<?php

/*
 * This file is part of the Phamda library
 *
 * (c) Mikael Pajunen <mikael.pajunen@gmail.com>
 *
 * For the full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 */

namespace Phamda\Tests;

use Phamda\Phamda as P;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the highlighted examples in the readme file and documentation.
 */
class ExampleTest extends TestCase
{
    public function testCurriedExample()
    {
        $isPositive   = function ($x) { return $x > 0; };
        $list         = [5, 7, -3, 19, 0, 2];
        $getPositives = P::filter($isPositive);
        $result       = $getPositives($list); // => [5, 7, 19, 2]

        $this->assertSame([5, 7, 3 => 19, 5 => 2], $result);
    }

    public function testCurriedNativeExample()
    {
        $replaceBad = P::curry('str_replace', 'bad', 'good');
        $dayResult  = $replaceBad('bad day'); // => 'good day'
        $notResult  = $replaceBad('not bad'); // => 'not good'

        $this->assertSame('good day', $dayResult);
        $this->assertSame('not good', $notResult);
    }

    public function testComposeExample()
    {
        $double           = function ($x) { return $x * 2; };
        $addFive          = function ($x) { return $x + 5; };
        $addFiveAndDouble = P::compose($double, $addFive);
        $result           = $addFiveAndDouble(16); // => 42
        // Equivalent to calling $double($addFive(16));

        $this->assertSame(42, $result);
    }

    public function testProductList()
    {
        $products = [
            ['category' => 'QDT', 'weight' => 65.8, 'price' => 293.5, 'number' => 15708],
            ['number' => 59391, 'price' => 366.64, 'category' => 'NVG', 'weight' => 15.5],
            ['category' => 'AWK', 'number' => 89634, 'price' => 341.92, 'weight' => 35],
            ['price' => 271.8, 'weight' => 5.3, 'number' => 38718, 'category' => 'ETW'],
            ['price' => 523.63, 'weight' => 67.9, 'number' => 75905, 'category' => 'YVM'],
            ['price' => 650.31, 'weight' => 3.9, 'category' => 'XPA', 'number' => 46289],
            ['category' => 'WGX', 'weight' => 75.5, 'number' => 26213, 'price' => 471.44],
            ['category' => 'KCF', 'price' => 581.85, 'weight' => 31.9, 'number' => 48160],
        ];

        $formatPrice = P::curry(P::flip('number_format'))(2);
        $process     = P::pipe(
            P::filter(// Only include products that...
                P::pipe(
                    P::prop('weight'), // ... weigh...
                    P::gt(50.0) // ... less than 50.0.
                )
            ),
            P::map(// For each product...
                P::pipe(
                    // ... drop the weight field and fix field order:
                    P::pick(['number', 'category', 'price']),
                    // ... and format the price:
                    P::evolve(['price' => $formatPrice])
                )
            ),
            P::sortBy(// Sort the products by...
                P::prop('number') // ... comparing product numbers.
            )
        );

        $result = $process($products);

        $expected = [
            ['number' => 38718, 'category' => 'ETW', 'price' => '271.80'],
            ['number' => 46289, 'category' => 'XPA', 'price' => '650.31'],
            ['number' => 48160, 'category' => 'KCF', 'price' => '581.85'],
            ['number' => 59391, 'category' => 'NVG', 'price' => '366.64'],
            ['number' => 89634, 'category' => 'AWK', 'price' => '341.92'],
        ];

        $this->assertSame($expected, $result);
    }
}
