<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal - Horde</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($themesUri) ?>/default/responsive.css">
</head>
<body class="portal-page">
    <header class="portal-header">
        <div class="container">
            <div class="portal-brand">
                <img src="<?php echo htmlspecialchars($themesUri) ?>/default/graphics/logo.png" alt="Horde" class="portal-logo">
            </div>
            <div class="portal-user">
                <span class="user-name"><?php echo htmlspecialchars($fullname) ?></span>
                <button id="logout-btn" class="btn btn-secondary btn-sm">Logout</button>
            </div>
        </div>
    </header>

    <main class="portal-main">
        <div class="container">
            <h1 class="portal-title">Your Applications</h1>

            <div class="app-grid">
<?php foreach ($apps as $app): ?>
                <a href="<?php echo htmlspecialchars($app['url']) ?>" class="app-card">
                    <div class="app-icon">
                        <img src="<?php echo htmlspecialchars($app['icon']) ?>" alt="<?php echo htmlspecialchars($app['name']) ?>">
                    </div>
                    <div class="app-name"><?php echo htmlspecialchars($app['name']) ?></div>
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

    <script>
    // Configure Horde Auth
    window.HORDE_AUTH_CONFIG = {
        webroot: '<?php echo htmlspecialchars($webroot, ENT_QUOTES) ?>',
        logoutUrl: '<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES) ?>',
        jwtBootstrap: <?php echo $jwtBootstrapJson ?>
    };
    </script>
    <script src="<?php echo htmlspecialchars($webroot) ?>/js/horde-auth.js"></script>
    <script>
    // Set up logout button handler
    document.getElementById('logout-btn').addEventListener('click', function(e) {
        e.preventDefault();
        window.HordeAuth.logout();
    });
    </script>
</body>
</html>
