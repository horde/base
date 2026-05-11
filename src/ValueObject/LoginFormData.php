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

namespace Horde\Horde\ValueObject;

/**
 * Immutable value object containing everything needed to render a login form.
 */
final class LoginFormData
{
    public function __construct(
        public readonly string $formFields,
        public readonly string $languageSelector,
        public readonly string $modeSelector,
        public readonly string $passwordResetLink,
        public readonly string $errorHtml,
        public readonly array $jsCode,
        public readonly array $jsFiles,
        public readonly array $oauthProviders,
        public readonly string $oauthLoginBaseUrl,
        public readonly string $formActionUrl,
        public readonly ?string $alternateLoginUrl,
        public readonly string $app,
        public readonly string $url,
        public readonly string $anchorString,
        public readonly string $themesUri,
        public readonly string $theme,
        public readonly array $cssUrls,
        public readonly array $jsUrls,
    ) {}
}
