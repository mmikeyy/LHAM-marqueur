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
 * Tests for function list doc examples.
 *
 * These tests are used to generate function doc comments and documentation. If a function is not tested here,
 * tests cases from the `BasicTest` class are used instead.
 *
 * For details about the code generation, please see the build directory.
 */
class FunctionExampleTest extends TestCase
{
    public function test_()
    {
        $sub10 = P::subtract(P::_(), 10);
        $this->assertSame(42, $sub10(52));
    }

    public function testAll()
    {
        $isPositive = function ($x) { return $x > 0; };
        $this->assertSame(false, P::all($isPositive, [1, 2, 0, -5]));
        $this->assertSame(true, P::all($isPositive, [1, 2, 1, 11]));
    }

    public function testAllPass()
    {
        $isEven            = function ($x) { return $x % 2 === 0; };
        $isPositive        = function ($x) { return $x > 0; };
        $isEvenAndPositive = P::allPass([$isEven, $isPositive]);
        $this->assertSame(false, $isEvenAndPositive(5));
        $this->assertSame(false, $isEvenAndPositive(-4));
        $this->assertSame(true, $isEvenAndPositive(6));
    }

    public function testAlways()
    {
        $alwaysFoo = P::always('foo');
        $this->assertSame('foo', $alwaysFoo());
    }

    public function testAny()
    {
        $isPositive = function ($x) { return $x > 0; };
        $this->assertSame(true, P::any($isPositive, [1, 2, 0, -5]));
        $this->assertSame(false, P::any($isPositive, [-3, -7, -1, -5]));
    }

    public function testAnyPass()
    {
        $isEven           = function ($x) { return $x % 2 === 0; };
        $isPositive       = function ($x) { return $x > 0; };
        $isEvenOrPositive = P::anyPass([$isEven, $isPositive]);
        $this->assertSame(true, $isEvenOrPositive(5));
        $this->assertSame(true, $isEvenOrPositive(-4));
        $this->assertSame(false, $isEvenOrPositive(-3));
    }

    public function testApply()
    {
        $concat3 = function ($a, $b, $c) { return $a . $b . $c; };
        $this->assertSame('foobarba', P::apply($concat3, ['foo', 'ba', 'rba']));
    }

    public function testBinary()
    {
        $add3 = function ($a = 0, $b = 0, $c = 0) { return $a + $b + $c; };
        $add2 = P::binary($add3);
        $this->assertSame(42, $add2(27, 15, 33));
    }

    public function testBoth()
    {
        $lt          = function ($x, $y) { return $x < $y; };
        $arePositive = function ($x, $y) { return $x > 0 && $y > 0; };
        $test        = P::both($lt, $arePositive);
        $this->assertSame(false, $test(9, 4));
        $this->assertSame(false, $test(-3, 11));
        $this->assertSame(true, $test(5, 17));
    }

    public function testComparator()
    {
        $lt      = function ($x, $y) { return $x < $y; };
        $compare = P::comparator($lt);
        $this->assertSame(-1, $compare(5, 6));
        $this->assertSame(1, $compare(6, 5));
        $this->assertSame(0, $compare(5, 5));
    }

    public function testConstruct()
    {
        $date = P::construct(\DateTime::class, '2015-03-15');
        $this->assertSame('2015-03-15', $date->format('Y-m-d'));
    }

    public function testConstructN()
    {
        $construct = P::constructN(1, \DateTime::class);
        $this->assertSame('2015-03-15', $construct('2015-03-15')->format('Y-m-d'));
    }

    public function testCompose()
    {
        $add5         = function ($x) { return $x + 5; };
        $square       = function ($x) { return $x ** 2; };
        $addToSquared = P::compose($add5, $square);
        $this->assertSame(21, $addToSquared(4));
        $hello      = function ($target) { return 'Hello ' . $target; };
        $helloUpper = P::compose($hello, 'strtoupper');
        $upperHello = P::compose('strtoupper', $hello);
        $this->assertSame('Hello WORLD', $helloUpper('world'));
        $this->assertSame('HELLO WORLD', $upperHello('world'));
    }

    public function testCurry()
    {
        $add        = function ($x, $y, $z) { return $x + $y + $z; };
        $addHundred = P::curry($add, 100);
        $this->assertSame(123, $addHundred(20, 3));
    }

    public function testCurryN()
    {
        $add    = function ($x, $y, $z = 0) { return $x + $y + $z; };
        $addTen = P::curryN(3, $add, 10);
        $this->assertSame(23, $addTen(10, 3));
        $addTwenty = $addTen(10);
        $this->assertSame(25, $addTwenty(5));
    }

    public function testEach()
    {
        $date        = new \DateTime('2015-02-02');
        $addCalendar = function ($number, $type) use ($date) { $date->modify("+$number $type"); };
        P::each($addCalendar, ['months' => 3, 'weeks' => 6, 'days' => 2]);
        $this->assertSame('2015-06-15', $date->format('Y-m-d'));
    }

