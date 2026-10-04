<?php

declare(strict_types=1);

namespace Stravid\Html\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use Stravid\Html\Tests\Fixtures\Size;

use function Stravid\Html\a;
use function Stravid\Html\button;
use function Stravid\Html\div;
use function Stravid\Html\input;
use function Stravid\Html\li;
use function Stravid\Html\p;
use function Stravid\Html\ul;

final class AttributeTest extends TestCase
{
    public function testRendersNamedArgumentsAsAttributes(): void
    {
        self::assertSame('<a href="/about" title="About us">About</a>', (string) a(href: '/about', title: 'About us')('About'));
    }

    public function testConvertsUnderscoresInNamedArgumentsToHyphens(): void
    {
        self::assertSame(
            '<button aria-label="Close" hx-post="/save" data-user-id="5"></button>',
            (string) button(aria_label: 'Close', hx_post: '/save', data_user_id: 5),
        );
    }

    public function testUsesNamesFromArraysAsIs(): void
    {
        self::assertSame(
            '<div x-on:click="open = true" @click="go()" :class="cls" data_raw="1" données="x"></div>',
            (string) div(['x-on:click' => 'open = true', '@click' => 'go()', ':class' => 'cls', 'data_raw' => 1, 'données' => 'x']),
        );
    }

    public function testRendersBooleanAttributesFromArrays(): void
    {
        self::assertSame('<input type="email" required>', (string) input(['type' => 'email', 'required' => true]));
    }

    public function testIntKeysInAttributeArraysThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Attribute arrays need string keys. For a boolean attribute, use 'required' => true; "
            . 'for text, pass it as a separate argument.',
        );

        input(['type' => 'email', 'required']);
    }

    public function testChildrenInAttributeArraysThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Attribute arrays need string keys. Use 'name' => true for boolean attributes, and pass children as separate arguments.",
        );

        div(['class' => 'card', p('Text')]);
    }

    public function testListOfNamesIsChildren(): void
    {
        self::assertSame('<div>hidden</div>', (string) div(['hidden']));
    }

    public function testEscapesValues(): void
    {
        self::assertSame(
            '<a href="/?a=1&amp;b=2" title="Say &quot;hi&quot; &amp; &apos;bye&apos; &lt;now&gt;"></a>',
            (string) a(href: '/?a=1&b=2', title: 'Say "hi" & \'bye\' <now>'),
        );
    }

    public function testTrueRendersBooleanAttribute(): void
    {
        self::assertSame('<input disabled>', (string) input(disabled: true));
    }

    public function testFalseAndNullLeaveAttributeOut(): void
    {
        self::assertSame('<input type="text">', (string) input(type: 'text', disabled: false, placeholder: null));
    }

    public function testRendersEmptyString(): void
    {
        self::assertSame('<input value="">', (string) input(value: ''));
    }

    public function testConvertsNumbersAndEnumsToStrings(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'text';
            }
        };

        self::assertSame(
            '<input value="0" step="0.5" size="large" placeholder="text">',
            (string) input(value: 0, step: 0.5, size: Size::Large, placeholder: $stringable),
        );
    }

    public function testLastValueWins(): void
    {
        self::assertSame('<input type="email">', (string) input(type: 'text')(type: 'email'));
        self::assertSame('<div id="b"></div>', (string) div(['id' => 'a'], id: 'b'));
    }

    public function testFalseAfterTrueRemovesAttribute(): void
    {
        self::assertSame('<input>', (string) input(disabled: true)(disabled: false));
        self::assertSame('<input>', (string) input(disabled: true)(disabled: null));
        self::assertSame('<input disabled>', (string) input(disabled: false)(disabled: true));
    }

    public function testExpandsArraysIntoPrefixedAttributes(): void
    {
        self::assertSame(
            '<div data-user-id="5" data-role="admin" aria-label="Close" hx-post="/save"></div>',
            (string) div(data: ['user-id' => 5, 'role' => 'admin'], aria: ['label' => 'Close'], hx: ['post' => '/save']),
        );
    }

    public function testExpandedAttributesHandleBooleansAndNull(): void
    {
        self::assertSame(
            '<div data-flag></div>',
            (string) div(data: ['flag' => true, 'off' => false, 'none' => null]),
        );
    }

    public function testExpandsNestedArrays(): void
    {
        self::assertSame('<div hx-on-click="go()"></div>', (string) div(hx: ['on' => ['click' => 'go()']]));
    }

    public function testExpandsListsIntoNumberedAttributes(): void
    {
        self::assertSame('<div data-0="a" data-1="b"></div>', (string) div(data: ['a', 'b']));
    }

    public function testChecksExpandedNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid attribute name 'data-a b'");

        div(data: ['a b' => 1]);
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNamesThrow(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid attribute name '$name'");

        div([$name => 'x']);
    }

    public function testNodeAsValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Attribute 'de' was given a Node. If you meant to pass children, "
            . 'pass a list instead of an associative array by using array_values().',
        );

        // array_map() keeps the string keys, so the result looks like attributes.
        ul(array_map(fn (string $language) => li($language), ['de' => 'German']));
    }

    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'space' => ['a b'],
            'tab' => ["a\tb"],
            'newline' => ["a\nb"],
            'double quote' => ['a"b'],
            'single quote' => ["a'b"],
            'less than' => ['a<b'],
            'greater than' => ['a>b'],
            'slash' => ['a/b'],
            'equals' => ['a=b'],
            'null byte' => ["a\0b"],
            'control character' => ["a\x1fb"],
            'delete' => ["a\x7fb"],
            'injection' => ['onclick="alert(1)" x'],
        ];
    }
}
