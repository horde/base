<?php
/**
 * Responsive Topbar Component
 * Mobile-first navigation bar for responsive Horde apps
 *
 * Required variables:
 * - $appName: string - Application name to display
 * - $portalUrl: string - URL to portal
 * - $logoutUrl: string - URL to logout
 * - $userName: string - Logged in username
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 */
?>
<header class="horde-topbar" role="banner">
    <button class="topbar-hamburger"
            aria-label="Menu"
            aria-expanded="false"
            data-topbar-toggle>
        <span class="hamburger-icon">☰</span>
    </button>

    <h1 class="topbar-title"><?= htmlspecialchars($appName) ?></h1>
</header>

<nav class="topbar-menu" hidden data-topbar-menu aria-label="Main menu">
    <div class="topbar-menu-header">
        <button class="topbar-menu-close" aria-label="Close menu">×</button>
    </div>

    <a href="<?= htmlspecialchars($portalUrl) ?>" class="topbar-menu-link topbar-portal">
        <span class="link-text"><?= _("Portal") ?></span>
    </a>

    <a href="<?= htmlspecialchars($logoutUrl) ?>" class="topbar-menu-link topbar-logout">
        <span class="link-text"><?= _("Logout") ?></span>
    </a>

    <div class="topbar-menu-footer">
        <small class="topbar-user"><?= sprintf(_("Logged in as: %s"), htmlspecialchars($userName)) ?></small>
    </div>
</nav>
