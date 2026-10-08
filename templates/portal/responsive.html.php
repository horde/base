<!DOCTYPE html>
<html lang="<?php echo $this->escape($language) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $this->escape(_("Portal")) ?> - Horde</title>
<?php foreach ($cssUrls as $cssUrl): ?>
    <link rel="stylesheet" href="<?php echo $this->escape($cssUrl) ?>">
<?php endforeach; ?>
</head>
<body class="horde-responsive portal-page">
    <header class="portal-header">
        <div class="container">
            <div class="portal-brand">
                <img src="<?php echo $this->escape($themesUri) ?>/<?php echo $this->escape($theme) ?>/graphics/logo.png" alt="Horde" class="portal-logo">
            </div>
            <div class="portal-user">
                <span class="user-name"><?php echo $this->escape($fullname) ?></span>
                <a href="<?php echo $this->escape($logoutUrl) ?>" class="btn btn-secondary btn-sm"><?php echo $this->escape(_("Log out")) ?></a>
            </div>
        </div>
    </header>

    <main class="portal-main">
        <div class="container">
            <h1 class="portal-title"><?php echo $this->escape(_("Your Applications")) ?></h1>

            <div class="app-grid">
<?php foreach ($apps as $app): ?>
                <a href="<?php echo $this->escape($app['url']) ?>" class="app-card">
                    <div class="app-icon">
                        <img src="<?php echo $this->escape($app['icon']) ?>" alt="<?php echo $this->escape($app['name']) ?>">
                    </div>
                    <div class="app-name"><?php echo $this->escape($app['name']) ?></div>
                </a>
<?php endforeach; ?>
            </div>
        </div>
    </main>

    <footer class="portal-footer">
        <div class="container">
            <p>&copy; 2026 <a href="https://www.horde.org/">The Horde Project</a></p>
        </div>
    </footer>

<?php foreach ($jsUrls as $jsUrl): ?>
    <script src="<?php echo $this->escape($jsUrl) ?>"></script>
<?php endforeach; ?>
</body>
</html>
