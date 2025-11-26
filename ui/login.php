<?php
include('includes/config.php');
if(isset($_SESSION['login'])){
        $model->redirect('index.php');
}
$recordCssPath = __DIR__ . '/css/record_style.css';
$recordCssVersion = file_exists($recordCssPath) ? filemtime($recordCssPath) : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Call Recording Login</title>
        <link rel="stylesheet" href="css/record_style.css?v=<?php echo $recordCssVersion; ?>">
</head>
<body class="auth-body">
        <main class="auth-main">
                <section class="auth-card" aria-labelledby="login-heading">
                        <header class="auth-card__header">
                                <h1 id="login-heading" class="auth-card__title">Secure access</h1>
                                <p class="auth-card__subtitle">Sign in to review recordings, manage downloads, and access the latest activity.</p>
                        </header>
                        <?php if(isset($_SESSION['invalid'])) { ?>
                        <div class="auth-card__alert" role="alert">Invalid credentials. Please try again.</div>
                        <?php unset($_SESSION['invalid']); } ?>
                        <form action="process.php" method="post" accept-charset="utf-8" class="auth-form">
                                <label class="auth-field" for="username">
                                        <span class="auth-field__label">Username</span>
                                        <input class="auth-field__control" type="text" name="username" id="username" autocomplete="username" required>
                                </label>
                                <label class="auth-field" for="password">
                                        <span class="auth-field__label">Password</span>
                                        <input class="auth-field__control" type="password" name="password" id="password" autocomplete="current-password" required>
                                </label>
                                <input type="hidden" name="action" value="Login">
                                <button type="submit" name="submit" class="auth-form__submit">Login</button>
                        </form>
                        <footer class="auth-card__footer">
                                Solidus Call Recording Suite
                        </footer>
                </section>
        </main>
</body>
</html>
