<?php
// CLI entry point to index recordings into SQL Server.
$root = dirname(__DIR__);
require_once $root . '/includes/config.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script must be run from the command line." . PHP_EOL;
    exit(1);
}

$baseDirectory = maindirectory;

$stats = $model->runRecordingIndexer($baseDirectory);

echo "Recording indexer completed" . PHP_EOL;
echo "Seen: " . $stats['seen'] . PHP_EOL;
echo "Inserted: " . $stats['inserted'] . PHP_EOL;
echo "Updated: " . $stats['updated'] . PHP_EOL;
echo "Deleted: " . $stats['deleted'] . PHP_EOL;
