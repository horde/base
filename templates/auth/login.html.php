<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Horde</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($themesUri) ?>/default/responsive.css">
</head>
<body class="login-page">
    <div class="login-card card">
        <div class="login-logo">
            <img src="<?php echo htmlspecialchars($themesUri) ?>/default/graphics/logo.png" alt="Horde">
        </div>

        <div class="card-header">
            <h1 class="card-title">Welcome to Horde</h1>
            <p class="card-subtitle">Sign in to continue</p>
        </div>

        <?php echo $errorHtml ?>

        <form method="post" action="<?php echo htmlspecialchars($webroot) ?>/auth/login" id="login-form">
            <input type="hidden" name="url" value="<?php echo htmlspecialchars($vars->url ?? '') ?>">
            <input type="hidden" name="anchor_string" value="<?php echo htmlspecialchars($vars->anchor_string ?? '') ?>">
            <input type="hidden" name="app" value="<?php echo htmlspecialchars($vars->app ?? '') ?>">
            <input type="hidden" name="new_lang" value="<?php echo htmlspecialchars($vars->new_lang ?? '') ?>">

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

    <script src="<?php echo htmlspecialchars($webroot) ?>/js/login-form.js"></script>
</body>
</html>
