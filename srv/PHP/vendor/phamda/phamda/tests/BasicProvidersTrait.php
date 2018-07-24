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
use Phamda\Tests\Fixtures\Adder;
use Phamda\Tests\Fixtures\Calculator;
use Phamda\Tests\Fixtures\ConstructableConcat;
use Phamda\Tests\Fixtures\Counter;
use Phamda\Tests\Fixtures\Test1;
use Phamda\Tests\Fixtures\Test2;

/**
 * Data providers for testing basic functionality.
 */
trait BasicProvidersTrait
{
    public function get_Data()
    {
        return [
            [P::_()],
        ];
    }

    public function getAddData()
    {
        return [
            [42, 15, 27],
            [28, 36, -8],
        ];
    }

    public function getAllData()
    {
        $isPositive = function ($x) { return $x > 0; };

        return [
            [false, $isPositive, [1, 2, 0, -5]],
            [false, $isPositive, [-3, -7, -1, -5]],
            [true, $isPositive, [1, 2, 1, 11]],
        ];
    }

    public function getAllPassData()
    {
        $isSumEven     = function ($x, $y) { return ($x + $y) % 2 === 0; };
        $isSumPositive = function ($x, $y) { return ($x + $y) > 0; };

        return [
            [true, [$isSumEven, $isSumPositive], 15, 27],
            [false, [$isSumEven, $isSumPositive], 15, -25],
            [false, [$isSumEven, $isSumPositive], 15, 8],
            [false, [$isSumEven, $isSumPositive], -3, -4],
        ];
    }

    public function getAlwaysData()
    {
        $a = (object) ['foo' => 'bar'];

        return [
            [1, 1],
            [null, null],
            ['abc', 'abc'],
            [$a, $a],
        ];
    }

    public function getAnyData()
    {
        $isPositive = function ($x) { return $x > 0; };

        return [
            [true, $isPositive, [1, 2, 0, -5]],
            [false, $isPositive, [-3, -7, -1, -5]],
            [true, $isPositive, [1, 2, 1, 11]],
        ];
    }

    public function getAnyPassData()
    {
        $isSumEven     = function ($x, $y) { return ($x + $y) % 2 === 0; };
        $isSumPositive = function ($x, $y) { return ($x + $y) > 0; };

        return [
            [true, [$isSumEven, $isSumPositive], 15, 27],
            [true, [$isSumEven, $isSumPositive], 15, -25],
            [true, [$isSumEven, $isSumPositive], 15, 8],
            [false, [$isSumEven, $isSumPositive], -3, -4],
        ];
    }

    public function getAppendData()
    {
        return [
            [['a', 'b', 'c'], 'c', ['a', 'b']],
            [['c'], 'c', []],
            [['a', 'b', ['d', 'e']], ['d', 'e'], ['a', 'b']],
        ];
    }

    public function getApplyData()
    {
        return [
            [42, 'max', [13, -3, 42, 3]],
            [[1, 2, 3, 4], 'array_merge', [[1, 2], [3], [4]]],
        ];
    }

    public function getAssocData()
    {
        return [
            [['foo' => 1, 'bar' => 3], 'bar', 3, ['foo' => 1]],
            [['foo' => 1, 'bar' => 3], 'bar', 3, ['foo' => 1, 'bar' => 2]],
            [['foo' => null, 'bar' => 7], 'foo', null, ['foo' => 15, 'bar' => 7]],
        ];
    }

    public function getAssocPathData()
    {
        return [
            [['foo' => 1, 'bar' => 3], ['bar'], 3, ['foo' => 1, 'bar' => 2]],
            [['foo' => 1, 'bar' => ['baz' => 4]], ['bar', 'baz'], 4, ['foo' => 1, 'bar' => []]],
        ];
    }

    public function getBinaryData()
    {
        $add3 = function ($a = 0, $b = 0, $c = 0) { return $a + $b + $c; };

        return [
            [42, $add3, 27, 15, 33],
        ];
    }

