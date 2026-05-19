<?php

/**
 * @package Horde
 */
class Horde_Block_Time extends Horde_Core_Block
{
    /**
     */
    public $updateable = true;

    /**
     */
    public function __construct($app, $params = [])
    {
        parent::__construct($app, $params);

        $this->_name = _("Current Time");
    }

    /**
     */
    protected function _params()
    {
        return [
            'time' => [
                'type' => 'enum',
                'name' => _("Time format"),
                'default' => '24-hour',
                'values' => [
                    '24-hour' => _("24 Hour Format"),
                    '12-hour' => _("12 Hour Format"),
                ],
            ],
        ];
    }

    /**
     */
    protected function _content()
    {
        if (empty($this->_params['time'])) {
            $this->_params['time'] = '24-hour';
        }

        // Set the timezone variable, if available.
        $GLOBALS['registry']->setTimeZone();

        $html = '<div style="font-size:200%; font-weight:bold; text-align:center">'
            . Horde\Date\Format::formatDate(time(), $GLOBALS['prefs']->getValue('date_format'), $GLOBALS['language'] ?? 'en_US') . ' ';
        if ($this->_params['time'] == '24-hour') {
            $html .= date('H:i');
        } else {
            $html .= date('g:i A');
        }
        return $html . '</div>';
    }

}
