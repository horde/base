<?php
/**
 * Shared responsive login template.
 *
 * Used by both login.php and the /auth/login PSR-15 route
 * (ResponsiveLoginController via ResponsiveTemplateView).
 *
 * Expected variables (all must be set by the caller):
 *
 *   $escape            callable(string): string  — HTML-escape helper
 *   $cssUrls           string[]                  — stylesheet URLs
 *   $jsUrls            string[]                  — script URLs (loaded at end of body)
 *   $jsCode            array                     — inline JS vars (key→value, emitted as JSON)
 *   $jsFiles           array                     — legacy Horde JS files (string or [url])
 *   $themesUri         string                    — base URI for theme assets
 *   $theme             string                    — active theme name
 *   $errorHtml         string                    — pre-rendered error/info alert div (or empty)
 *   $formActionUrl     string                    — form POST target
 *   $formId            string                    — HTML id for the <form> element
 *   $extraHiddenFields array                     — [{name, value, ?id}] extra <input type=hidden>
 *   $formFields        string                    — pre-rendered credential fields HTML
 *   $modeSelector      string                    — pre-rendered mode selector HTML
 *   $languageSelector  string                    — pre-rendered language selector HTML
 *   $passwordResetLink string                    — pre-rendered forgot-password link (or empty)
 *   $showPasswordLogin bool                      — whether to show user/pass fields + button
 *   $oauthProviders    array                     — OAuth provider config rows
 *   $oauthLoginBaseUrl string                    — base URL for OAuth login forms
 *   $app               string                    — hidden field: target application
 *   $url               string                    — hidden field: post-login redirect
 *   $anchor_string     string                    — hidden field: URL anchor
 *   $motdHtml          string                    — Message-of-the-Day HTML (trusted, raw)
 *   $showFooter        bool                      — show the "Powered by Horde" footer
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */

// Default optional flags if caller didn't set them
$motdHtml          = $motdHtml ?? '';
$showFooter        = $showFooter ?? false;
$extraHiddenFields = $extraHiddenFields ?? [];
$formId            = $formId ?? 'login-form';
$jsCode            = $jsCode ?? [];
$jsFiles           = $jsFiles ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo _("Login") ?> - Horde</title>
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

        <form method="post" action="<?php echo $escape($formActionUrl) ?>" id="<?php echo $escape($formId) ?>">
<?php foreach ($extraHiddenFields as $hidden): ?>
            <input type="hidden"<?php if (!empty($hidden['id'])): ?> id="<?php echo $escape($hidden['id']) ?>"<?php endif ?> name="<?php echo $escape($hidden['name']) ?>" value="<?php echo $escape($hidden['value']) ?>">
<?php endforeach; ?>
            <input type="hidden" name="url" value="<?php echo $escape($url) ?>">
            <input type="hidden" name="anchor_string" value="<?php echo $escape($anchor_string) ?>">
            <input type="hidden" name="app" value="<?php echo $escape($app) ?>">

            <?php if ($showPasswordLogin): ?>
                <?php echo $formFields ?>
            <?php endif ?>
            <?php echo $languageSelector ?>
            <?php echo $modeSelector ?>

            <?php if ($showPasswordLogin): ?>
                <div class="form-group">
                    <button type="submit" id="login-button" class="btn btn-primary btn-block">
                        <?php echo _("Sign In") ?>
                    </button>
                </div>
                <?php echo $passwordResetLink ?>
            <?php endif ?>
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

<?php if ($showFooter): ?>
        <div class="login-footer">
            <p>&copy; 2026 <a href="https://www.horde.org/">Horde LLC</a></p>
        </div>
<?php endif ?>
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
    <script src="<?php echo $escape($jsFile[0]) ?>"></script>
<?php else: ?>
    <script src="<?php echo $escape($jsFile) ?>"></script>
<?php endif; ?>
<?php endforeach; ?>
<?php foreach ($jsUrls as $jsUrl): ?>
    <script src="<?php echo $escape($jsUrl) ?>"></script>
<?php endforeach; ?>
</body>
</html>
