<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author  Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */

namespace Horde\Horde\Config;

use Horde_Config_Form;

class Form extends Horde_Config_Form
{
    public function __construct(&$vars, $app = 'horde', $fillvars = false)
    {
        parent::__construct($vars, $app, $fillvars);
    }

    protected function _buildVariables($config, $prefix = '')
    {
        if ($prefix === '' && !class_exists('Horde_ActiveSync')) {
            $config = $this->_filterActiveSyncConfig($config);
        }

        parent::_buildVariables($config, $prefix);
    }

    /**
     * Remove ActiveSync configuration from the top-level config tree when
     * the optional horde/activesync package is not installed.
     */
    protected function _filterActiveSyncConfig(array $config): array
    {
        $filtered = [];

        foreach ($config as $name => $configitem) {
            if ($name === 'activesync') {
                continue;
            }

            if (is_array($configitem) && ($configitem['tab'] ?? null) === 'activesync') {
                continue;
            }

            $filtered[$name] = $configitem;
        }

        return $filtered;
    }
}
