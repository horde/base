<?php

/**
 * Tests for Horde configuration form ActiveSync tab visibility.
 *
 * @author   Torben Dannhauer <torben@dannhauer.de>
 * @license  http://www.horde.org/licenses/lgpl LGPL
 * @copyright 2026 The Horde Project (http://www.horde.org/)
 * @package  Horde
 */

use Horde\Horde\Config\Form;
use PHPUnit\Framework\TestCase;

class Horde_Unit_Config_FormTest extends TestCase
{
    public function testFilterActiveSyncConfigRemovesTabAndSection()
    {
        $form = $this->_createFormWithoutConstructor();
        $method = (new ReflectionClass($form))->getMethod('_filterActiveSyncConfig');
        $method->setAccessible(true);

        $config = [
            'general' => ['foo' => 'bar'],
            'uniqid1' => ['tab' => 'activesync', 'desc' => 'ActiveSync'],
            'activesync' => ['enabled' => ['_type' => 'boolean']],
        ];

        $filtered = $method->invoke($form, $config);

        $this->assertSame(['general' => ['foo' => 'bar']], $filtered);
    }

    public function testFilterActiveSyncConfigLeavesOtherTabsUntouched()
    {
        $form = $this->_createFormWithoutConstructor();
        $method = (new ReflectionClass($form))->getMethod('_filterActiveSyncConfig');
        $method->setAccessible(true);

        $config = [
            'uniqid1' => ['tab' => 'general', 'desc' => 'General'],
            'general' => ['foo' => 'bar'],
            'uniqid2' => ['tab' => 'db', 'desc' => 'Database'],
            'sql' => ['phptype' => ['_type' => 'enum']],
        ];

        $filtered = $method->invoke($form, $config);

        $this->assertSame($config, $filtered);
    }

    private function _createFormWithoutConstructor(): Form
    {
        $ref = new ReflectionClass(Form::class);
        return $ref->newInstanceWithoutConstructor();
    }
}