    public function getBothData()
    {
        $true  = function () { return true; };
        $false = function () { return false; };
        $equal = function ($a, $b) { return $a === $b; };

        return [
            [true, $true, $true],
            [false, $true, $false],
            [false, $false, $true],
            [false, $false, $false],
            [true, $equal, $true, 2, 2],
            [false, $equal, $true, 2, 1],
            [false, $equal, $equal, 2, 1],
        ];
    }

    public function getCastData()
    {
        $values = ['a' => 1, 'b' => 2];

        return [
            [$values, 'array', (object) $values],
            ['3', 'string', 3],
            [4, 'int', 4.55],
        ];
    }

    public function getComparatorData()
    {
        return [
            [-1, P::lt(), 1, 2],
            [0, P::lt(), 1, 1],
            [1, P::lt(), 2, 1],
        ];
    }

    public function getComposeData()
    {
        $square = function ($x) { return $x ** 2; };
        $sum    = function ($x, $y) { return $x + $y; };

        return [
            [256, [$square, $square], 4],
            [64, [$square, $sum], 3, 5],
            [2401, [$square, $square, $sum], 5, 2],
        ];
    }

    public function getConcatData()
    {
        return [
            ['abcd', 'ab', 'cd'],
            ['abc', 'abc', ''],
        ];
    }

    public function getConstructData()
    {
        return [
            ['abc', ConstructableConcat::class, 'a', 'b', 'c'],
            ['abc', ConstructableConcat::class, 'a', 'b', 'c', 'x', 'y', 'z'],
        ];
    }

    public function getConstructNData()
    {
        return [
            ['abc', 3, ConstructableConcat::class, 'a', 'b', 'c'],
            ['abc', 3, ConstructableConcat::class, 'a', 'b', 'c', 'x', 'y', 'z'],
        ];
    }

    public function getContainsData()
    {
        $a = (object) [];
        $b = (object) [];

        return [
            [true, 'a', ['a', 'b', 'c', 'e']],
            [false, 'd', ['a', 'b', 'c', 'e']],
            [true, $a, [$a, $b, $b]],
            [false, $a, [$b, 'a']],
        ];
    }

    public function getCurryData()
    {
        $sum = function ($a, $b, $c, $d) { return $a + $b + $c + $d; };

        return [
            [1234, $sum, 1000, 200, 30, 4],
            [1234, $sum, 1000, 200, 30, 4, 5],
            [true, P::eq(), 5, 5],
            [false, P::eq(), 5, 7],
            [true, [P::class, 'eq'], 5, 5],
            [42, new Adder(), 23, 19],
        ];
    }

    public function getCurryNData()
    {
        $sum = function ($a, $b, $c, $d) { return $a + $b + $c + $d; };

        return [
            [1234, 4, $sum, 1000, 200, 30, 4],
            [1234, 4, $sum, 1000, 200, 30, 4, 5],
            [true, 2, P::eq(), 5, 5],
            [false, 2, P::eq(), 5, 7],
            [true, 2, [P::class, 'eq'], 5, 5],
            [42, 2, new Adder(), 23, 19],
        ];
    }

    public function getDecData()
    {
        return [
            [42, 43],
            [-15, -14],
        ];
    }

    public function getDefaultToData()
    {
        return [
            [15, 22, 15],
            [42, 42, null],
            [false, 15, false],
            [null, null, null],
        ];
    }

    public function getDivideData()
    {
        return [
            [5, 55, 11],
            [-6, 48, -8],
        ];
    }

    public function getEachData()
    {
        $counter  = new Counter();
        $addCount = function ($number, $index) use ($counter) { $counter->value += ($number + $index); };

        return [
            [[1, 2, 3, 4, 5], $addCount, [1, 2, 3, 4, 5]],
        ];
    }

