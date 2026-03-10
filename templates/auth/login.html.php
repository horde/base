<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Horde</title>
<?php foreach ($cssUrls as $cssUrl): ?>
    <link rel="stylesheet" href="<?php echo $this->escape($cssUrl) ?>">
<?php endforeach; ?>
</head>
<body class="login-page">
    <div class="login-card card">
        <div class="login-logo">
            <img src="<?php echo $this->escape($themesUri) ?>/<?php echo $this->escape($theme) ?>/graphics/logo.png" alt="Horde">
        </div>

        <div class="card-header">
            <h1 class="card-title">Welcome to Horde</h1>
            <p class="card-subtitle">Sign in to continue</p>
        </div>

        <?php echo $logoutMessageHtml ?>
        <?php echo $errorHtml ?>

        <form method="post" action="<?php echo $this->escape($webroot) ?>/auth/login" id="login-form">
            <input type="hidden" name="url" value="<?php echo $this->escape($url) ?>">
            <input type="hidden" name="anchor_string" value="<?php echo $this->escape($anchor_string) ?>">
            <input type="hidden" name="app" value="<?php echo $this->escape($app) ?>">

            <?php echo $formFields ?>
            <?php echo $languageSelector ?>
            <?php echo $modeSelector ?>

            <button type="submit" class="btn btn-primary btn-block">
                Sign In
            </button>

            <?php echo $passwordResetLink ?>
        </form>

        <div class="login-footer">
            <p>&copy; 2026 <a href="https://www.horde.org/">Horde LLC</a></p>
        </div>
    </div>

<?php foreach ($jsUrls as $jsUrl): ?>
    <script src="<?php echo $this->escape($jsUrl) ?>"></script>
<?php endforeach; ?>
</body>
</html>
