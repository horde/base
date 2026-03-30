<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit;

use Horde\Test\TestCase;
use Horde_Test;

/**
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @license    http://www.horde.org/licenses/gpl GPL
 * @category   Horde
 * @package    Horde
 * @subpackage UnitTests
 * @coversNothing
 */
class HordeTestTest extends TestCase
{
    public function setUp(): void {}

    public function testTestClass()
    {
        $test = new Horde_Test();
        $this->assertInstanceOf('Horde_Test', $test);
    }

    public function tearDown(): void {}
}