    public function getEitherData()
    {
        $true  = function () { return true; };
        $false = function () { return false; };
        $equal = function ($a, $b) { return $a === $b; };

        return [
            [true, $true, $true],
            [true, $true, $false],
            [true, $false, $true],
            [false, $false, $false],
            [true, $equal, $true, 2, 2],
            [true, $equal, $true, 2, 1],
            [false, $equal, $equal, 2, 1],
        ];
    }

    public function getEqData()
    {
        $x = (object) [];
        $y = (object) [];

        return [
            [true, 'a', 'a'],
            [false, 'a', 'b'],
            [true, null, null],
            [false, true, false],
            [false, null, false],
            [false, 0, false],
            [false, 'a', 'b'],
            [true, $x, $x],
            [false, $y, $x],
        ];
    }

    public function getEvolveData()
    {
        return [
            [['foo' => ['b', 'a', 'r'], 'biz' => 'buz'], ['foo' => 'str_split'], ['foo' => 'bar', 'biz' => 'buz']],
        ];
    }

    public function getExplodeData()
    {
        return [
            [['f', 'o', 'o'], '/', 'f/o/o'],
            [['b', '/', 'a', '/', 'z'], '', 'b/a/z'],
            [[''], '.', ''],
            [['a', 'b', 'cd', ''], '.', 'a.b.cd.'],
        ];
    }

    public function getFalseData()
    {
        return [
            [false],
        ];
    }

    public function getFilterData()
    {
        $gt2               = function ($x) { return $x > 2; };
        $isEven            = function ($x) { return $x % 2 === 0; };
        $isSmallerThanNext = function ($value, $key, $list) {
            return isset($list[$key + 1]) ? $value < $list[$key + 1] : false;
        };

        return [
            [[2 => 3, 3 => 4], $gt2, [1, 2, 3, 4]],
            [[1 => 2, 3 => 4], $isEven, [1, 2, 3, 4]],
            [[0 => 3, 2 => 2, 3 => 19], $isSmallerThanNext, [3, 6, 2, 19, 44, 5]],
        ];
    }

    public function getFindData()
    {
        $isPositive = function ($x) { return $x > 0; };

        return [
            [15, $isPositive, [-5, 0, 15, 33, -2]],
            [22, $isPositive, ['a' => -3, 'b' => 22, 'c' => 13, 'd' => 0, 'e' => -3]],
            [null, $isPositive, [-5, 0, -8, -1, -2]],
        ];
    }

    public function getFindIndexData()
    {
        $isPositive = function ($x) { return $x > 0; };

        return [
            [2, $isPositive, [-5, 0, 15, 33, -2]],
            ['b', $isPositive, ['a' => -3, 'b' => 22, 'c' => 13, 'd' => 0, 'e' => -3]],
            [null, $isPositive, [-5, 0, -8, -1, -2]],
        ];
    }

    public function getFindLastData()
    {
        $isPositive = function ($x) { return $x > 0; };

        return [
            [33, $isPositive, [-5, 0, 15, 33, -2]],
            [13, $isPositive, ['a' => -3, 'b' => 22, 'c' => 13, 'd' => 0, 'e' => -3]],
            [null, $isPositive, [-5, 0, -8, -1, -2]],
        ];
    }

    public function getFindLastIndexData()
    {
        $isPositive = function ($x) { return $x > 0; };

        return [
            [3, $isPositive, [-5, 0, 15, 33, -2]],
            ['c', $isPositive, ['a' => -3, 'b' => 22, 'c' => 13, 'd' => 0, 'e' => -3]],
            [null, $isPositive, [-5, 0, -8, -1, -2]],
        ];
    }

    public function getFirstData()
    {
        $a = (object) [];
        $b = (object) [];
        $c = (object) [];

        return [
            [5, [5, 8, 9, 13]],
            [null, []],
            [$a, [$a, $b, $c]],
        ];
    }

    public function getFlatMapData()
    {
        $duplicate = function ($x) { return [$x, $x]; };

        return [
            [[2, 2, 3, 3, 6, 6], $duplicate, [2, 3, 6]],
        ];
    }

