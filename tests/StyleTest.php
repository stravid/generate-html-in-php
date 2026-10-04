<?php

declare(strict_types=1);

namespace Stravid\Html\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stravid\Html\Tests\Fixtures\Size;

use function Stravid\Html\div;

final class StyleTest extends TestCase
{
    public function testTrimsWhitespaceAndSemicolonsFromStrings(): void
    {
        self::assertSame('<div style="color: red"></div>', (string) div(style: ' color: red; '));
        self::assertSame('<div style="color: red"></div>', (string) div(style: 'color: red;;'));
        self::assertSame('<div style="color: red"></div>', (string) div(style: "\v\0\t\n color: red;\v\0\r"));
        self::assertSame('<div style="color: red; margin: 0"></div>', (string) div(style: 'color: red; margin: 0;'));
    }

    public function testRendersArrayAsDeclarations(): void
    {
        self::assertSame(
            '<div style="color: red; margin-top: 1rem; opacity: 0.5; --size: large"></div>',
            (string) div(style: ['color' => 'red', 'margin-top' => '1rem', 'opacity' => 0.5, '--size' => Size::Large]),
        );
    }

    public function testEscapesValues(): void
    {
        self::assertSame(
            '<div style="font-family: &quot;Helvetica Neue&quot;, sans-serif"></div>',
            (string) div(style: ['font-family' => '"Helvetica Neue", sans-serif']),
        );
    }

    public function testNullFalseAndEmptyValuesLeavePropertyOut(): void
    {
        $hidden = false;

        self::assertSame(
            '<div style="color: red"></div>',
            (string) div(style: ['color' => 'red', 'display' => $hidden ? 'none' : null, 'width' => false, 'height' => '']),
        );
    }

    public function testKeepsZero(): void
    {
        self::assertSame('<div style="width: 0; height: 0"></div>', (string) div(style: ['width' => 0, 'height' => '0']));
    }

    public function testKeepsFalseString(): void
    {
        self::assertSame('<div style="display: false"></div>', (string) div(style: ['display' => 'false']));
    }

    #[DataProvider('trueValues')]
    public function testTrueThrows(mixed $style): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't render a value of type bool");

        div(style: $style);
    }

    public function testHandlesIntKeyStringsAndNestedArrays(): void
    {
        self::assertSame(
            '<div style="color: red; margin: 0; padding: 0; height: 50%"></div>',
            (string) div(style: ['color' => 'red', 'margin: 0; padding: 0;', ['height' => '50%']]),
        );
    }

    public function testMergesAcrossArguments(): void
    {
        self::assertSame('<div style="color: red; margin: 0"></div>', (string) div(style: 'color: red;')(style: ['margin' => 0]));
        self::assertSame('<div style="a: 1; b: 2"></div>', (string) div(['style' => 'a: 1'], style: 'b: 2'));
    }

    public function testKeepsDuplicatesInOrder(): void
    {
        self::assertSame(
            '<div style="color: red; color: blue; color: red"></div>',
            (string) div(style: ['color: red', 'color: blue', 'color: red']),
        );
        self::assertSame(
            '<div style="color: red; color: blue; color: red"></div>',
            (string) div(style: [['color' => 'red'], ['color' => 'blue'], ['color' => 'red']]),
        );
        self::assertSame(
            '<div style="color: red; color: blue; color: red"></div>',
            (string) div(style: ['color' => 'red'])(style: 'color: blue')(style: ['color' => 'red']),
        );
    }

    #[DataProvider('emptyValues')]
    public function testLeavesEmptyStyleOut(mixed $style): void
    {
        self::assertSame('<div></div>', (string) div(style: $style));
    }

    public function testEmptyStyleKeepsEarlierStyles(): void
    {
        self::assertSame('<div style="color: red"></div>', (string) div(style: 'color: red')(style: []));
        self::assertSame('<div style="color: red"></div>', (string) div(style: 'color: red')(style: ' ; '));
    }

    public static function trueValues(): array
    {
        return [
            'true' => [true],
            'true as value' => [['display' => true]],
            'true in list' => [[true]],
        ];
    }

    public static function emptyValues(): array
    {
        return [
            'empty string' => [''],
            'whitespace and semicolons' => [' ; ;'],
            'null' => [null],
            'false' => [false],
            'empty array' => [[]],
            'only empty values' => [['color' => null, 'margin' => false, 'width' => '']],
            'only empty strings' => [['', ';']],
        ];
    }
}
