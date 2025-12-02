<?php
	
        class model
        {
                private $recordingBaseUrl;
                private $logDirectory;
                private $logFile;

		function __construct()
		{
			/*$this->initialiseLogging();  */

			$serverName = "GNT-SEC";
			$connectionInfo = array( "Database"=>dbname, "UID"=>username, "PWD"=>password);
			$connect	=	sqlsrv_connect( $serverName, $connectionInfo);

			if(!$connect)
			{
				$this->logMessage('error', 'Database connection failed', array('errors' => $this->collectSqlErrors()));
				die('Could Not Connect!');
				/* die( print_r( sqlsrv_errors(), true)); */
			}
		/* 	mssql_select_db(dbname,$connect) ; */
                define('connect',$connect);
                $this->recordingBaseUrl = defined('recording_base_url') ? rtrim(recording_base_url, '/\\') : '';
                }

         /*       private function initialiseLogging()
                {
                        $this->logDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . "logs";

                        if (!is_dir($this->logDirectory)) {
                                if (!@mkdir($this->logDirectory, 0775, true) && !is_dir($this->logDirectory)) {
                                        $this->logDirectory = null;
                                        $this->logFile = null;
                                        return;
                                }
                        }

                        if (!is_writable($this->logDirectory)) {
                                $this->logDirectory = null;
                                $this->logFile = null;
                                return;
                        }

                        $this->logFile = $this->logDirectory . DIRECTORY_SEPARATOR . "application.log";
                }   */

                private function collectSqlErrors()
                {
                        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);

                        if (!is_array($errors)) {
                                return array();
                        }

                        $filtered = array();

                        foreach ($errors as $error) {
                                $filtered[] = array(
                                        "code" => isset($error["code"]) ? $error["code"] : null,
                                        "message" => isset($error["message"]) ? $error["message"] : null,
                                );
                        }

                        return $filtered;
                }

                private function normaliseContext(array $context)
                {
                        $filtered = array();

                        foreach ($context as $key => $value) {
                                if (is_scalar($value) || $value === null) {
                                        $filtered[$key] = $value;
                                        continue;
                                }

                                if ($value instanceof \JsonSerializable) {
                                        $filtered[$key] = $value;
                                        continue;
                                }

                                if ($value instanceof \Throwable) {
                                        $filtered[$key] = array(
                                                "type" => get_class($value),
                                                "message" => $value->getMessage(),
                                        );
                                        continue;
                                }

                                $filtered[$key] = json_decode(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), true);
                        }

                        if (!isset($filtered["request_uri"]) && isset($_SERVER["REQUEST_URI"])) {
                                $filtered["request_uri"] = $_SERVER["REQUEST_URI"];
                        }

                        if (!isset($filtered["remote_addr"]) && isset($_SERVER["REMOTE_ADDR"])) {
                                $filtered["remote_addr"] = $_SERVER["REMOTE_ADDR"];
                        }

                        return $filtered;
                }

                private function logMessage($level, $message, array $context = array())
                {
                        if ($this->logFile === null) {
                                return;
                        }

                        $level = strtoupper($level);
                        $timestamp = date('Y-m-d H:i:s');
                        $contextData = $this->normaliseContext($context);
                        $line = $timestamp . " [" . $level . "] " . $message;

                        if (!empty($contextData)) {
                                $json = json_encode($contextData, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

                                if ($json !== false) {
                                        $line .= " " . $json;
                                }
                        }

                        $line .= PHP_EOL;

                        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
                }

                private function logException(\Throwable $exception, array $context = array())
                {
                        $context['exception'] = array(
                                "type" => get_class($exception),
                                "message" => $exception->getMessage(),
                                "file" => $exception->getFile(),
                                "line" => $exception->getLine(),
                        );

                        $this->logMessage('error', "Unhandled exception", $context);
                }



                private function resolveDirectoryNameForId($rawId)
                {
                        $trimmed = trim((string) $rawId);

                        if ($trimmed === '') {
                                return '';
                        }

                        $baseDirectory = rtrim(maindirectory, '/\\');

                        if (preg_match('/^-?\d+$/', $trimmed)) {
                                $numericId = (int) $trimmed;

                                if ($numericId < 0) {
                                        return '';
                                }

                                $padded = str_pad((string) $numericId, 6, '0', STR_PAD_LEFT);
                                $candidates = array($padded);

                                if ($padded !== $trimmed) {
                                        $candidates[] = ltrim($trimmed, '0');
                                        $candidates[] = $trimmed;
                                }

                                foreach ($candidates as $candidate) {
                                        if ($candidate === '') {
                                                continue;
                                        }

                                        $fullPath = $baseDirectory . DIRECTORY_SEPARATOR . $candidate;

                                        if (is_dir($fullPath)) {
                                                return $candidate;
                                        }
                                }

                                return $padded;
                        }

                        $fullPath = $baseDirectory . DIRECTORY_SEPARATOR . $trimmed;

                        if (is_dir($fullPath)) {
                                return $trimmed;
                        }

                        return $trimmed;
                }

                public function getAgentRoster()
                {
                        $sql = "SELECT id, first_name, last_name FROM dbo.cc_user WHERE delete_date > GETDATE()";
                        $query = sqlsrv_query(connect, $sql);

                        if ($query === false) {
                                $this->logMessage('error', 'Failed to load agent roster', array('sql' => $sql, 'errors' => $this->collectSqlErrors()));
                                return array();
                        }

                        $roster = array();
                        $usedDomIds = array();

                        while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
                                if (!isset($row['id'])) {
                                        $this->logMessage('debug', 'Skipping roster row without id', array('row' => $row));
                                        continue;
                                }

                                $rawId = (string) $row['id'];
                                $directoryName = $this->resolveDirectoryNameForId($rawId);

                                if ($rawId === '' || $directoryName === '') {
                                        $this->logMessage('debug', 'Skipping roster entry without directory', array('agent_id' => $rawId, 'resolved_directory' => $directoryName));
                                        continue;
                                }

                                $firstName = isset($row['first_name']) ? trim($row['first_name']) : '';
                                $lastName = isset($row['last_name']) ? trim($row['last_name']) : '';
                                $display = trim($firstName . ' ' . $lastName);

                                if ($display === '') {
                                        $display = $directoryName;
                                }

                                $agentDomId = preg_replace('/[^A-Za-z0-9_-]/', '', $directoryName);

                                if ($agentDomId === '') {
                                        $agentDomId = substr(md5($rawId), 0, 8);
                                }

                                $baseDomId = $agentDomId;
                                $suffix = 2;

                                while (isset($usedDomIds[$agentDomId])) {
                                        $agentDomId = $baseDomId . '-' . $suffix;
                                        $suffix++;
                                }

                                $usedDomIds[$agentDomId] = true;

                                $roster[] = array(
                                        'domId' => $agentDomId,
                                        'directory' => $directoryName,
                                        'displayName' => $display,
                                        'agentId' => $rawId,
                                );
                        }

                        sqlsrv_free_stmt($query);
                        $this->logMessage('debug', 'Roster loaded', array('count' => count($roster)));

                        return $roster;
                }

                public function getServiceGroups()
                {
                        $sql = "SELECT id, name FROM dbo.service_grp WHERE id > 0 ORDER BY name";
                        $query = sqlsrv_query(connect, $sql);

                        if ($query === false) {
                                $this->logMessage('error', 'Failed to load service groups', array('errors' => $this->collectSqlErrors()));
                                return array();
                        }

                        $groups = array();
                        $seenNames = array();

                        while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
                                if (!isset($row['name'])) {
                                        $this->logMessage('debug', 'Service group missing name field', array('row' => $row));
                                        continue;
                                }

                                $name = trim((string) $row['name']);

                                if ($name === '') {
                                        $this->logMessage('debug', 'Service group name empty after trim', array('row' => $row));
                                        continue;
                                }

                                $normalized = strtolower($name);

                                if (isset($seenNames[$normalized])) {
                                        $this->logMessage('debug', 'Skipping duplicate service group', array('name' => $name));
                                        continue;
                                }

                                $seenNames[$normalized] = true;

                                $groups[] = array(
                                        'name' => $name,
                                        'id' => isset($row['id']) ? (int) $row['id'] : null,
                                );
                        }

                        sqlsrv_free_stmt($query);
                        $this->logMessage('debug', 'Loaded service groups', array('count' => count($groups)));

                        return $groups;
                }
		
		function admin_login()
		{
			$username	=	trim($_POST['username']);
			$password	=	trim($_POST['password']);
			
			if($username==adminusername && $password==adminpassword)
			{
						$_SESSION['username']	=	adminusername;
						$_SESSION['login']		=	'Admin';
						header('Location:index.php');
						die;
			}else{
						$_SESSION['invalid']	=	'invalid';
						header('Location:login.php');
						die;
			}
		}
		
		function redirect($url)
		{
			header('location:'.$url.'');
			die;
		}
                private function prepareRecordingSegments(array $segments)
                {
                        $clean = array();

                        foreach ($segments as $segment) {
                                if (!is_string($segment)) {
                                        continue;
                                }

                                $sanitized = trim(str_replace(array('/', '\\'), '', $segment));

                                if ($sanitized === '' || $sanitized === '.' || $sanitized === '..') {
                                        continue;
                                }

                                $clean[] = $sanitized;
                        }

                        return $clean;
                }

                private function encodeSegmentForUrl($segment)
                {
                        $encoded = rawurlencode($segment);

                        return strtr($encoded, array('%24' => '$'));
                }

                private function buildPublicRecordingUrl(array $segments)
                {
                        $cleanSegments = $this->prepareRecordingSegments($segments);

                        if (empty($cleanSegments) || empty($this->recordingBaseUrl)) {
                                return '';
                        }

                        $encodedSegments = array_map(array($this, 'encodeSegmentForUrl'), $cleanSegments);

                        return $this->recordingBaseUrl . '/' . implode('/', $encodedSegments);
                }

                private function buildDownloadHref(array $segments, $downloadName)
                {
                        $cleanSegments = $this->prepareRecordingSegments($segments);

                        if (empty($cleanSegments)) {
                                return '#';
                        }

                        $safeName = basename($downloadName);
                        $publicUrl = $this->buildPublicRecordingUrl($segments);

                        if ($publicUrl !== '') {
                                $query = http_build_query(array(
                                        'download' => $publicUrl,
                                        'filename' => $safeName,
                                ));

                                return 'index.php?' . $query;
                        }

                        $relativePath = implode('/', $cleanSegments);

                        $query = http_build_query(array(
                                'download' => $relativePath,
                                'filename' => $safeName,
                        ));

                        return 'index.php?' . $query;
                }

                private function ensureRecordingIndexTable()
                {
                        $sql = "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'recordings_index')\n"
                             . "BEGIN\n"
                             . "    CREATE TABLE dbo.recordings_index (\n"
                             . "        id INT IDENTITY(1,1) PRIMARY KEY,\n"
                             . "        agent_directory NVARCHAR(128) NOT NULL,\n"
                             . "        relative_path NVARCHAR(260) NOT NULL,\n"
                             . "        recording_name NVARCHAR(260) NOT NULL,\n"
                             . "        service_group NVARCHAR(120) NULL,\n"
                             . "        other_party NVARCHAR(120) NULL,\n"
                             . "        call_id NVARCHAR(120) NULL,\n"
                             . "        description NVARCHAR(260) NULL,\n"
                             . "        recorded_at DATETIME2 NULL,\n"
                             . "        file_size BIGINT NULL,\n"
                             . "        last_seen_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),\n"
                             . "        CONSTRAINT UQ_recordings_index_path UNIQUE(agent_directory, relative_path)\n"
                             . "    );\n"
                             . "    CREATE INDEX IX_recordings_index_agent_date ON dbo.recordings_index(agent_directory, recorded_at DESC);\n"
                             . "END";

                        $query = sqlsrv_query(connect, $sql);

                        if ($query === false) {
                                $this->logMessage('error', 'Failed to ensure recordings_index table exists', array('errors' => $this->collectSqlErrors()));
                        }

                        if (is_resource($query)) {
                                sqlsrv_free_stmt($query);
                        }
                }

                private function recordingIndexAvailable()
                {
                        $sql = "SELECT 1 FROM sys.tables WHERE name = 'recordings_index'";
                        $query = sqlsrv_query(connect, $sql);

                        if ($query === false) {
                                return false;
                        }

                        $exists = sqlsrv_fetch_array($query) !== null;
                        sqlsrv_free_stmt($query);

                        return $exists;
                }

                private function extractTimestampFromFilename($filename)
                {
                        $parts = explode('$', $filename);

                        if (count($parts) < 2) {
                                return null;
                        }

                        $candidate = $parts[1];

                        if (!preg_match('/^\d{14}$/', $candidate)) {
                                return null;
                        }

                        $date = \DateTime::createFromFormat('YmdHis', $candidate);

                        if ($date === false) {
                                return null;
                        }

                        return $date->getTimestamp();
                }

                private function parseRecordingFilename($filename)
                {
                        $parts = explode('$', $filename);

                        if (count($parts) < 5) {
                                return null;
                        }

                        return array(
                                'service_group' => $parts[0],
                                'datetime' => $parts[1],
                                'other_party' => $parts[2],
                                'description' => $parts[3],
                                'call_id' => isset($parts[4]) ? explode('.', $parts[4])[0] : '',
                        );
                }
		function logout()
		{
			unset($_SESSION['username']);
			unset($_SESSION['login']);
			$this->redirect('login.php');
		}
                function Sort_Directory_Files_By_Last_Modified($dir, $sort_type = 'descending', $date_format = "F d Y H:i:s")
                {
                        $files = scandir($dir);

                        $array = array();

                        foreach($files as $file)
                        {
                                                                if($file != '.' && $file != '..')
                                                {
                                                        $now = time();
                                                        $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
                                                        $last_modified = $this->extractTimestampFromFilename($file);

                                                        if ($last_modified === null) {
                                                                $last_modified = @filemtime($fullPath);
                                                        }

                                                        $time_passed_array = array();

                                                        if (is_int($last_modified) && $last_modified > 0) {
                                                                $diff = $now - $last_modified;

                                                                $days = floor($diff / (3600 * 24));

                                                                if($days)
                                                                {
                                                                $time_passed_array['days'] = $days;
                                                                }

                                                                $diff = $diff - ($days * 3600 * 24);

                                                                $hours = floor($diff / 3600);

                                                                if($hours)
                                                                {
                                                                $time_passed_array['hours'] = $hours;
                                                                }

                                                                $diff = $diff - (3600 * $hours);

                                                                $minutes = floor($diff / 60);

                                                                if($minutes)
                                                                {
                                                                $time_passed_array['minutes'] = $minutes;
                                                                }

                                                                $seconds = $diff - ($minutes * 60);

                                                                $time_passed_array['seconds'] = $seconds;
                                                        }

                    $array[] = array('file'         => $file,
                                                             'timestamp'    => is_int($last_modified) ? $last_modified : null,
                                                             'date'         => is_int($last_modified) ? date ($date_format, $last_modified) : '',
                                                             'time_passed'  => $time_passed_array);
                    }
                }

                usort($array, static function ($a, $b) {
                        $aTime = $a['timestamp'] ?? null;
                        $bTime = $b['timestamp'] ?? null;

                        if ($aTime === $bTime) {
                                return 0;
                        }

                        if ($aTime === null) {
                                return 1;
                        }

                        if ($bTime === null) {
                                return -1;
                        }

                        return ($aTime < $bTime) ? -1 : 1;
                });

                if($sort_type == 'descending')
                {
                $array = array_reverse($array);
                }

                return array($array, $sort_type);
        }
                private function recordingMatchesFilters($datetime, $description, $otherparty, $servicegroup, $callId)
                {
                        if (!isset($_POST['action']) || $_POST['action'] !== 'getdirectory') {
                                return true;
                        }

                        if (!empty($_POST['name'])) {
                                return $_POST['name'] === $description;
                        }

                        if (!empty($_POST['date']) && !empty($_POST['enddate'])) {
                                $paymentDate = date('Y-m-d', strtotime($datetime));
                                return (strtotime($paymentDate) >= strtotime($_POST['date'])) && (strtotime($paymentDate) <= strtotime($_POST['enddate']));
                        }

                        if (!empty($_POST['other_party'])) {
                                return $_POST['other_party'] === $otherparty;
                        }

                        if (!empty($_POST['service_group'])) {
                                return $_POST['service_group'] === $servicegroup;
                        }

                        if (!empty($_POST['call_id'])) {
                                return $_POST['call_id'] === $callId;
                        }

                        return true;
                }

                private function collectAgentRecordings($directoryName, $scope, $recentCutoff, $page, $perPage)
                {
                        $baseDirectory = rtrim(maindirectory, '/\\');
                        $agentRoot = $baseDirectory . DIRECTORY_SEPARATOR . $directoryName;

                        if (!is_dir($agentRoot)) {
                                $this->logMessage('warning', 'Agent directory missing or unreadable', array('directory' => $directoryName, 'path' => $agentRoot));
                                return array('records' => array(), 'total' => 0);
                        }

                        $offset = ($page - 1) * $perPage;

                        if ($offset < 0) {
                                $offset = 0;
                        }

                        $heapLimit = $offset + $perPage;

                        if ($heapLimit < $perPage) {
                                $heapLimit = $perPage;
                        }

                        $stats = array(
                                'total_scanned' => 0,
                                'skipped_non_files' => 0,
                                'skipped_old' => 0,
                                'skipped_malformed' => 0,
                                'skipped_filtered' => 0,
                                'skipped_path' => 0,
                                'missing_timestamp' => 0,
                        );
                        $heap = new class extends \SplMinHeap {
                                protected function compare($value1, $value2)
                                {
                                        if ($value1['priority'] === $value2['priority']) {
                                                return 0;
                                        }

                                        return ($value1['priority'] < $value2['priority']) ? -1 : 1;
                                }
                        };

                        $totalMatches = 0;
                        $baseLength = strlen($baseDirectory) + 1;

                        try {
                                $iterator = new \RecursiveIteratorIterator(
                                        new \RecursiveDirectoryIterator(
                                                $agentRoot,
                                                \FilesystemIterator::SKIP_DOTS
                                        ),
                                        \RecursiveIteratorIterator::SELF_FIRST
                                );
                        } catch (\Throwable $exception) {
                                $this->logException($exception, array(
                                        'directory' => $directoryName,
                                        'path' => $agentRoot,
                                        'stage' => 'iterator_initialisation',
                                ));
                                return array('records' => array(), 'total' => 0);
                        }

                        try {
                        foreach ($iterator as $info) {
                                $stats['total_scanned']++;
                                if (!$info->isFile()) {
                                        $stats['skipped_non_files']++;
                                        continue;
                                }

                                $filename = $info->getFilename();
                                $timestamp = $this->extractTimestampFromFilename($filename);

                                if ($timestamp === null) {
                                        $stats['missing_timestamp']++;
                                        $fileTimestamp = @filemtime($info->getPathname());

                                        if ($fileTimestamp !== false) {
                                                $timestamp = (int) $fileTimestamp;
                                        }
                                }

                                if ($scope === 'recent' && $timestamp !== null && $timestamp < $recentCutoff) {
                                        $stats['skipped_old']++;
                                        continue;
                                }

                                $parts = explode('$', $filename);

                                if (count($parts) < 5) {
                                        $stats['skipped_malformed']++;
                                        continue;
                                }

                                $servicegroup = $parts[0];
                                $datetime = $parts[1];
                                $otherparty = $parts[2];
                                $description = $parts[3];
                                $callParts = explode('.', $parts[4]);
                                $callId = $callParts[0];

                                if (!$this->recordingMatchesFilters($datetime, $description, $otherparty, $servicegroup, $callId)) {
                                        $stats['skipped_filtered']++;
                                        continue;
                                }

                                $fullPath = $info->getPathname();
                                $relativePath = substr($fullPath, $baseLength);

                                if ($relativePath === false || $relativePath === '') {
                                        continue;
                                }

                                $rawSegments = preg_split('/[\\\\\/]+/', $relativePath);
                                $segments = $this->prepareRecordingSegments($rawSegments);

                                if (empty($segments)) {
                                        $stats['skipped_path']++;
                                        continue;
                                }

                                $totalMatches++;

                                $record = array(
                                        'segments' => $segments,
                                        'downloadName' => $filename,
                                        'otherparty' => $otherparty,
                                        'datetime' => $datetime,
                                        'servicegroup' => $servicegroup,
                                        'callId' => $callId,
                                        'description' => $description,
                                        'timestamp' => $timestamp,
                                );

                                if ($heapLimit <= 0) {
                                        continue;
                                }

                                $priority = ($timestamp !== null) ? (int) $timestamp : PHP_INT_MIN;

                                if ($heap->count() < $heapLimit) {
                                        $heap->insert(array('priority' => $priority, 'record' => $record));
                                        continue;
                                }

                                $top = $heap->top();

                                if ($priority > $top['priority']) {
                                        $heap->extract();
                                        $heap->insert(array('priority' => $priority, 'record' => $record));
                                }
                        }
                        } catch (\Throwable $exception) {
                                $this->logException($exception, array('directory' => $directoryName, 'path' => $agentRoot, 'stage' => 'iteration', 'stats' => $stats));
                                return array('records' => array(), 'total' => 0);
                        }

                        $collected = array();
                        $heapClone = clone $heap;

                        while (!$heapClone->isEmpty()) {
                                $collected[] = $heapClone->extract();
                        }

                        if (!empty($collected)) {
                                usort($collected, static function ($a, $b) {
                                        if ($a['priority'] === $b['priority']) {
                                                return 0;
                                        }

                                        return ($a['priority'] < $b['priority']) ? 1 : -1;
                                });
                        }

                        $records = array();

                        foreach ($collected as $item) {
                                $records[] = $item['record'];
                        }

                        $pageRecords = array_slice($records, $offset, $perPage);
                        $this->logMessage('debug', 'Collected agent recordings', array(
                                'directory' => $directoryName,
                                'scope' => $scope,
                                'page' => $page,
                                'per_page' => $perPage,
                                'total_matches' => $totalMatches,
                                'returned' => count($pageRecords),
                                'stats' => $stats,
                        ));

                        return array(
                                'records' => $pageRecords,
                                'total' => $totalMatches,
                        );
                }

                private function deleteStaleIndexedRecords($cutoffUtc)
                {
                        $sql = "DELETE FROM dbo.recordings_index WHERE last_seen_at < ?";
                        $stmt = sqlsrv_query(connect, $sql, array($cutoffUtc));

                        if ($stmt === false) {
                                $this->logMessage('error', 'Failed to delete stale indexed recordings', array('errors' => $this->collectSqlErrors()));
                                return 0;
                        }

                        $deleted = sqlsrv_rows_affected($stmt);
                        sqlsrv_free_stmt($stmt);

                        return is_numeric($deleted) ? (int) $deleted : 0;
                }

                private function upsertRecordingIndex(array $record, $lastSeenUtc)
                {
                        $updateSql = "UPDATE dbo.recordings_index\n"
                                   . "SET recording_name = ?, service_group = ?, other_party = ?, call_id = ?, description = ?, recorded_at = ?, file_size = ?, last_seen_at = ?\n"
                                   . "WHERE agent_directory = ? AND relative_path = ?";
                        $updateParams = array(
                                $record['recording_name'],
                                $record['service_group'],
                                $record['other_party'],
                                $record['call_id'],
                                $record['description'],
                                $record['recorded_at'],
                                $record['file_size'],
                                $lastSeenUtc,
                                $record['agent_directory'],
                                $record['relative_path'],
                        );

                        $updateStmt = sqlsrv_query(connect, $updateSql, $updateParams);

                        if ($updateStmt !== false) {
                                $updatedRows = sqlsrv_rows_affected($updateStmt);
                                sqlsrv_free_stmt($updateStmt);

                                if (is_numeric($updatedRows) && $updatedRows > 0) {
                                        return 'updated';
                                }
                        }

                        $insertSql = "INSERT INTO dbo.recordings_index (agent_directory, relative_path, recording_name, service_group, other_party, call_id, description, recorded_at, file_size, last_seen_at)\n"
                                   . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $insertParams = array(
                                $record['agent_directory'],
                                $record['relative_path'],
                                $record['recording_name'],
                                $record['service_group'],
                                $record['other_party'],
                                $record['call_id'],
                                $record['description'],
                                $record['recorded_at'],
                                $record['file_size'],
                                $lastSeenUtc,
                        );

                        $insertStmt = sqlsrv_query(connect, $insertSql, $insertParams);

                        if ($insertStmt === false) {
                                $this->logMessage('error', 'Failed to upsert indexed recording', array('record' => $record, 'errors' => $this->collectSqlErrors()));
                                return false;
                        }

                        sqlsrv_free_stmt($insertStmt);

                        return 'inserted';
                }

                private function fetchIndexedRecordings($directoryName, $scope, $page, $perPage)
                {
                        if (!$this->recordingIndexAvailable()) {
                                return null;
                        }

                        $offset = ($page - 1) * $perPage;

                        if ($offset < 0) {
                                $offset = 0;
                        }

                        $params = array($directoryName);
                        $where = "WHERE agent_directory = ?";

                        if ($scope === 'recent') {
                                $where .= " AND recorded_at IS NOT NULL AND recorded_at >= DATEADD(day, -14, SYSUTCDATETIME())";
                        }

                        $countSql = "SELECT COUNT(*) AS total FROM dbo.recordings_index {$where}";
                        $countStmt = sqlsrv_query(connect, $countSql, $params);

                        if ($countStmt === false) {
                                $this->logMessage('error', 'Failed to count indexed recordings', array('directory' => $directoryName, 'errors' => $this->collectSqlErrors()));
                                return null;
                        }

                        $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
                        sqlsrv_free_stmt($countStmt);

                        $total = isset($countRow['total']) ? (int) $countRow['total'] : 0;

                        if ($total === 0) {
                                return array('records' => array(), 'total' => 0);
                        }

                        $listSql = "SELECT agent_directory, relative_path, recording_name, service_group, other_party, call_id, description, recorded_at\n"
                                 . "FROM dbo.recordings_index {$where}\n"
                                 . "ORDER BY recorded_at DESC, id DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

                        $listParams = array_merge($params, array($offset, $perPage));
                        $listStmt = sqlsrv_query(connect, $listSql, $listParams);

                        if ($listStmt === false) {
                                $this->logMessage('error', 'Failed to fetch indexed recordings', array('directory' => $directoryName, 'errors' => $this->collectSqlErrors()));
                                return null;
                        }

                        $records = array();

                        while ($row = sqlsrv_fetch_array($listStmt, SQLSRV_FETCH_ASSOC)) {
                                $relativePath = isset($row['relative_path']) ? (string) $row['relative_path'] : '';
                                $pathSegments = $relativePath !== '' ? preg_split('/[\\\\\/]+/', $relativePath) : array();

                                array_unshift($pathSegments, $directoryName);

                                $recordedAtValue = null;

                                if (isset($row['recorded_at']) && $row['recorded_at'] !== null) {
                                        if ($row['recorded_at'] instanceof \DateTimeInterface) {
                                                $recordedAtValue = $row['recorded_at']->getTimestamp();
                                        } else {
                                                $recordedAtValue = strtotime((string) $row['recorded_at']);
                                        }
                                }

                                $records[] = array(
                                        'segments' => $this->prepareRecordingSegments($pathSegments),
                                        'downloadName' => isset($row['recording_name']) ? (string) $row['recording_name'] : '',
                                        'otherparty' => isset($row['other_party']) ? (string) $row['other_party'] : '',
                                        'datetime' => ($recordedAtValue !== null && $recordedAtValue !== false) ? date('YmdHis', $recordedAtValue) : '',
                                        'servicegroup' => isset($row['service_group']) ? (string) $row['service_group'] : '',
                                        'callId' => isset($row['call_id']) ? (string) $row['call_id'] : '',
                                        'description' => isset($row['description']) ? (string) $row['description'] : '',
                                        'timestamp' => ($recordedAtValue !== null && $recordedAtValue !== false) ? $recordedAtValue : null,
                                );
                        }

                        sqlsrv_free_stmt($listStmt);

                        return array(
                                'records' => $records,
                                'total' => $total,
                        );
                }

                public function runRecordingIndexer($baseDirectory = null, $mode = 'full')
                {
                        $this->ensureRecordingIndexTable();

                        if (!$this->recordingIndexAvailable()) {
                                return array('inserted' => 0, 'updated' => 0, 'deleted' => 0, 'seen' => 0);
                        }

                        $root = rtrim($baseDirectory ?: maindirectory, '/\\');
                        $passStartedAt = gmdate('Y-m-d H:i:s');

                        $isIncremental = ($mode === 'incremental');
                        $lastSeenAt = $isIncremental ? $this->getLatestIndexedSeenAt() : null;
                        $lastSeenTimestamp = null;

                        if ($lastSeenAt !== null) {
                                if ($lastSeenAt instanceof \DateTimeInterface) {
                                        $lastSeenTimestamp = $lastSeenAt->getTimestamp();
                                } else {
                                        $lastSeenTimestamp = strtotime((string) $lastSeenAt);
                                }
                        }

                        $stats = array(
                                'inserted' => 0,
                                'updated' => 0,
                                'deleted' => 0,
                                'seen' => 0,
                        );

                        if (!is_dir($root)) {
                                $this->logMessage('error', 'Recording root missing for indexer', array('path' => $root));
                                return $stats;
                        }

                        $agentDirs = @scandir($root);

                        if (!is_array($agentDirs)) {
                                $this->logMessage('error', 'Unable to read recording root for indexer', array('path' => $root));
                                return $stats;
                        }

                        foreach ($agentDirs as $agentDir) {
                                if (in_array($agentDir, array('.', '..'), true)) {
                                        continue;
                                }

                                $agentPath = $root . DIRECTORY_SEPARATOR . $agentDir;

                                if (!is_dir($agentPath)) {
                                        continue;
                                }

                                try {
                                        $iterator = new \RecursiveIteratorIterator(
                                                new \RecursiveDirectoryIterator(
                                                        $agentPath,
                                                        \FilesystemIterator::SKIP_DOTS
                                                ),
                                                \RecursiveIteratorIterator::LEAVES_ONLY
                                        );
                                } catch (\Throwable $exception) {
                                        $this->logException($exception, array('directory' => $agentDir, 'stage' => 'iterator_initialisation'));
                                        continue;
                                }

                                        foreach ($iterator as $info) {
                                                if (!$info->isFile()) {
                                                        continue;
                                                }

                                                if ($isIncremental && $lastSeenTimestamp !== null) {
                                                        $mtime = $info->getMTime();

                                                        if ($mtime !== false && $mtime <= $lastSeenTimestamp) {
                                                                continue;
                                                        }
                                                }

                                                $filename = $info->getFilename();
                                                $parsed = $this->parseRecordingFilename($filename);

                                                if ($parsed === null) {
                                                        continue;
                                        }

                                        $datetime = isset($parsed['datetime']) ? $parsed['datetime'] : '';
                                        $recordedAt = null;

                                        if (preg_match('/^\d{14}$/', $datetime)) {
                                                $dt = \DateTime::createFromFormat('YmdHis', $datetime);

                                                if ($dt instanceof \DateTime) {
                                                        $recordedAt = $dt->format('Y-m-d H:i:s');
                                                }
                                        }

                                        if ($recordedAt === null) {
                                                $fallbackTimestamp = $info->getMTime();

                                                if ($fallbackTimestamp !== false) {
                                                        $recordedAt = date('Y-m-d H:i:s', $fallbackTimestamp);
                                                }
                                        }

                                        $absolutePath = $info->getPathname();
                                        $basePath = rtrim($agentPath, '/\\');

                                        if (stripos($absolutePath, $basePath . DIRECTORY_SEPARATOR) === 0) {
                                                $relativePath = substr($absolutePath, strlen($basePath) + 1);
                                        } else {
                                                $relativePath = $filename;
                                        }

                                        $normalizedRelative = str_replace('\\', '/', $relativePath);

                                        $record = array(
                                                'agent_directory' => $agentDir,
                                                'relative_path' => $normalizedRelative,
                                                'recording_name' => $filename,
                                                'service_group' => $parsed['service_group'],
                                                'other_party' => $parsed['other_party'],
                                                'call_id' => $parsed['call_id'],
                                                'description' => $parsed['description'],
                                                'recorded_at' => $recordedAt,
                                                'file_size' => $info->getSize(),
                                        );

                                        $result = $this->upsertRecordingIndex($record, $passStartedAt);

                                        $stats['seen']++;

                                        if ($result === 'inserted') {
                                                $stats['inserted']++;
                                        } elseif ($result === 'updated') {
                                                $stats['updated']++;
                                        }
                                        }
                                }

                        if (!$isIncremental) {
                                $stats['deleted'] = $this->deleteStaleIndexedRecords($passStartedAt);
                        }

                        $this->logMessage('info', 'Recording indexer completed', $stats);

                        return $stats;
                }

                private function getLatestIndexedSeenAt()
                {
                        if (!$this->recordingIndexAvailable()) {
                                return null;
                        }

                        $sql = "SELECT MAX(last_seen_at) AS last_seen_at FROM dbo.recordings_index";
                        $stmt = sqlsrv_query(connect, $sql);

                        if ($stmt === false) {
                                $this->logMessage('error', 'Failed to query last seen timestamp for recordings index', array('errors' => $this->collectSqlErrors()));
                                return null;
                        }

                        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                        sqlsrv_free_stmt($stmt);

                        if ($row === null || !isset($row['last_seen_at'])) {
                                return null;
                        }

                        return $row['last_seen_at'];
                }

                public function renderRecordingRow($index, array $pathSegments, $downloadName, $otherparty, $datetime, $servicegroup, $callId, $description)
                {
                        $playUrl = $this->buildPublicRecordingUrl($pathSegments);
                        $downloadLabel = $downloadName !== '' ? $downloadName : 'recording.mp3';
                        $downloadHref = $this->buildDownloadHref($pathSegments, $downloadLabel);
                        $otherpartyEsc = htmlspecialchars($otherparty, ENT_QUOTES, 'UTF-8');
                        $serviceEsc = htmlspecialchars($servicegroup, ENT_QUOTES, 'UTF-8');
                        $descriptionEsc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                        $callEsc = htmlspecialchars($callId, ENT_QUOTES, 'UTF-8');
                        $dateDisplay = date('d/m/Y H:i:s A', strtotime($datetime));
                        $dateEsc = htmlspecialchars($dateDisplay, ENT_QUOTES, 'UTF-8');
                        $downloadHref = htmlspecialchars($downloadHref, ENT_QUOTES, 'UTF-8');
                        $downloadAttr = htmlspecialchars($downloadLabel, ENT_QUOTES, 'UTF-8');
                        $playArgument = json_encode($playUrl, JSON_UNESCAPED_SLASHES);
                        if ($playArgument === false) {
                                $playArgument = json_encode('');
                        }
                        $onclick = htmlspecialchars('DHTMLSound('.$playArgument.','.$index.')', ENT_QUOTES, 'UTF-8');

                        return <<<HTML
                                  <tr class="table_row table_row--detail">
                                   <td class="record-cell record-cell--actions">
                                     <div class="action-toolbar">
                                       <a href="javascript:void(0)" class="action-icon action-icon--play" onclick="{$onclick}">
                                         <span class="sr-only">Play recording</span>
                                         <svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="M9.5 7.4a.75.75 0 0 1 1.15-.64l6.25 4.1a.75.75 0 0 1 0 1.28l-6.25 4.1A.75.75 0 0 1 9.5 15.6Z"/></svg>
                                       </a>

                                       <a class="download-link" href="{$downloadHref}" download="{$downloadAttr}">
                                         <span class="download-link__icon" aria-hidden="true"><svg viewBox="0 0 24 24" role="presentation"><path fill="currentColor" d="M12 3.25a.75.75 0 0 1 .75.75v8.19l2.22-2.22a.75.75 0 1 1 1.06 1.06l-3.5 3.5a.75.75 0 0 1-1.06 0l-3.5-3.5a.75.75 0 1 1 1.06-1.06l2.22 2.22V4a.75.75 0 0 1 .75-.75Zm-5.25 13a.75.75 0 0 0 0 1.5h10.5a.75.75 0 0 0 0-1.5Z"/></svg></span>
                                         <span>Download</span>
                                       </a>
                                     </div>
                                     <div id="dummyspan_{$index}" class="dummyspan" aria-live="polite"></div>
                                   </td>
                                   <td class="record-cell record-cell--other">{$otherpartyEsc}</td>
                                   <td class="record-cell record-cell--datetime">{$dateEsc}</td>
                                   <td class="record-cell record-cell--group"><span class="record-pill record-pill--group">{$serviceEsc}</span></td>
                                   <td class="record-cell record-cell--call"><span class="record-pill record-pill--id">{$callEsc}</span></td>
                                   <td class="record-cell record-cell--description">{$descriptionEsc}</td>
                                  </tr>
HTML;
                }


                function get_directories($user,$value_full)
                {
                        $scope = (isset($_POST['scope']) && $_POST['scope'] === 'recent') ? 'recent' : 'all';
                        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
                        if ($page < 1) {
                                $page = 1;
                        }

                        $perPage = 20;
                        $recentCutoff = strtotime('-14 days');

                        $agentAttr = htmlspecialchars($user, ENT_QUOTES, 'UTF-8');
                        $directoryAttr = htmlspecialchars($value_full, ENT_QUOTES, 'UTF-8');
                        $scopeAttr = htmlspecialchars($scope, ENT_QUOTES, 'UTF-8');

                        $collection = $this->fetchIndexedRecordings($value_full, $scope, $page, $perPage);

                        if ($collection === null) {
                                $print = '<div class="recording-panel recording-panel--error" data-agent="' . $agentAttr . '" data-directory="' . $directoryAttr . '" data-scope="' . $scopeAttr . '">';
                                $print .= '<div class="recording-panel__empty">Recording index unavailable. Please run the indexer and try again.</div>';
                                $print .= '</div>';
                                echo $print;
                                return;
                        }
                        $pageRecords = $collection['records'];
                        $totalRecords = $collection['total'];

                        $print = '<div class="recording-panel" data-agent="' . $agentAttr . '" data-directory="' . $directoryAttr . '" data-scope="' . $scopeAttr . '">';
                        $print .= '<div class="recording-panel__controls">';

                        if ($scope === 'recent') {
                                $print .= '<h3 class="recording-panel__title">Recent recordings (last 14 days)</h3>';
                                $print .= '<button type="button" class="recording-panel__toggle" data-role="show-all" data-scope="all" data-agent="' . $agentAttr . '" data-directory="' . $directoryAttr . '">View all recordings</button>';
                        } else {
                                $print .= '<h3 class="recording-panel__title">All recordings</h3>';
                                $print .= '<button type="button" class="recording-panel__toggle" data-role="show-recent" data-scope="recent" data-agent="' . $agentAttr . '" data-directory="' . $directoryAttr . '">Show last 14 days</button>';
                        }

                        $print .= '</div>';

                        if ($totalRecords === 0) {
                                $this->logMessage('info', 'No recordings available for agent', array('agent' => $user, 'directory' => $value_full, 'scope' => $scope, 'page' => $page));
                                if ($scope === 'recent') {
                                        $print .= '<div class="recording-panel__empty">No recordings found in the last 14 days.</div>';
                                } else {
                                        $print .= '<div class="recording-panel__empty">No recordings available.</div>';
                                }

                                $print .= '</div>';
                                echo $print;
                                return;
                        }

                        $totalPages = (int) ceil($totalRecords / $perPage);

                        if ($totalPages < 1) {
                                $totalPages = 1;
                        }

                        if ($page > $totalPages) {
                                $this->logMessage('debug', 'Adjusted page beyond total pages', array('agent' => $user, 'directory' => $value_full, 'requested_page' => $page, 'total_pages' => $totalPages, 'new_page' => $totalPages));
                                $page = $totalPages;
                                $collection = $this->fetchIndexedRecordings($value_full, $scope, $page, $perPage);
                                $pageRecords = $collection['records'];
                        }

                        $offset = ($page - 1) * $perPage;

                        $rangeStart = $offset + 1;
                        $rangeEnd = $offset + count($pageRecords);

                        $print .= '<p class="recording-panel__meta">Showing ' . $rangeStart . '&ndash;' . $rangeEnd . ' of ' . $totalRecords . ' recordings</p>';
                        $print .= '<table class="record-table record-table--detail">';

                        $print .= '<colgroup><col class="record-col record-col--actions"><col class="record-col record-col--other"><col class="record-col record-col--datetime"><col class="record-col record-col--group"><col class="record-col record-col--call"><col class="record-col record-col--description"></colgroup>';

                        foreach ($pageRecords as $index => $record) {
                                $rowIndex = $index + 1;
                                $print .= $this->renderRecordingRow(
                                        $rowIndex,
                                        $record['segments'],
                                        $record['downloadName'],
                                        $record['otherparty'],
                                        $record['datetime'],
                                        $record['servicegroup'],
                                        $record['callId'],
                                        $record['description']
                                );
                        }

                        $print .= '</table>';

                        if ($totalPages > 1) {
                                $print .= '<nav class="pagination" aria-label="Recordings pagination">';

                                if ($page > 1) {
                                        $prev = $page - 1;
                                        $print .= '<button type="button" class="pagination__btn" data-role="page" data-page="' . $prev . '" data-scope="' . $scopeAttr . '" data-agent="' . $agentAttr . '" data-directory="' . $directoryAttr . '">Previous</button>';
                                } else {
                                        $print .= '<span class="pagination__placeholder"></span>';
                                }

                                $print .= '<span class="pagination__status">Page ' . $page . ' of ' . $totalPages . '</span>';

                                if ($page < $totalPages) {
                                        $next = $page + 1;
                                        $print .= '<button type="button" class="pagination__btn" data-role="page" data-page="' . $next . '" data-scope="' . $scopeAttr . '" data-agent="' . $agentAttr . '" data-directory="' . $directoryAttr . '">Next</button>';
                                } else {
                                        $print .= '<span class="pagination__placeholder"></span>';
                                }

                                $print .= '</nav>';
                        }

                        $print .= '</div>';

                        echo $print;


                }

		
	}
?>