    public function getFlattenData()
    {
        return [
            [[1, 2, 3, 4], [1, [2, 3], [4]]],
            [[1, 2, 3, 4], [1, [2, [3]], [[4]]]],
        ];
    }

    public function getFlattenLevelData()
    {
        return [
            [[1, 2, 3, 4], [1, [2, 3], [4]]],
            [[1, 2, [3], [4]], [1, [2, [3]], [[4]]]],
        ];
    }

    public function getFlipData()
    {
        $subMany = function ($a, $b, $c = 0, $d = 0, $e = 0) {
            return $a - $b - $c - $d - $e;
        };

        return [
            [-22, $subMany, 42, 20],
            [-36, $subMany, 42, 20, 6, 8],
        ];
    }

    public function getFromPairsData()
    {
        return [
            [['a' => 'b', 'c' => 'd'], [['a', 'b'], ['c', 'd']]],
            [[3 => 'b', 5 => null], [[3, 'b'], [5, null]]],
        ];
    }

    public function getGroupByData()
    {
        $firstChar = function ($string) { return $string[0]; };

        return [
            [
                ['a' => [0 => 'abc', 1 => 'aba', 5 => 'ayb'], 'c' => [2 => 'cbc', 3 => 'cab'], 'b' => [4 => 'baa'], 'd' => [6 => 'dfe']],
                $firstChar,
                ['abc', 'aba', 'cbc', 'cab', 'baa', 'ayb', 'dfe'],
            ],
        ];
    }

    public function getGtData()
    {
        return [
            [false, 1, 2],
            [false, 1, 1],
            [true, 2, 1],
        ];
    }

    public function getGteData()
    {
        return [
            [false, 1, 2],
            [true, 1, 1],
            [true, 2, 1],
        ];
    }

    public function getIfElseData()
    {
        return [
            [42, P::lt(0), P::add(27), P::add(3), 15],
            [0, P::lt(0), P::add(27), P::add(3), -3],
        ];
    }

    public function getImplodeData()
    {
        return [
            ['f/o/o', '/', ['f', 'o', 'o']],
            ['a.b.cd.', '.', ['a', 'b', 'cd', '']],
            ['', '.', ['']],
        ];
    }

    public function getIncData()
    {
        return [
            [42, 41],
            [-15, -16],
        ];
    }

    public function getIndexOfData()
    {
        $a = (object) [];
        $b = (object) [];
        $c = (object) [];

        return [
            [3, 16, [1, 6, 44, 16, 52]],
            [null, 15, [1, 6, 44, 16, 52]],
            ['a', $a, ['a' => $a, 'b' => $b, 'c' => $c]],
            [null, 15, []],
        ];
    }

    public function getIdentityData()
    {
        $a = (object) ['foo' => 'bar'];

        return [
            [1, 1],
            [null, null],
            ['abc', 'abc'],
            [$a, $a],
        ];
    }

    public function getInvokerData()
    {
        $calculator = new Calculator();

        return [
            [42, 2, 'addTwo', [], 15, 27, $calculator],
            [42, 2, 'addTwo', [15, 27], $calculator],
            [65, 4, 'addMany', [15, 27], 1, 5, 8, 9, $calculator],
        ];
    }

    public function getIsEmptyData()
    {
        return [
            [false, [1, 2, 3]],
            [false, [0]],
            [true, []],
        ];
    }

    public function getIsInstanceData()
    {
        return [
            [true, Test1::class, new Test1()],
            [false, Test2::class, new Test1()],
        ];
    }

    public function getLastData()
    {
        $a = (object) [];
        $b = (object) [];
        $c = (object) [];

        return [
            [13, [5, 8, 9, 13]],
            [$c, [$a, $b, $c]],
            [null, []],
        ];
    }

    public function getLtData()
    {
        return [
            [true, 1, 2],
            [false, 1, 1],
            [false, 2, 1],
        ];
    }

