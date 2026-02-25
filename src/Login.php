<?php

namespace Horde\Horde;

use Horde_Registry;
use Horde_Variables;

/**
 * Factor out logic from the horde login script.
 */
class Login
{
    public readonly int $secondFactorMode;
    public function __construct(private Horde_Registry $registry, private Horde_Variables $vars)
    {
        if ($this->secondFactorApi('isEnabled', false)) {
            if ($this->secondFactorApi('showCode', true)) {
                $mode = 1;
            } else {
                $mode = 2;
            }
        } else {
            $mode = 0;
        }
        $this->secondFactorMode = $mode;
    }

    private function secondFactorApi(string $method, $default)
    {
        $method = 'secondfactor/' . $method;
        if ($this->registry->hasMethod($method)) {
            return $this->registry->call($method);
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
                'type' => 'text',
                'value' => $this->vars->horde_user,
            ],
            'horde_pass' => [
                'label' => _("Password"),
                'type' => 'password',
            ],
        ];
        if ($this->secondFactorMode > 0) {
            $loginparams['horde_secondfactor'] = [
                'label' => _("Second Factor"),
                'type' => $this->secondFactorMode == 1 ? 'text' : 'password',
                'extra' => [ 'autocomplete' => 'one-time-code' ],
            ];
        }
        return $loginparams;
    }

    public function handleUiLogin(): void {}
}
