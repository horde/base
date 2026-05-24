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
<body class="horde-responsive login-page">
    <div class="login-card card">
        <div class="login-logo">
            <img src="<?php echo $this->escape($themesUri) ?>/<?php echo $this->escape($theme) ?>/graphics/logo.png" alt="Horde">
        </div>

        <div class="card-header">
            <h1 class="card-title">Welcome to Horde</h1>
            <p class="card-subtitle">Sign in to continue</p>
        </div>

        <?php echo $errorHtml ?>

        <form method="post" action="<?php echo $this->escape($formActionUrl) ?>" id="login-form">
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

<?php if (!empty($oauthProviders)): ?>
        <div class="login-separator" style="display:flex;align-items:center;margin:20px 0">
            <hr style="flex:1;border:none;border-top:1px solid #ddd">
            <span style="padding:0 12px;color:#888;font-size:0.9em"><?php echo _("or") ?></span>
            <hr style="flex:1;border:none;border-top:1px solid #ddd">
        </div>
        <div class="oauth-buttons">
<?php foreach ($oauthProviders as $provider): ?>
            <form method="post" action="<?php echo $this->escape($oauthLoginBaseUrl) ?>/<?php echo $this->escape($provider['provider_id']) ?>">
                <input type="hidden" name="url" value="<?php echo $this->escape($url) ?>">
                <button type="submit" class="btn btn-block" style="margin-bottom:8px;padding:10px 16px;border:1px solid #ccc;border-radius:4px;cursor:pointer;font-size:1em;width:100%<?php if (!empty($provider['display_color'])): ?>;background-color:<?php echo $this->escape($provider['display_color']) ?>;color:#fff;border-color:<?php echo $this->escape($provider['display_color']) ?><?php endif ?>">
                    <?php echo $this->escape($provider['display_label'] ?: $provider['name']) ?>
                </button>
            </form>
<?php endforeach ?>
        </div>
<?php endif ?>

        <div class="login-footer">
            <p>&copy; 2026 <a href="https://www.horde.org/">Horde LLC</a></p>
        </div>
    </div>

<?php if (!empty($jsCode)): ?>
<script>
<?php foreach ($jsCode as $varName => $varValue): ?>
var <?php echo $varName ?> = <?php echo json_encode($varValue, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
<?php endforeach; ?>
</script>
<?php endif; ?>
<?php foreach ($jsFiles as $jsFile): ?>
<?php if (is_array($jsFile)): ?>
    <script src="<?php echo $this->escape($jsFile[0]) ?>"></script>
<?php else: ?>
    <script src="<?php echo $this->escape($jsFile) ?>"></script>
<?php endif; ?>
<?php endforeach; ?>
<?php foreach ($jsUrls as $jsUrl): ?>
    <script src="<?php echo $this->escape($jsUrl) ?>"></script>
<?php endforeach; ?>
</body>
</html>