    public function getLteData()
    {
        return [
            [true, 1, 2],
            [true, 1, 1],
            [false, 2, 1],
        ];
    }

    public function getMapData()
    {
        $square            = function ($x) { return $x ** 2; };
        $lengthKeyMultiply = function ($value, $key, $list) {
            return $value * $key * count($list);
        };

        return [
            [[1, 4, 9, 16], $square, [1, 2, 3, 4]],
            [[], $square, []],
            [[0, 8, 24, 48], $lengthKeyMultiply, [1, 2, 3, 4]],
        ];
    }

    public function getMaxData()
    {
        return [
            [15, [6, 15, 8, 9, -2, -3]],
            ['foo', ['bar', 'foo', 'baz']],
        ];
    }

    public function getMaxByData()
    {
        $getFoo = function ($item) { return $item->foo; };

        $a = (object) ['baz' => 3, 'bar' => 16, 'foo' => 5];
        $b = (object) ['baz' => 1, 'bar' => 25, 'foo' => 8];
        $c = (object) ['baz' => 14, 'bar' => 20, 'foo' => -2];

        return [
            [$b, $getFoo, [$a, $b, $c]],
            [$a, $getFoo, [$a, $c]],
        ];
    }

    public function getMergeData()
    {
        return [
            [[1, 2, 3, 4, 5], [1, 2], [3, 4, 5]],
            [['a', 'b', 'a', 'b'], ['a', 'b'], ['a', 'b']],
        ];
    }

    public function getMinData()
    {
        return [
            [-3, [6, 15, 8, 9, -2, -3]],
            ['bar', ['bar', 'foo', 'baz']],
        ];
    }

    public function getMinByData()
    {
        $getBar = function ($item) { return $item->bar; };

        $a = (object) ['baz' => 3, 'bar' => 16, 'foo' => 5];
        $b = (object) ['baz' => 1, 'bar' => 25, 'foo' => 8];
        $c = (object) ['baz' => 14, 'bar' => 20, 'foo' => -2];

        return [
            [$a, $getBar, [$a, $b, $c]],
            [$c, $getBar, [$b, $c]],
        ];
    }

    public function getModuloData()
    {
        return [
            [3, 15, 6],
            [0, 22, 11],
            [-5, -23, 6],
        ];
    }

    public function getMultiplyData()
    {
        return [
            [405, 15, 27],
            [-288, 36, -8],
        ];
    }

    public function getNAryData()
    {
        $add3 = function ($a = 0, $b = 0, $c = 0) { return $a + $b + $c; };

        return [
            [42, 2, $add3, 27, 15, 33],
            [27, 1, $add3, 27, 15, 33],
            [0, 0, $add3, 27, 15, 33],
        ];
    }

    public function getNegateData()
    {
        return [
            [-15, 15],
            [0.7, -0.7],
            [0, 0],
        ];
    }

    public function getNoneData()
    {
        $isPositive = function ($x) { return $x > 0; };

        return [
            [false, $isPositive, [1, 2, 0, -5]],
            [true, $isPositive, [-3, -7, -1, -5]],
            [false, $isPositive, [1, 2, 1, 11]],
        ];
    }

    public function getNotData()
    {
        $equal = function ($a, $b) { return $a === $b; };

        return [
            [false, $equal, 1, 1],
            [true, $equal, 1, 2],
        ];
    }

    public function getPartialData()
    {
        $sum = function ($a, $b, $c, $d) { return $a + $b + $c + $d; };

        return [
            [42, $sum, [], 23, 18, 29, -28],
            [42, $sum, [29, -28], 23, 18, 15],
        ];
    }

    public function getPartialNData()
    {
        $sum = function ($a, $b, $c, $d) { return $a + $b + $c + $d; };

        return [
            [42, 4, $sum, [], 23, 18, 29, -28],
            [42, 4, $sum, [29, -28], 23, 18, 15],
        ];
    }

