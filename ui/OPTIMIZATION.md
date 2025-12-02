# Recording search and retrieval optimizations

This application currently performs on-demand directory walks when loading recordings. Each agent fetch iterates recursively through their entire folder tree, parsing every filename to filter results. That approach becomes slow and can miss filters when directories hold tens of thousands of files.

## Observed bottlenecks
- **Per-request recursive scans:** `collectAgentRecordings()` descends through an agent's directory on every request and inspects each file before filtering. With 50k+ files per agent, the request blocks until the entire walk completes.
- **Filename parsing for filters:** Filters are derived by splitting the filename on every file (service group, timestamp, other party, description, call ID), adding CPU and I/O overhead during the scan.
- **Sequential filtering:** Date, participant, and service-group filters are evaluated in PHP after the file metadata is read, so we still traverse all files even when most could be excluded early.

## Options to improve performance and accuracy

1. **Index metadata in a database instead of scanning directories.**
   - Store filename-derived fields (service group, timestamp, other party, description, call ID) and the relative file path in a table updated by a background job.
   - Run SQL filters and pagination to fetch only matching rows, then build the playback URLs from stored path segments. This removes per-request filesystem walks.
   - **Background job flow:**
     1. A scheduler enqueues one job per agent directory on a fixed cadence (e.g., every 5–10 minutes for hot data, nightly for cold data).
     2. A CLI worker dequeues jobs and performs an incremental scan using stored `mtime`/`inode` checkpoints to skip previously indexed files.
     3. New or changed files are parsed once to extract metadata, then upserted in batches (e.g., 500–1,000 rows per transaction) to minimize lock time and I/O.
     4. Deleted files are detected by comparing current listings to the last checkpoint; matching rows are soft-deleted (set `deleted_at`) so queries can exclude them without blocking on hard deletes.
     5. When a job finishes, it writes a new checkpoint (last processed path + timestamp) to a small per-agent state table to keep future runs fast and resumable.
     6. Optional: emit metrics (rows inserted/updated/deleted, duration, lag) and send alerts if a job exceeds SLA or backlog grows.
   - **Does this require an external service?** Cron/Systemd timers are OS-level schedulers (not part of PHP). They simply invoke your PHP CLI script or queue publisher on a schedule. In containers, you can swap in the platform's scheduler (Kubernetes CronJob, ECS Scheduled Task, GitHub Actions, etc.) or use a queue with delayed jobs (e.g., Redis/Beanstalkd) if the host does not permit cron.
    - **PHP-native and Windows/IIS-friendly scheduling options:**
      - **Windows Task Scheduler + `php.exe`:** Create a scheduled task that runs `php.exe C:\path\to\indexer.php` every N minutes. This is built into Windows and keeps everything PHP-only (no extra daemons). Log stdout/stderr to a file so you can review failures.
      - **Looping PHP worker launched at boot:** Start a PHP CLI script (manually, via Task Scheduler "At startup," or a Windows service wrapper) that runs an infinite loop: pull jobs from your queue or file list, process, then `sleep(60)`. Because IIS recycles worker processes, keep this script outside IIS and run it with `php.exe` to avoid app-pool shutdowns.
      - **HTTP-triggered job endpoint:** Add a secured PHP endpoint (e.g., `GET /jobs/run-indexer?token=...`) that kicks off one batch. Pair it with Windows Task Scheduler calling `curl.exe` or `php.exe run_once.php` to avoid long-lived workers while still staying inside PHP. Use a token or IP restriction to prevent unauthorized triggers.
      - **File-based queue for pure-PHP installs:** If Redis/SQL queues are unavailable, maintain a simple JSON/CSV worklist on disk and use file locks (`flock`) to coordinate the looping worker so only one instance processes an agent at a time.
      - **Checkpoint and backoff:** Regardless of trigger method, persist last scanned filename/timestamp in a small SQLite/JSON file so each invocation resumes quickly, and add exponential backoff when a scan overruns its slot.

### Database-backed indexing with a Windows-scheduled PHP worker

The flow below stays native to PHP on Windows/IIS while offloading heavy directory scans to a background worker.

1. **Schema (example for SQL Server or MySQL):**
   - `recordings` table: `id` (PK), `agent`, `service_group`, `other_party`, `description`, `call_id`, `recorded_at` (datetime), `path` (relative), `duration_seconds` (optional), `deleted_at` (nullable).
   - `recording_index_state` table: `agent` (PK), `last_seen_path`, `last_seen_mtime`, `checkpointed_at`.
