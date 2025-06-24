<?php

namespace Horde\Horde;

use Horde_Registry;
use Horde_Variables;

/**
 * Factor out logic from the horde login script.
 */
class Login
{
    public readonly bool $secondFactorSupported;
    public function __construct(private Horde_Registry $registry, private Horde_Variables $vars)
    {
        if ($registry->hasMethod('secondfactor/isEnabled')) {
            $this->secondFactorSupported = $registry->call('secondfactor/isEnabled');
        } else {
            $this->secondFactorSupported = false;
        }
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
        if ($this->secondFactorSupported) {
            $loginparams['horde_secondfactor'] = [
                'label' => _("Second Factor"),
                'type' => 'password',
                // 'value' => $this->vars->horde_secondfactor,
                'extra' => [ 'autocomplete' => 'off' ],
            ];
        }
        return $loginparams;
    }

    public function handleUiLogin(): void {}
}
