<?php

declare(strict_types=1);

namespace Stravid\Html\Tests;

use PHPUnit\Framework\TestCase;

use function Stravid\Html\a;
use function Stravid\Html\button;
use function Stravid\Html\div;
use function Stravid\Html\h1;
use function Stravid\Html\input;
use function Stravid\Html\p;
use function Stravid\Html\span;

final class InvokeTest extends TestCase
{
    public function testAttributesFirstThenChildren(): void
    {
        self::assertSame(
            '<div class="card"><h1>Title</h1><p>Text</p></div>',
            (string) div(class: 'card')(h1('Title'), p('Text')),
        );
        self::assertSame('<a href="/">Home</a>', (string) a(href: '/')('Home'));
    }

    public function testAppendsChildrenAfterExistingOnes(): void
    {
        self::assertSame('<p>ab<span>c</span></p>', (string) p('a')('b', span('c')));
    }

    public function testEscapesExistingTextChildrenOnlyOnce(): void
    {
        self::assertSame('<p>&lt;&amp;&gt;</p>', (string) p('<')('&')('>'));
    }

    public function testMergesClassAndStyleAndOverridesOtherAttributes(): void
    {
        self::assertSame(
            '<div class="a b" style="color: red; margin: 0" id="y"></div>',
            (string) div(class: 'a', style: 'color: red', id: 'x')(class: 'b', style: ['margin' => 0], id: 'y'),
        );
    }

    public function testKeepsBooleanAttributes(): void
    {
        self::assertSame('<input required type="email">', (string) input(required: true)(type: 'email'));
    }

    public function testConvertsUnderscoresInNewNamedArguments(): void
    {
        self::assertSame('<button aria-label="Close"></button>', (string) button()(aria_label: 'Close'));
    }

    public function testKeepsExistingAttributeNamesAsIs(): void
    {
        self::assertSame('<div data_raw="1" id="x"></div>', (string) div(['data_raw' => 1])(id: 'x'));
    }

    public function testLeavesOriginalElementUnchanged(): void
    {
        $button = button(class: 'btn', type: 'button')('Save');

        $primary = $button(class: 'btn--primary', type: 'submit')('!');
        $secondary = $button(class: 'btn--secondary');

        self::assertSame('<button class="btn" type="button">Save</button>', (string) $button);
        self::assertSame('<button class="btn btn--primary" type="submit">Save!</button>', (string) $primary);
        self::assertSame('<button class="btn btn--secondary" type="button">Save</button>', (string) $secondary);
    }

    public function testReturnsNewElementEvenWithoutArguments(): void
    {
        $div = div(class: 'a')('x');

        self::assertNotSame($div, $div());
        self::assertSame((string) $div, (string) $div());
    }

    public function testCanBeChained(): void
    {
        self::assertSame(
            '<div class="a b" id="x"><p>hi</p></div>',
            (string) div(class: 'a')(id: 'x')(p('hi'))(class: 'b'),
        );
    }
}
