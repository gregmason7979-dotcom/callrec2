<?php include('config.php'); ?>
<?php
$recordCssPath = __DIR__ . '/../css/record_style.css';
$recordCssVersion = file_exists($recordCssPath) ? filemtime($recordCssPath) : time();

$uiVersionTimestamp = $recordCssVersion;
$uiVersionFiles = array(
    __DIR__ . '/../index.php',
    __DIR__ . '/functions.php',
    __DIR__ . '/../search.php',
    __DIR__ . '/../login.php',
);

foreach ($uiVersionFiles as $versionFile) {
    if (!file_exists($versionFile)) {
        continue;
    }

    $mtime = @filemtime($versionFile);

    if ($mtime === false) {
        continue;
    }

    if ($mtime > $uiVersionTimestamp) {
        $uiVersionTimestamp = $mtime;
    }
}

$uiVersionLabel = date('d M Y H:i', $uiVersionTimestamp);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recorded Calls</title>
<link rel="stylesheet" href="css/record_style.css?v=<?php echo $recordCssVersion; ?>">
</head>
<body>
  <div class="app-shell">
    <header class="app-header">
      <div class="app-header__inner">
        <h1 class="app-header__title">Recorded Calls</h1>
        <p class="app-header__subtitle">Review, monitor, and download conversations.</p>
        <p class="app-header__meta">Last updated <?php echo htmlspecialchars($uiVersionLabel, ENT_QUOTES, 'UTF-8'); ?></p>
        <nav class="app-nav" aria-label="Primary">
          <span class="app-nav__welcome">Welcome</span>
          <span class="app-nav__status"><span class="app-nav__status-dot" aria-hidden="true"></span>Secure Workspace</span>
          <a class="logout-link" href="logout.php">Logout</a>
        </nav>
      </div>
    </header>
    <main class="app-main">