    public function getPartitionData()
    {
        $largerThanFive = function ($x) { return $x > 5; };

        return [
            [[[1 => 16, 2 => 7, 5 => 88], [0 => 4, 3 => -3, 4 => 2]], $largerThanFive, [4, 16, 7, -3, 2, 88]],
            [[[0 => 4, 3 => -3, 4 => 2], [1 => 16, 2 => 7, 5 => 88]], P::not($largerThanFive), [4, 16, 7, -3, 2, 88]],
        ];
    }

    public function getPathData()
    {
        return [
            [15, ['foo', 'bar'], ['foo' => ['baz' => 26, 'bar' => 15]]],
            [26, ['foo', 'baz'], ['foo' => (object) ['baz' => 26, 'bar' => 15]]],
            [null, ['bar', 'baz'], ['bar' => ['baz' => null, 'foo' => 15]]],
        ];
    }

    public function getPathEqData()
    {
        return [
            [false, ['foo', 'bar'], 44, ['foo' => ['baz' => 26, 'bar' => 15]]],
            [true, ['foo', 'baz'], 26, ['foo' => ['baz' => 26, 'bar' => 15]]],
            [false, ['foo', 'baz'], 37, ['foo' => (object) ['baz' => 26, 'bar' => 15]]],
            [true, ['foo', 'bar'], 15, ['foo' => (object) ['baz' => 26, 'bar' => 15]]],
            [true, ['bar', 'baz'], null, ['bar' => ['baz' => null, 'foo' => 15]]],
            [false, ['bar', 'baz'], 1, ['bar' => ['baz' => null, 'foo' => 15]]],
        ];
    }

    public function getPickData()
    {
        $item = ['foo' => null, 'bar' => 'bzz', 'baz' => 'bob'];

        return [
            [['bar' => 'bzz'], ['bar', 'fib'], $item],
            [[], ['fob', 'fib'], $item],
            [['bar' => 'bzz', 'foo' => null], ['bar', 'foo'], $item],
            [[], [], $item],
        ];
    }

    public function getPickAllData()
    {
        $item = ['foo' => null, 'bar' => 'bzz', 'baz' => 'bob'];

        return [
            [['bar' => 'bzz', 'fib' => null], ['bar', 'fib'], $item],
            [['fob' => null, 'fib' => null], ['fob', 'fib'], $item],
            [['bar' => 'bzz', 'foo' => null], ['bar', 'foo'], $item],
            [[], [], $item],
        ];
    }

    public function getPipeData()
    {
        $sum    = function ($x, $y) { return $x + $y; };
        $square = function ($x) { return $x ** 2; };
        $triple = function ($x) { return 3 * $x; };

        return [
            [300, [$sum, $square, $triple], 2, 8],
            [675, [$triple, $square, $triple], 5],
        ];
    }

    public function getPluckData()
    {
        $items = [
            ['foo' => null, 'bar' => 'bzz', 'baz' => 'bob'],
            ['foo' => 'fii', 'baz' => 'pob'],
        ];

        return [
            [[null, 'fii'], 'foo', $items],
            [['bob', 'pob'], 'baz', $items],
        ];
    }

    public function getPrependData()
    {
        return [
            [['c', 'a', 'b'], 'c', ['a', 'b']],
            [['c'], 'c', []],
            [[['d', 'e'], 'a', 'b'], ['d', 'e'], ['a', 'b']],
        ];
    }

    public function getProductData()
    {
        return [
            [-264, [11, -8, 3]],
            [720, [1, 2, 3, 4, 5, 6]],
        ];
    }

    public function getPropData()
    {
        $foo = ['bar' => 'fuz', 'baz' => null];

        return [
            ['fuz', 'bar', $foo],
            [null, 'baz', $foo],
            ['fuz', 'bar', (object) $foo],
            [null, 'baz', (object) $foo],
        ];
    }

    public function getPropEqData()
    {
        return [
            [true, 'foo', 'bar', ['foo' => 'bar']],
            [false, 'foo', 'baz', ['foo' => 'bar']],
            [true, 'foo', 'bar', (object) ['foo' => 'bar']],
            [false, 'foo', 'baz', (object) ['foo' => 'bar']],
        ];
    }

