<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */

namespace Horde\Horde\Service;

use Horde;
use Horde_Registry;
use Horde_Url;
use Exception;

/**
 * URL validation and redirect resolution for the login flow.
 *
 * Extracts the redirect safety logic from index.php into a testable service.
 */
class RedirectValidationService
{
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly array $conf,
    ) {}

    /**
     * Validate a redirect URL for safety (anti-phishing, anti-XSS).
     *
     * Checks that the URL's host matches the cookie domain and uses an
     * allowed protocol. Returns the sanitized URL or null if invalid.
     */
    public function validateRedirectUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $req = @parse_url($url);
        if ($req === false) {
            return null;
        }

        // Verify host matches cookie domain
        if (isset($req['host'])) {
            $cookieDomain = $this->conf['cookie']['domain'] ?? '';
            if (!empty($cookieDomain)) {
                $qcookiedom = preg_quote($cookieDomain, '/');
                if (!preg_match('/' . $qcookiedom . '$/', $req['host'])) {
                    return null;
                }
            }
        }

        // Protocol whitelist for fully qualified URLs
        if (isset($req['scheme']) || isset($req['host']) || isset($req['port'])
            || isset($req['user']) || isset($req['pass'])) {
            $allowedProtocols = ['http', 'https'];
            if (empty($req['scheme']) || !in_array($req['scheme'], $allowedProtocols)) {
                return null;
            }
        }

        return $url;
    }

    /**
     * Verify a signed URL using Horde's HMAC mechanism.
     *
     * Returns the URL without signature params if valid, null otherwise.
     */
    public function verifySignedUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $verified = Horde::verifySignedUrl($url);
        return $verified !== false ? $verified : null;
    }

    /**
     * Resolve the post-login landing page.
     *
     * Applies the same logic as index.php: validated URL → initial app →
     * smartmobile portal → initial horde page → portal.
     */
    public function resolveInitialPage(?string $url = null, ?string $anchorString = null): string
    {
        // If a redirect URL was provided and is valid, use it
        if (!empty($url)) {
            $validated = $this->validateRedirectUrl($url);
            if ($validated !== null) {
                return $this->addAnchor($validated, $anchorString);
            }
        }

        // Check initial_application preference
        try {
            $prefs = $GLOBALS['prefs'] ?? null;
            if ($prefs) {
                $initialApp = $prefs->getValue('initial_application');
                if ($initialApp && $initialApp !== 'horde'
                    && $this->registry->hasPermission($initialApp)) {
                    return (string) Horde::url(
                        $this->registry->getInitialPage($initialApp),
                        true,
                    );
                }
            }
        } catch (Exception $e) {
            // Fall through to portal
        }

        // Smartmobile view goes to portal
        if ($this->registry->getView() == Horde_Registry::VIEW_SMARTMOBILE) {
            return (string) $this->registry->getServiceLink('portal');
        }

        // Try initial horde page (avoid loops with index.php/login.php)
        try {
            $initialPage = $this->registry->getInitialPage('horde');
            if ($initialPage && !in_array(basename($initialPage), ['index.php', 'login.php'])) {
                return (string) Horde::url($initialPage, true);
            }
        } catch (Exception $e) {
            // Fall through to portal
        }

        // Fallback to portal
        return (string) $this->registry->getServiceLink('portal');
    }

    /**
     * Attach a fragment anchor to a URL.
     */
    public function addAnchor(string $url, ?string $anchor): string
    {
        if (empty($anchor)) {
            return $url;
        }

        $separator = str_contains($url, '#') ? '' : '#';
        return $url . $separator . $anchor;
    }
}
