<?php

/**
 * Defines the AJAX interface for Horde.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */
use Horde\Horde\HordeConfig;

class Horde_Ajax_Application extends Horde_Core_Ajax_Application
{
    /**
     */
    protected function _init()
    {
        $config = $GLOBALS['injector']->get(HordeConfig::class);
        $this->addHandler('Horde_Ajax_Application_Handler');
        // Needed because Core contains Imples
        $this->addHandler('Horde_Core_Ajax_Application_Handler_Imple');

        if (!empty($config->get('twitter.enabled'))) {
            $this->addHandler('Horde_Ajax_Application_TwitterHandler');
        }

        if (!empty($config->get('facebook.enabled'))) {
            $this->addHandler('Horde_Ajax_Application_FacebookHandler');
        }
    }

}
