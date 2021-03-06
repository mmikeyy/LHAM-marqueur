<?php

/*
 * This file is part of the Phamda library
 *
 * (c) Mikael Pajunen <mikael.pajunen@gmail.com>
 *
 * For the full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 */

namespace Phamda\Tests\Fixtures;

class Calculator
{
    public function addMany(...$arguments)
    {
        $result = 0;

        foreach ($arguments as $value) {
            $result += $value;
        }

        return $result;
    }

    public function addTwo($a, $b)
    {
        return $a + $b;
    }
}
