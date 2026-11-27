<?php

/**
 * Block for displaying the current user's Facebook stream, with the ability to
 * filter it using the same Facebook filters available on facebook.com.  Also
 * provides ability to update the current user's status.
 *
 * Copyright 2009-2026 Horde LLC (http://www.horde.org/)
 *
 * @author  Michael J. Rubinsky <mrubinsk@horde.org>
 * @package Horde
 *
 * @deprecated Compatibility stub. The original block used the defunct Facebook
 *             API and its OAuth flow, which no longer works. The block is kept
 *             so existing portal layouts that reference it still render, but it
 *             loads no JavaScript and makes no API calls. A replacement based
 *             on a current API and Horde OAuth2 is needed.
 */
class Horde_Block_FbStream extends Horde_Core_Block
{
    /**
     */
    public function __construct($app, $params = [])
    {
        parent::__construct($app, $params);
        $this->_name = _("My Facebook Stream");
    }

    /**
     */
    protected function _params()
    {
        return [
            'filter' => [
                'type' => 'enum',
                'name' => _("Filter"),
                'default' => 'nf',
                'values' => [],
            ],
            'count' => [
                'type' => 'int',
                'name' => _("Maximum number of entries to display"),
                'default' => '20',
            ],
            'height' => [
                'name' => _("Height of stream content (width automatically adjusts to block)"),
                'type' => 'int',
                'default' => 250,
            ],
        ];
    }

    /**
     */
    protected function _title()
    {
        return $this->getName();
    }

    /**
     * The content to go in this block.
     *
     * @return string   The content.
     */
    protected function _content()
    {
        return _("This block was built for a dead Facebook API and will only work again once Horde integrates with a new, suitable API.");
    }

}
