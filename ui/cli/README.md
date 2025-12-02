# Recording indexer CLI usage

The CLI entry point `ui/cli/index_recordings.php` refreshes the SQL-backed recording index that the UI reads from when available.

## Prerequisites
- Ensure `ui/includes/config.php` is configured with SQL Server connection details and `maindirectory` points to the root directory that contains the agent subfolders.
- Run the script from the project root (same directory that contains the `ui/` folder) so relative paths resolve correctly.

## Run manually from a command prompt
Run the script with the PHP CLI binary. On Windows, specify the full path to `php.exe` if it is not on your `PATH`.

```bash
php ui/cli/index_recordings.php
# or on Windows
"C:\\Path\\to\\php.exe" "C:\\inetpub\\wwwroot\\callrec2\\ui\\cli\\index_recordings.php"
```

The script prints indexing counts (seen/inserted/updated/deleted) when it finishes.

## Schedule with Windows Task Scheduler
1. Open **Task Scheduler** → **Create Basic Task**.
2. Set the trigger (e.g., every 5 minutes or hourly).
3. Action: **Start a Program** and point to `php.exe` with the script path as the argument, e.g.:
   - **Program/script:** `C:\\Path\\to\\php.exe`
   - **Add arguments:** `"C:\\inetpub\\wwwroot\\callrec2\\ui\\cli\\index_recordings.php"`
   - **Start in:** `C:\\inetpub\\wwwroot\\callrec2`
4. (Optional) Redirect output to a log by wrapping the call in a `.cmd` file: `php.exe C:\\inetpub\\wwwroot\\callrec2\\ui\\cli\\index_recordings.php >> C:\\logs\\index_recordings.log 2>&1` and schedule the `.cmd` file.

The scheduler will invoke the PHP script on the cadence you choose, keeping the database index up to date without manual intervention.
