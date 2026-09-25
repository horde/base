<?php

/**
 * @package Horde
 */
use Horde\Horde\HordeConfig;

class Horde_Block_Fortune extends Horde_Core_Block
{
    /**
     */
    public $updateable = true;
    private HordeConfig $config;

    /**
     */
    public function __construct($app, $params = [])
    {
        parent::__construct($app, $params);

        $this->config = $GLOBALS['injector']->get(HordeConfig::class);
        $this->enabled = (null !== $this->config->get('fortune.exec_path') && is_executable($this->config->get('fortune.exec_path')));
        $this->_name = _("Random Fortune");
    }

    /**
     */
    protected function _title()
    {
        return _("Fortune");
    }

    /**
     */
    protected function _params()
    {

        $descriptions = [
            'art' => _("Art"),
            'ascii-art' => _("Ascii Art"),
            'bofh-excuses' => _("BOFH Excuses"),
            'computers' => _("Computers"),
            'cookie' => _("Cookie"),
            'definitions' => _("Definitions"),
            'drugs' => _("Drugs"),
            'education' => _("Education"),
            'ethnic' => _("Ethnic"),
            'food' => _("Food"),
            'fortunes' => _("Fortunes"),
            'fortunes2' => _("Fortunes 2"),
            'goedel' => _("Goedel"),
            'humorists' => _("Humorists"),
            'kernelnewbies' => _("Kernel Newbies"),
            'kids' => _("Kids"),
            'law' => _("Law"),
            'limerick' => _("Limerick"),
            'linuxcookie' => _("Linux Cookie"),
            'literature' => _("Literature"),
            'love' => _("Love"),
            'magic' => _("Magic"),
            'medicine' => _("Medicine"),
            'miscellaneous' => _("Miscellaneous"),
            'news' => _("News"),
            'osfortune' => _("Operating System"),
            'people' => _("People"),
            'pets' => _("Pets"),
            'platitudes' => _("Platitudes"),
            'politics' => _("Politics"),
            'riddles' => _("Riddles"),
            'science' => _("Science"),
            'songs-poems' => _("Songs & Poems"),
            'sports' => _("Sports"),
            'startrek' => _("Star Trek"),
            'translate-me' => _("Translations"),
            'wisdom' => _("Wisdom"),
            'work' => _("Work"),
            'zippy' => _("Zippy"),
        ];

        $values = [];

        exec($this->config->get('fortune.exec_path') . ' -f 2>&1', $output, $status);
        if (!$status) {
            for ($i = 1, $ocnt = count($output); $i < $ocnt; ++$i) {
                $fortune = substr($output[$i], strrpos($output[$i], ' ') + 1);
                $values[$fortune] = $descriptions[$fortune]
                    ?? $fortune;
            }
        }

        if (empty($values)) {
            $values = $descriptions;
        }

        asort($values);
        $values = array_merge(['' => _("All")], $values);

        return [
            'offend' => [
                'type' => 'enum',
                'name' => _("Offense filter"),
                'default' => '',
                'values' => [
                    '' => _("No offensive fortunes"),
                    ' -o' => _("Only offensive fortunes"),
                    ' -a' => _("Both"),
                ],
            ],
            'fortune' => [
                'type' => 'multienum',
                'name' => _("Fortune type"),
                'default' => [''],
                'values' => $values,
            ],
        ];
    }

    /**
     */
    protected function _content()
    {
        $cmdLine = $this->config->get('fortune.exec_path')
            . $this->_params['offend']
            . ' ' . implode(' ', $this->_params['fortune']);

        return '<span class="fixed"><small>'
            . nl2br($GLOBALS['injector']->getInstance('Horde_Core_Factory_TextFilter')->filter(shell_exec($cmdLine), ['space2html'], [['encode' => true]]))
            . '</small></span>';
    }

}
