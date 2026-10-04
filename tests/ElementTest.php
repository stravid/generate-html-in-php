<?php

declare(strict_types=1);

namespace Stravid\Html\Tests;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Stravid\Html\area;
use function Stravid\Html\base;
use function Stravid\Html\br;
use function Stravid\Html\col;
use function Stravid\Html\div;
use function Stravid\Html\el;
use function Stravid\Html\embed;
use function Stravid\Html\hr;
use function Stravid\Html\img;
use function Stravid\Html\input;
use function Stravid\Html\link;
use function Stravid\Html\meta;
use function Stravid\Html\p;
use function Stravid\Html\source;
use function Stravid\Html\span;
use function Stravid\Html\track;
use function Stravid\Html\var_;
use function Stravid\Html\wbr;

final class ElementTest extends TestCase
{
    public function testRendersEmptyElement(): void
    {
        self::assertSame('<div></div>', (string) div());
        self::assertSame('<span></span>', (string) span());
        self::assertSame('<p></p>', (string) p());
    }

    #[DataProvider('voidElements')]
    public function testRendersVoidElementWithoutClosingTag(Closure $element, string $name): void
    {
        self::assertSame("<$name>", (string) $element());
    }

    public function testRendersVoidElementWithAttributes(): void
    {
        self::assertSame('<img src="a.png" alt="">', (string) img(src: 'a.png', alt: ''));
    }

    public function testRendersVoidElementCaseInsensitively(): void
    {
        self::assertSame('<BR>', (string) el('BR'));
    }

    #[DataProvider('voidElements')]
    public function testVoidElementWithChildrenThrows(Closure $element, string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("<$name> is a void element and can't have children");

        $element('child');
    }

    public function testVoidElementWithChildrenThrowsCaseInsensitively(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("<BR> is a void element and can't have children");

        el('BR', 'child');
    }

    public function testVoidElementWithChildrenThroughInvokeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("<img> is a void element and can't have children");

        img(src: 'a.png')('child');
    }

    public function testVoidElementIgnoresNullChildren(): void
    {
        self::assertSame('<br>', (string) br(null, false));
    }

    #[DataProvider('customElementNames')]
    public function testElRendersCustomElementNames(string $name): void
    {
        self::assertSame("<$name></$name>", (string) el($name));
    }

    public function testElTakesChildrenAndAttributes(): void
    {
        self::assertSame(
            '<sds-stack size="small">Hello</sds-stack>',
            (string) el('sds-stack', size: 'small')('Hello'),
        );
    }

    #[DataProvider('invalidElementNames')]
    public function testElWithInvalidNameThrows(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid element name '$name'");

        el($name);
    }

    public function testVarRendersVarElement(): void
    {
        self::assertSame('<var>x</var>', (string) var_('x'));
    }

    /**
     * @return array<string, array{Closure, string}>
     */
    public static function voidElements(): array
    {
        return [
            'area' => [area(...), 'area'],
            'base' => [base(...), 'base'],
            'br' => [br(...), 'br'],
            'col' => [col(...), 'col'],
            'embed' => [embed(...), 'embed'],
            'hr' => [hr(...), 'hr'],
            'img' => [img(...), 'img'],
            'input' => [input(...), 'input'],
            'link' => [link(...), 'link'],
            'meta' => [meta(...), 'meta'],
            'source' => [source(...), 'source'],
            'track' => [track(...), 'track'],
            'wbr' => [wbr(...), 'wbr'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function customElementNames(): array
    {
        return [
            'hyphen' => ['sds-stack'],
            'dot' => ['my-app.widget'],
            'underscore' => ['my_app-widget'],
            'colon' => ['x:my-button'],
            'digits' => ['h7-heading2'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidElementNames(): array
    {
        return [
            'empty' => [''],
            'attribute injection' => ['div onclick=alert(1)'],
            'tag end' => ['div>'],
            'self-closing slash' => ['div/'],
            'starts with digit' => ['1div'],
            'starts with hyphen' => ['-div'],
            'non-ASCII' => ['my-élément'],
        ];
    }
}
