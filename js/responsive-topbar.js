/**
 * Responsive Topbar Component
 * Handles hamburger menu toggle behavior
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 */
(function() {
    'use strict';

    const hamburgerBtn = document.querySelector('[data-topbar-toggle]');
    const menu = document.querySelector('[data-topbar-menu]');

    if (!hamburgerBtn || !menu) {
        return; // Component not present
    }

    // Create backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'topbar-backdrop';
    document.body.appendChild(backdrop);

    const closeBtn = menu.querySelector('.topbar-menu-close');

    function openMenu() {
        menu.hidden = false;
        menu.setAttribute('data-visible', '');
        backdrop.setAttribute('data-visible', '');
        hamburgerBtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden'; // Prevent body scroll
    }

    function closeMenu() {
        menu.removeAttribute('data-visible');
        backdrop.removeAttribute('data-visible');
        hamburgerBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';

        // Wait for animation before hiding
        setTimeout(function() {
            if (!menu.hasAttribute('data-visible')) {
                menu.hidden = true;
            }
        }, 300);
    }

    // Toggle on hamburger click
    hamburgerBtn.addEventListener('click', function() {
        if (menu.hasAttribute('data-visible')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    // Close on close button
    closeBtn.addEventListener('click', closeMenu);

    // Close on backdrop click
    backdrop.addEventListener('click', closeMenu);

    // Close on menu link click
    var menuLinks = menu.querySelectorAll('.topbar-menu-link');
    for (var i = 0; i < menuLinks.length; i++) {
        menuLinks[i].addEventListener('click', closeMenu);
    }

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && menu.hasAttribute('data-visible')) {
            closeMenu();
        }
    });
})();
