<?php

/**
 * A bare-bones twitter client in a Horde block.
 *
 * Copyright 2009-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author  Ben Klang <ben@alkaloid.net>
 * @author  Michael J Rubinsky <mrubinsk@horde.org>
 * @package Horde
 *
 * @deprecated Compatibility stub. The original block used the defunct Twitter
 *             V1 API via OAuth 1.0a, which no longer works. The block is kept
 *             so existing portal layouts that reference it still render, but it
 *             loads no JavaScript and makes no API calls. A replacement based
 *             on a current API and Horde OAuth2 is needed.
 */

class Horde_Block_TwitterTimeline extends Horde_Core_Block
{
    /**
     */
    public function __construct($app, $params = [])
    {
        parent::__construct($app, $params);

        $this->_name = _("Twitter Timeline");
    }

    /**
     */
    protected function _title()
    {
        return $this->getName();
    }

    /**
     */
    protected function _params()
    {
        return [
            'height' => [
                'name' => _("Height of stream content (width automatically adjusts to block)"),
                'type' => 'int',
                'default' => 350,
            ],
            'refresh_rate' => [
                'name' => _("Number of seconds to wait to refresh"),
                'type' => 'int',
                'default' => 300,
            ],
        ];
    }

    /**
     */
    protected function _content()
    {
        return _("This block was built for a dead Twitter API and will only work again once Horde integrates with a new, suitable API.");
    }

}
