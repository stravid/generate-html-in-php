<?php

declare(strict_types=1);

namespace Stravid\Html\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stravid\Html\Tests\Fixtures\Size;

use function Stravid\Html\div;

final class ClassTest extends TestCase
{
    public function testRendersStringAndNormalisesWhitespace(): void
    {
        self::assertSame('<div class="btn"></div>', (string) div(class: 'btn'));
        self::assertSame('<div class="btn btn--primary"></div>', (string) div(class: "  btn \n\t btn--primary  "));
    }

    public function testStringKeyWithTruthyValueIncludesClass(): void
    {
        self::assertSame(
            '<div class="active one yes list"></div>',
            (string) div(class: ['active' => true, 'one' => 1, 'yes' => 'yes', 'list' => [1]]),
        );
    }

    public function testKeyWithSeveralClassesAddsAll(): void
    {
        self::assertSame('<div class="btn btn--active"></div>', (string) div(class: ['btn btn--active' => true]));
        self::assertSame('<div></div>', (string) div(class: ['btn btn--active' => false]));
    }

    public function testFalsyConditionsLeaveClassOut(): void
    {
        self::assertSame(
            '<div class="kept"></div>',
            (string) div(class: ['false' => false, 'zero' => 0, 'zero-string' => '0', 'empty' => '', 'null' => null, 'array' => [], 'kept' => true]),
        );
    }

    public function testSkipsNullFalseAndEmptyItems(): void
    {
        $active = false;

        self::assertSame('<div class="a b"></div>', (string) div(class: ['a', null, false, '', 'b']));
        self::assertSame('<div class="btn"></div>', (string) div(class: ['btn', $active ? 'is-active' : null]));
    }

    public function testKeepsZeroAsItem(): void
    {
        self::assertSame('<div class="0"></div>', (string) div(class: ['0']));
        self::assertSame('<div class="0"></div>', (string) div(class: [0]));
        self::assertSame('<div class="0"></div>', (string) div(class: '0'));
    }

    public function testAcceptsEnumsAsClassNames(): void
    {
        self::assertSame('<div class="btn large"></div>', (string) div(class: ['btn', Size::Large]));
    }

    #[DataProvider('trueValues')]
    public function testTrueAsItemThrows(mixed $class): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't render a value of type bool");

        div(class: $class);
    }

    public function testHandlesNestedArrays(): void
    {
        self::assertSame(
            '<div class="btn icon icon--left deep"></div>',
            (string) div(class: ['btn', ['icon', 'icon--left' => true, 'icon--right' => false, ['deep']]]),
        );
    }

    public function testMergesAcrossArgumentsAndRemovesDuplicates(): void
    {
        self::assertSame('<div class="btn card card--wide"></div>', (string) div(class: 'btn card')(class: ['btn', 'card--wide']));
        self::assertSame('<div class="a b"></div>', (string) div(['class' => 'a'], class: 'b'));
        self::assertSame('<div class="a b"></div>', (string) div(class: 'a a b a'));
    }

    #[DataProvider('emptyValues')]
    public function testLeavesEmptyClassOut(mixed $class): void
    {
        self::assertSame('<div></div>', (string) div(class: $class));
    }

    public function testEmptyClassKeepsEarlierClasses(): void
    {
        self::assertSame('<div class="a"></div>', (string) div(class: 'a')(class: []));
        self::assertSame('<div class="a"></div>', (string) div(class: 'a')(class: null));
    }

    public static function trueValues(): array
    {
        return [
            'true' => [true],
            'true in list' => [[true]],
            'JavaScript habit' => [[true && 'active']],
        ];
    }

    public static function emptyValues(): array
    {
        return [
            'empty string' => [''],
            'whitespace' => ['   '],
            'null' => [null],
            'false' => [false],
            'empty array' => [[]],
            'only false conditions' => [['a' => false, 'b' => null]],
            'only skipped items' => [[null, false, '']],
        ];
    }
}