2. **Indexing script (CLI-only, run with `php.exe`):**
   - Accepts `--agent=<name>` (or scans all agents) plus `--since` override for backfills.
   - Reads the last checkpoint for the agent, walks the directory, and skips files older than the stored `last_seen_mtime` when `last_seen_path` is untouched.
   - Parses filename segments once (service group, timestamp, other party, description, call ID), then batches **upserts** (e.g., 500–1,000 rows per transaction) using prepared statements.
   - Detects deletions by comparing current files to the DB rows newer than the checkpoint and sets `deleted_at` for missing files (keeps history and speeds queries with `WHERE deleted_at IS NULL`).
   - Writes the newest processed `(path, mtime)` back to `recording_index_state` so the next run is incremental.
3. **Windows Task Scheduler setup (no external services):**
   - Create a **Basic Task** that runs `"C:\Path\to\php.exe" "C:\inetpub\wwwroot\callrec\cli\index_recordings.php" --agent=all` on a schedule (e.g., every 5 minutes for hot data, hourly for cold data).
   - Set **Start in** to the project root so relative paths resolve; enable **Run whether user is logged on or not**; and capture output to a log, e.g., `>> C:\logs\index_recordings.log 2>&1` via a simple `.cmd` wrapper.
   - Add a second task for **daily cleanup** (e.g., midnight) that prunes `deleted_at` rows older than retention or recomputes `duration_seconds` if you derive it once per file.
4. **Serving search requests:**
   - Replace filesystem scans with a DB query: `SELECT ... FROM recordings WHERE deleted_at IS NULL AND agent = ? AND recorded_at BETWEEN ? AND ? AND ... ORDER BY recorded_at DESC LIMIT ? OFFSET ?`.
   - Construct playback URLs from the stored `path` segments; only touch the filesystem when streaming the chosen file.
5. **Observability and retries:**
   - Log each run's counts (inserted/updated/deleted/errored) and duration to the Task Scheduler log file.
   - If a run fails, leave the checkpoint unchanged so the next invocation safely retries the same window.
   - Add a lightweight health endpoint (e.g., `GET /health/indexer`) that reports the max `checkpointed_at` per agent so you can alert when lag exceeds your SLA.

2. **Build per-agent manifest files for lightweight lookups.**
   - Generate a JSON or SQLite manifest per agent that lists files with their parsed metadata. Update manifests asynchronously when new files arrive.
   - Search endpoints would read the manifest (or query the local SQLite file) to resolve matches quickly while keeping file storage unchanged.

3. **Shard directories to reduce filesystem traversal cost.**
   - Introduce subdirectory hashing (e.g., first two digits of the call ID or date) so no single folder holds tens of thousands of files. This improves filesystem lookup times and reduces iterator overhead.

4. **Cache recent query results and directory stats.**
   - Cache "recent" (last 14 days) listings per agent in-memory or in Redis with a TTL, invalidating when manifests update. Subsequent requests reuse cached lists instead of re-scanning.

5. **Use asynchronous search requests with server-side streaming.**
   - Offload the scan/index read to a queue worker and return a job ID to the UI; stream results as they are found. This keeps the UI responsive for worst-case directories and allows progress indicators.

6. **Normalize filters server-side before traversal.**
   - Apply inclusive date ranges and combined filters in a single predicate, and short-circuit traversal when the date range excludes older directories. This reduces unnecessary file visits.

7. **Add health metrics and tracing.**
   - Emit timings and counters (files scanned, skipped, returned) to logs or Prometheus to spot hotspots and validate improvements after each change.

8. **Pre-generate playback/download URLs.**
   - When indexing, also store the public URL segments so the UI can render rows without repeated `prepareRecordingSegments()` work per request.

## Quick win prototype

As a first step without major storage changes, consider generating a per-agent manifest nightly:
- Cron runs a PHP/CLI script that walks each agent directory once, writes a gzipped JSON manifest of parsed metadata, and stores an `mtime` marker.
- The web request reads the manifest, filters in memory, and only falls back to a live scan if the manifest is missing or older than a threshold.
- This can be built incrementally and later migrated to a full database index if needed.