    public function testEither()
    {
        $lt          = function ($x, $y) { return $x < $y; };
        $arePositive = function ($x, $y) { return $x > 0 && $y > 0; };
        $test        = P::either($lt, $arePositive);
        $this->assertSame(false, $test(-5, -16));
        $this->assertSame(true, $test(-3, 11));
        $this->assertSame(true, $test(17, 3));
    }

    public function testEvolve()
    {
        $object = ['foo' => 'bar', 'fiz' => 'buz'];
        $this->assertSame(['foo' => 'BAR', 'fiz' => 'buz'], P::evolve(['foo' => 'strtoupper'], $object));
    }

    public function testFalse()
    {
        $false = P::false();
        $this->assertSame(false, $false());
    }

    public function testFilter()
    {
        $gt2 = function ($x) { return $x > 2; };
        $this->assertSame(['bar' => 3, 'baz' => 4], P::filter($gt2, ['foo' => 2, 'bar' => 3, 'baz' => 4]));
    }

    public function testFind()
    {
        $isPositive = function ($x) { return $x > 0; };
        $this->assertSame(15, P::find($isPositive, [-5, 0, 15, 33, -2]));
    }

    public function testFindIndex()
    {
        $isPositive = function ($x) { return $x > 0; };
        $this->assertSame(2, P::findIndex($isPositive, [-5, 0, 15, 33, -2]));
    }

    public function testFindLast()
    {
        $isPositive = function ($x) { return $x > 0; };
        $this->assertSame(33, P::findLast($isPositive, [-5, 0, 15, 33, -2]));
    }

    public function testFindLastIndex()
    {
        $isPositive = function ($x) { return $x > 0; };
        $this->assertSame(3, P::findLastIndex($isPositive, [-5, 0, 15, 33, -2]));
    }

