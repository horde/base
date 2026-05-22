<?php

/**
 * Tests the Nls API contract used by base (Metar block, Sunrise block, prefs).
 *
 * - Horde_Nls::getCountryISO($code) for Metar block
 * - Horde_Nls_Loader::loadCoordinates() for Sunrise block
 * - Horde_Nls::getTimezones() for prefs.php
 * @coversNothing
 */
class Horde_Base_Unit_NlsTest extends PHPUnit\Framework\TestCase
{
    /**
     * Test getCountryISO returns country name for a valid code.
     * Used by Block_Metar to label weather stations by country.
     */
    public function testGetCountryIsoReturnsName()
    {
        $result = Horde_Nls::getCountryISO('US');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test getTimezones returns array with timezone IDs as keys.
     * Used by config/prefs.php to build timezone dropdown.
     */
    public function testGetTimezonesReturnsArray()
    {
        $timezones = Horde_Nls::getTimezones();

        $this->assertIsArray($timezones);
        $this->assertNotEmpty($timezones);
        $this->assertArrayHasKey('America/New_York', $timezones);
        $this->assertArrayHasKey('Europe/London', $timezones);
    }

    /**
     * Test loadCoordinates returns nested array of country => [coord => city].
     * Used by Block_Sunrise to show location picker and resolve city names.
     */
    public function testLoadCoordinatesReturnsNestedArray()
    {
        $coordinates = Horde_Nls_Loader::loadCoordinates();

        $this->assertIsArray($coordinates);
        $this->assertNotEmpty($coordinates);
        $this->assertArrayHasKey('Germany', $coordinates);
        $this->assertIsArray($coordinates['Germany']);
        $this->assertNotEmpty($coordinates['Germany']);

        $firstCity = reset($coordinates['Germany']);
        $this->assertIsString($firstCity);
    }
}