    public function getReduceData()
    {
        $concat         = function ($x, $y) { return $x . $y; };
        $sum            = function ($x, $y) { return $x + $y; };
        $keyValueConcat = function ($accumulator, $value, $key, $list) {
            return $accumulator . $value . ($key !== $value ? $list[$value] : '');
        };

        return [
            [10, $sum, 0, [1, 2, 3, 4]],
            [20, $sum, 10, [1, 2, 3, 4]],
            [5, $sum, 5, []],
            ['xabcd', $concat, 'x', ['a', 'b', 'c', 'd']],
            ['efcdbdaac', $keyValueConcat, 'ef', ['a' => 'c', 'b' => 'b', 'c' => 'd', 'd' => 'a']],
        ];
    }

    public function getReduceRightData()
    {
        $concat         = function ($x, $y) { return $x . $y; };
        $sum            = function ($x, $y) { return $x + $y; };
        $keyValueConcat = function ($accumulator, $value, $key, $list) {
            return $accumulator . $value . ($key !== $value ? $list[$value] : '');
        };

        return [
            [10, $sum, 0, [1, 2, 3, 4]],
            [20, $sum, 10, [1, 2, 3, 4]],
            [5, $sum, 5, []],
            ['xdcba', $concat, 'x', ['a', 'b', 'c', 'd']],
            ['efacdabcd', $keyValueConcat, 'ef', ['a' => 'c', 'b' => 'b', 'c' => 'd', 'd' => 'a']],
        ];
    }

    public function getRejectData()
    {
        $gt2               = function ($x) { return $x > 2; };
        $isEven            = function ($x) { return $x % 2 === 0; };
        $isSmallerThanNext = function ($value, $key, $list) {
            return isset($list[$key + 1]) ? $value < $list[$key + 1] : false;
        };

        return [
            [[0 => 1, 1 => 2], $gt2, [1, 2, 3, 4]],
            [[0 => 1, 2 => 3], $isEven, [1, 2, 3, 4]],
            [[1 => 6, 4 => 44, 5 => 5], $isSmallerThanNext, [3, 6, 2, 19, 44, 5]],
        ];
    }

    public function getReverseData()
    {
        return [
            [[2 => 1, 1 => 2, 0 => 3], [3, 2, 1]],
            [[3 => 5, 2 => 16, 1 => 4, 0 => 22], [22, 4, 16, 5]],
            [[], []],
        ];
    }

    public function getSliceData()
    {
        $list = [1, 2, 3, 4, 5, 6, 7, 8, 9];

        return [
            [[3, 4, 5, 6], 2, 6, $list],
            [[1, 2, 3], 0, 3, $list],
            [[8, 9], 7, 11, $list],
            [[3, 4, 5, 6], 2, -3, $list],
            [[5, 6], -5, -3, $list],
        ];
    }

    public function getSortData()
    {
        $sub = function ($a, $b) { return $a - $b; };

        return [
            [[1, 2, 3, 4], $sub, [2, 4, 1, 3]],
        ];
    }

    public function getSortByData()
    {
        $getFoo = function ($a) { return $a['foo']; };

        return [
            [
                [['foo' => 5, 'bar' => 42], ['foo' => 11, 'bar' => 7], ['foo' => 16, 'bar' => 3]],
                $getFoo,
                [['foo' => 16, 'bar' => 3], ['foo' => 5, 'bar' => 42], ['foo' => 11, 'bar' => 7]],
            ],
        ];
    }

    public function getStringIndexOfData()
    {
        return [
            [3, 'def', 'abcdefdef'],
            [0, 'a', 'abcdefgh'],
            [null, 'ghi', 'abcdefgh'],
            [null, 'xyz', 'abcdefgh'],
            [null, 'cba', 'abcdefgh'],
        ];
    }