    public function testFlatMap()
    {
        $split = P::unary('str_split');
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], P::flatMap($split, ['abc', 'de']));
        $getNeighbors = function ($x) { return [$x - 1, $x, $x + 1]; };
        $this->assertSame([0, 1, 2, 1, 2, 3, 2, 3, 4], P::flatMap($getNeighbors, [1, 2, 3]));
    }

    public function testFlip()
    {
        $sub        = function ($x, $y) { return $x - $y; };
        $flippedSub = P::flip($sub);
        $this->assertSame(10, $flippedSub(20, 30));
    }

    public function testGroupBy()
    {
        $firstChar  = function ($string) { return $string[0]; };
        $collection = ['abc', 'cbc', 'cab', 'baa', 'ayb'];
        $this->assertSame(
            ['a' => [0 => 'abc', 4 => 'ayb'], 'c' => [1 => 'cbc', 2 => 'cab'], 'b' => [3 => 'baa']],
            P::groupBy($firstChar, $collection)
        );
    }

    public function testIfElse()
    {
        $addOrSub = P::ifElse(P::lt(0), P::add(-10), P::add(10));
        $this->assertSame(15, $addOrSub(25));
        $this->assertSame(7, $addOrSub(-3));
    }

    public function testInvoker()
    {
        $addDay = P::invoker(1, 'add', new \DateInterval('P1D'));
        $this->assertSame('2015-03-16', $addDay(new \DateTime('2015-03-15'))->format('Y-m-d'));
        $this->assertSame('2015-03-13', $addDay(new \DateTime('2015-03-12'))->format('Y-m-d'));
    }

    public function testIsInstance()
    {
        $isDate = P::isInstance(\DateTime::class);
        $this->assertSame(true, $isDate(new \DateTime()));
        $this->assertSame(false, $isDate(new \DateTimeImmutable()));
    }

    public function testMap()
    {
        $square = function ($x) { return $x ** 2; };
        $this->assertSame([1, 4, 9, 16], P::map($square, [1, 2, 3, 4]));
        $keyExp = function ($value, $key) { return $value ** $key; };
        $this->assertSame([1, 2, 9, 64], P::map($keyExp, [1, 2, 3, 4]));
    }

    public function testMaxBy()
    {
        $getFoo = function ($item) { return $item->foo; };
        $a      = (object) ['baz' => 3, 'bar' => 16, 'foo' => 5];
        $b      = (object) ['baz' => 1, 'bar' => 25, 'foo' => 8];
        $c      = (object) ['baz' => 14, 'bar' => 20, 'foo' => -2];
        $this->assertSame($b, P::maxBy($getFoo, [$a, $b, $c]));
    }

    public function testMinBy()
    {
        $getFoo = function ($item) { return $item->foo; };
        $a      = (object) ['baz' => 3, 'bar' => 16, 'foo' => 5];
        $b      = (object) ['baz' => 1, 'bar' => 25, 'foo' => 8];
        $c      = (object) ['baz' => 14, 'bar' => 20, 'foo' => -2];
        $this->assertSame($c, P::minBy($getFoo, [$a, $b, $c]));
    }

    public function testNAry()
    {
        $add3 = function ($a = 0, $b = 0, $c = 0) { return $a + $b + $c; };
        $add2 = P::nAry(2, $add3);
        $this->assertSame(42, $add2(27, 15, 33));
        $add1 = P::nAry(1, $add3);
        $this->assertSame(27, $add1(27, 15, 33));
    }

    public function testNone()
    {
        $isPositive = function ($x) { return $x > 0; };
        $this->assertSame(false, P::none($isPositive, [1, 2, 0, -5]));
        $this->assertSame(true, P::none($isPositive, [-3, -7, -1, -5]));
    }

    public function testNot()
    {
        $equal    = function ($a, $b) { return $a === $b; };
        $notEqual = P::not($equal);
        $this->assertSame(true, $notEqual(15, 13));
        $this->assertSame(false, $notEqual(7, 7));
    }

    public function testPartial()
    {
        $add    = function ($x, $y, $z) { return $x + $y + $z; };
        $addTen = P::partial($add, 10);
        $this->assertSame(17, $addTen(3, 4));
        $addTwenty = P::partial($add, 2, 3, 15);
        $this->assertSame(20, $addTwenty());
    }

    public function testPartialN()
    {
        $add       = function ($x, $y, $z = 0) { return $x + $y + $z; };
        $addTen    = P::partialN(3, $add, 10);
        $addTwenty = $addTen(10);
        $this->assertSame(25, $addTwenty(5));
    }

    public function testPartition()
    {
        $isPositive = function ($x) { return $x > 0; };
        $this->assertSame([[0 => 4, 2 => 7, 4 => 2, 5 => 88], [1 => -16, 3 => -3]], P::partition($isPositive, [4, -16, 7, -3, 2, 88]));
    }

    public function testPipe()
    {
        $add5        = function ($x) { return $x + 5; };
        $square      = function ($x) { return $x ** 2; };
        $squareAdded = P::pipe($add5, $square);
        $this->assertSame(81, $squareAdded(4));
        $hello      = function ($target) { return 'Hello ' . $target; };
        $helloUpper = P::pipe('strtoupper', $hello);
        $upperHello = P::pipe($hello, 'strtoupper');
        $this->assertSame('Hello WORLD', $helloUpper('world'));
        $this->assertSame('HELLO WORLD', $upperHello('world'));
    }

    public function testReduce()
    {
        $concat = function ($x, $y) { return $x . $y; };
        $this->assertSame('foobarbaz', P::reduce($concat, 'foo', ['bar', 'baz']));
    }

    public function testReduceRight()
    {
        $concat = function ($accumulator, $value, $key) { return $accumulator . $key . $value; };
        $this->assertSame('nofizbuzfoobar', P::reduceRight($concat, 'no', ['foo' => 'bar', 'fiz' => 'buz']));
    }

    public function testReject()
    {
        $isEven = function ($x) { return $x % 2 === 0; };
        $this->assertSame([0 => 1, 2 => 3], P::reject($isEven, [1, 2, 3, 4]));
    }

    public function testSort()
    {
        $sub = function ($a, $b) { return $a - $b; };
        $this->assertSame([1, 2, 3, 4], P::sort($sub, [3, 2, 4, 1]));
    }

    public function testSortBy()
    {
        $getFoo     = function ($a) { return $a['foo']; };
        $collection = [['foo' => 16, 'bar' => 3], ['foo' => 5, 'bar' => 42], ['foo' => 11, 'bar' => 7]];
        $this->assertSame(
            [['foo' => 5, 'bar' => 42], ['foo' => 11, 'bar' => 7], ['foo' => 16, 'bar' => 3]],
            P::sortBy($getFoo, $collection)
        );
    }

    public function testTap()
    {
        $addDay = function (\DateTime $date) { $date->add(new \DateInterval('P1D')); };
        $date   = new \DateTime('2015-03-15');
        $this->assertSame($date, P::tap($addDay, $date));
        $this->assertSame('2015-03-16', $date->format('Y-m-d'));
    }

    public function testTimes()
    {
        $double = function ($number) { return $number * 2; };
        $this->assertSame([0, 2, 4, 6, 8], P::times($double, 5));
    }

    public function testTrue()
    {
        $true = P::true();
        $this->assertSame(true, $true());
    }

    public function testUnapply()
    {
        $concat = function (array $strings) { return implode(' ', $strings); };
        $this->assertSame('foo ba rba', P::unapply($concat, 'foo', 'ba', 'rba'));
    }

    public function testUnary()
    {
        $add2 = function ($a = 0, $b = 0) { return $a + $b; };
        $add1 = P::nAry(1, $add2);
        $this->assertSame(27, $add1(27, 15));
    }

    public function testZipWith()
    {
        $sum = function ($x, $y) { return $x + $y; };
        $this->assertSame([6, 8], P::zipWith($sum, [1, 2, 3], [5, 6]));
    }
}
