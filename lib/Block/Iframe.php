<?php

/**
 * @package Horde
 */
class Horde_Block_Iframe extends Horde_Core_Block
{
    /**
     */
    public function __construct($app, $params = [])
    {
        parent::__construct($app, $params);

        $this->_name = _("View an external web page");
    }

    /**
     */
    protected function _params()
    {
        return [
            'iframe' => [
                'type' => 'text',
                'name' => _("URL"),
                'default' => '',
            ],
            'title' => [
                'type' => 'text',
                'name' => _("Title"),
            ],
            'height' => [
                'type' => 'enum',
                'name' => _("Height"),
                'default' => '600',
                'values' => [
                    '480' => _("Small"),
                    '600' => _("Medium"),
                    '768' => _("Large"),
                    '1024' => _("Extra Large"),
                ],
            ],
        ];
    }

    /**
     */
    protected function _title()
    {
        $title = !empty($this->_params['title'])
            ? $this->_params['title']
            : $this->_params['iframe'];
        $url = new Horde_Url(Horde::externalUrl($this->_params['iframe']));

        return htmlspecialchars($title)
            . $url->link(['target' => '_blank'])
            . Horde_Themes_Image::tag('external.png', [
                'attr' => [
                    'style' => 'vertical-align:middle;padding-left:.3em',
                ],
            ]) . '</a>';
    }

    /**
     */
    protected function _content()
    {
        global $browser;

        if (!$browser->hasFeature('iframes')) {
            return _("Your browser does not support this feature.");
        }

        if (empty($this->_params['height'])) {
            $height = ($browser->isBrowser('msie') || $browser->isBrowser('webkit'))
                ? ''
                : ' height="100%"';
        } else {
            $height = ' height="' . htmlspecialchars($this->_params['height']) . '"';
        }

        return '<iframe src="' . htmlspecialchars($this->_params['iframe']) . '" width="100%"' . $height . ' marginheight="0" scrolling="auto" frameborder="0"></iframe>';
    }

}