    public function getStringLastIndexOfData()
    {
        return [
            [6, 'def', 'abcdefdef'],
            [0, 'a', 'abcdefgh'],
            [null, 'ghi', 'abcdefgh'],
            [null, 'xyz', 'abcdefgh'],
            [null, 'cba', 'abcdefgh'],
        ];
    }

    public function getSubstringData()
    {
        return [
            ['oba', 2, 5, 'foobarbaz'],
            ['arba', 4, 8, 'foobarbaz'],
            ['barb', 3, -2, 'foobarbaz'],
            ['', 5, 5, 'foobarbaz'],
        ];
    }

    public function getSubstringFromData()
    {
        return [
            ['rbaz', 5, 'foobarbaz'],
            ['oobarbaz', 1, 'foobarbaz'],
            ['az', -2, 'foobarbaz'],
        ];
    }

    public function getSubstringToData()
    {
        return [
            ['fooba', 5, 'foobarbaz'],
            ['foobarba', 8, 'foobarbaz'],
            ['foobar', -3, 'foobarbaz'],
        ];
    }

    public function getSubtractData()
    {
        return [
            [-12, 15, 27],
            [44, 36, -8],
        ];
    }

    public function getSumData()
    {
        return [
            [21, [1, 2, 3, 4, 5, 6]],
            [16, [11, 0, 2, -4, 7]],
        ];
    }

    public function getTailData()
    {
        return [
            [[4, 6, 3], [2, 4, 6, 3]],
        ];
    }

    public function getTapData()
    {
        $counter = new Counter();
        $addFive = function ($object) { $object->value += 5; };
        $addTwo  = function (&$value) { $value += 2; };

        return [
            [42, $addTwo, 40],
            [$counter, $addFive, $counter],
        ];
    }

    public function getTimesData()
    {
        $double = function ($number) { return $number * 2; };

        return [
            [[0, 1, 2, 3, 4], P::identity(), 5],
            [[0, 2, 4, 6, 8], $double, 5],
        ];
    }

    public function getToPairsData()
    {
        return [
            [[['a', 'b'], ['c', 'd']], ['a' => 'b', 'c' => 'd']],
            [[[3, 'b'], [5, null]], [3 => 'b', 5 => null]],
        ];
    }

    public function getTrueData()
    {
        return [
            [true],
        ];
    }

    public function getUnaryData()
    {
        $add2 = function ($a = 0, $b = 0) { return $a + $b; };

        return [
            [27, $add2, 27, 15],
        ];
    }

    public function getUnapplyData()
    {
        return [
            [42, 'max', 13, -3, 42, 3],
            [[1, 2, 3], 'array_merge', 1, 2, 3],
        ];
    }

    public function getWhereData()
    {
        $isLargest = function ($value, $object) { return $value === max($object); };
        $x         = ['a' => 15, 'b' => 42, 'c' => 88, 'd' => -10];
        $y         = ['a' => 15, 'b' => 16, 'c' => -20, 'd' => 77];

        return [
            [false, ['a' => 15, 'b' => 16], $x],
            [true, ['a' => 15, 'b' => 16], $y],
            [true, ['a' => 15, 'b' => 16], (object) $y],
            [true, ['d' => $isLargest], $y],
            [false, ['b' => $isLargest], $x],
        ];
    }

    public function getZipData()
    {
        return [
            [[[1, 4], [2, 5], [3, 6]], [1, 2, 3], [4, 5, 6]],
            [['a' => [1, 3]], ['a' => 1, 'b' => 2], ['a' => 3, 'c' => 4]],
            [[], [1, 2, 3], []],
        ];
    }

    public function getZipWithData()
    {
        $sum = function ($x, $y) { return $x + $y; };

        return [
            [[5, 'a' => 7, 9], $sum, [1, 'a' => 2, 3], [4, 'a' => 5, 6]],
            [[6, 8], $sum, [1, 2, 3], [5, 6]],
            [[], $sum, [1, 2, 3], []],
        ];
    }
}
