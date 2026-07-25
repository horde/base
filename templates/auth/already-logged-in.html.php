<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo _("Already Logged In") ?> - Horde</title>

    <?php foreach ($this->cssUrls as $cssUrl): ?>
    <link rel="stylesheet" href="<?php echo $this->escape($cssUrl) ?>">
    <?php endforeach; ?>
</head>
<body>
    <div class="login-page">
        <div class="login-card card">
            <div class="login-logo">
                <img src="<?php echo $this->escape($this->themesUri) ?>/<?php echo $this->escape($this->theme) ?>/graphics/logo.png" alt="Horde">
            </div>

            <div class="card-header">
                <h1 class="card-title"><?php echo _("Already Logged In") ?></h1>
            </div>

            <div class="alert alert-info">
                <?php echo _("You are currently logged in as") ?> <strong><?php echo $this->escape($this->username) ?></strong>.
            </div>

            <div style="margin-top: 2rem;">
                <a href="<?php echo $this->escape($this->portalUrl) ?>" class="btn btn-primary btn-block">
                    <?php echo _("Continue to Portal") ?>
                </a>
            </div>

            <div style="margin-top: 1rem;">
                <a href="<?php echo $this->escape($this->logoutUrl) ?>" class="btn btn-secondary btn-block">
                    <?php echo _("Logout") ?>
                </a>
            </div>

            <div class="login-footer">
                <p>&copy; <?php echo date('Y') ?> The Horde Project</p>
            </div>
        </div>
    </div>

    <?php foreach ($this->jsUrls as $jsUrl): ?>
    <script src="<?php echo $this->escape($jsUrl) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
