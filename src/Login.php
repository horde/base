<?php

namespace Horde\Horde;

use Horde_Registry;
use Horde_Variables;

/**
 * Factor out logic from the horde login script.
 */
class Login
{
    public const SECOND_FACTOR_DISABLED = 0;
    public const SECOND_FACTOR_SHOWCODE = 1;
    public const SECOND_FACTOR_HIDECODE = 2;

    public readonly int $secondFactorMode;

    public function __construct(
        private Horde_Registry $registry,
        private Horde_Variables $vars
    ) {
        if ($this->secondFactorApi('isEnabled', false)) {
            if ($this->secondFactorApi('showCode', true)) {
                $mode = self::SECOND_FACTOR_SHOWCODE;
            } else {
                $mode = self::SECOND_FACTOR_HIDECODE;
            }
        } else {
            $mode = self::SECOND_FACTOR_DISABLED;
        }
        $this->secondFactorMode = $mode;
    }

    public function secondFactorSupported(): bool
    {
        return $this->secondFactorMode !== self::SECOND_FACTOR_DISABLED;
    }

    public function secondFactorApi(string $method, mixed $default, array $params = []): mixed
    {
        $method = 'secondfactor/' . $method;
        if ($this->registry->hasMethod($method)) {
            return $this->registry->call($method, $params);
        }
        return $default;
    }

    /**
     * Build the list of login parameters to be used in the login form.
     */
    public function buildLoginParams(): array
    {
        $loginparams = [
            'horde_user' => [
                'label' => _("Username"),
                'type'  => 'text',
                'value' => $this->vars->horde_user,
            ],
            'horde_pass' => [
                'label' => _("Password"),
                'type'  => 'password',
            ],
        ];

        if ($this->secondFactorSupported()) {
            $loginparams['horde_secondfactor'] = [
                'label' => _("Second Factor"),
                'type'  => $this->secondFactorMode === self::SECOND_FACTOR_SHOWCODE ? 'text' : 'password',
                'extra' => ['autocomplete' => 'one-time-code'],
            ];
        }

        return $loginparams;
    }

    public function handleUiLogin(): void {}
}
