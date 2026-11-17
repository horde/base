<?php
/**
 * Responsive login page.
 *
 * Included by login.php, in its scope. A theme can replace this file by
 * shipping the same path in its horde theme directory
 * (themes/<theme>/templates/login/login.html.php); see login.php.
 *
 * A theme that overrides this template takes over its maintenance: it must
 * keep the form fields and hidden inputs below (login_post, url,
 * anchor_string, app), the OAuth forms and the scripts, and follow changes
 * made to this file.
 *
 * Available variables:
 *   $webroot            string  Horde web root.
 *   $themesUri          string  Themes base URI.
 *   $theme              string  Current theme name.
 *   $cssUrls            array   Stylesheet URLs (default, then theme).
 *   $jsUrls             array   Script URLs.
 *   $errorHtml          string  Error/logout message markup, or ''.
 *   $showPasswordLogin  bool    Whether the password form is shown.
 *   $formFields         string  Authentication fields markup.
 *   $languageSelector   string  Language selector markup, or ''.
 *   $modeSelectorField  string  View mode selector markup, or ''.
 *   $passwordResetLink  string  Password reset link markup, or ''.
 *   $motdHtml           string  Message of the day (trusted admin HTML), or ''.
 *   $oauthProviders     array   Enabled OAuth login providers.
 *   $oauthLoginBaseUrl  string  OAuth login base URL.
 *   $url, $anchor_string, $app  string  Request parameters to pass on.
 *   $vars               Horde_Variables  Request variables.
 *   $escape             callable  HTML escaping function.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Horde</title>
<?php foreach ($cssUrls as $cssUrl): ?>
    <link rel="stylesheet" href="<?php echo $escape($cssUrl) ?>">
<?php endforeach; ?>
</head>
<body class="horde-responsive login-page">
    <div class="login-card card">
        <div class="login-logo">
            <img src="<?php echo $escape($themesUri) ?>/<?php echo $escape($theme) ?>/graphics/logo.png" alt="Horde">
        </div>

        <div class="card-header">
	    <h1 class="card-title"><?php echo _("Welcome to Horde") ?></h1>
	    <p class="card-subtitle"><?php echo _("Sign in to continue") ?></p>
        </div>

        <?php echo $errorHtml ?>

        <form method="post" action="<?php echo $escape($webroot) ?>/login.php" id="horde_login">
            <input type="hidden" name="login_post" value="1" />
            <input type="hidden" id="login_post" value="0" />
            <input type="hidden" name="url" value="<?php echo $escape($url) ?>">
            <input type="hidden" id="anchor_string" name="anchor_string" value="<?php echo $escape($anchor_string) ?>">
            <input type="hidden" name="app" value="<?php echo $escape($app) ?>">

            <?php if ($showPasswordLogin): ?>
                <?php echo $formFields ?>
            <?php endif ?>
            <?php echo $languageSelector ?>
	    <?php echo $modeSelectorField ?>

            <?php if ($showPasswordLogin): ?>
                <div class="form-group">
                    <button type="submit" id="login-button" class="btn btn-primary btn-block"><?php echo _("Sign In") ?></button>
                </div>
            <?php endif ?>

            <?php echo $passwordResetLink ?>
        </form>

    <?php if ($motdHtml !== ''): ?>
        <div class="login-motd"><?php echo $motdHtml ?></div>
    <?php endif; ?>


<?php if (!empty($oauthProviders)): ?>
    <?php if ($showPasswordLogin): ?>
        <div class="login-separator" style="display:flex;align-items:center;margin:20px 0">
            <hr style="flex:1;border:none;border-top:1px solid #ddd">
            <span style="padding:0 12px;color:#888;font-size:0.9em"><?php echo _("or") ?></span>
            <hr style="flex:1;border:none;border-top:1px solid #ddd">
        </div>
    <?php endif ?>
        <div class="oauth-buttons">
<?php foreach ($oauthProviders as $provider): ?>
            <form method="post" action="<?php echo $escape($oauthLoginBaseUrl) ?>/<?php echo $escape($provider['provider_id']) ?>">
                <input type="hidden" name="url" value="<?php echo $escape($url) ?>">
                <button type="submit" class="btn btn-block" style="margin-bottom:8px;padding:10px 16px;border:1px solid #ccc;border-radius:4px;cursor:pointer;font-size:1em;width:100%<?php if (!empty($provider['display_color'])): ?>;background-color:<?php echo $escape($provider['display_color']) ?>;color:#fff;border-color:<?php echo $escape($provider['display_color']) ?><?php endif ?>">
                    <?php echo $escape($provider['display_label'] ?: $provider['name']) ?>
                </button>
            </form>
<?php endforeach ?>
        </div>
<?php endif ?>
    </div>

<script>
// Pass data to external JavaScript
window.HordeLoginPreSelected = <?php echo json_encode($vars->get('horde_select_view', $_COOKIE['default_horde_view'] ?? 'auto'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.HordeLoginStrings = {
    username: <?php echo json_encode(_("Please enter a username."), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    password: <?php echo json_encode(_("Please enter a password."), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    capsLock: <?php echo json_encode(_("Caps Lock is on"), JSON_HEX_TAG | JSON_HEX_AMP) ?>
};
</script>
<?php foreach ($jsUrls as $jsUrl): ?>
    <script src="<?php echo $escape($jsUrl) ?>"></script>
<?php endforeach; ?>
</body>
</html>
