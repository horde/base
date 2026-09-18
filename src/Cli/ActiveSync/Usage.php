<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl LGPL-2
 * @package   Horde
 */

namespace Horde\Horde\Cli\ActiveSync;

final class Usage
{
    public static function text(): string
    {
        return implode(PHP_EOL, [
            'Usage:',
            '  horde-activesync summary [--format=table|json] [--active-within=SEC] [--user=USER] [--device=ID] [--health=ok|warn|critical] [--stuck-only]',
            '  horde-activesync show USER DEVICE [--format=table|json]',
            '',
            'Exit codes (summary): 0 no critical, 1 >=1 critical device, 2 ActiveSync unavailable/config error',
        ]);
    }
}
