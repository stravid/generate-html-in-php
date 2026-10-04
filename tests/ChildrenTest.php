<?php

declare(strict_types=1);

namespace Stravid\Html\Tests;

use ArrayIterator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Stravid\Html\Tests\Fixtures\Priority;
use Stravid\Html\Tests\Fixtures\Size;
use Stravid\Html\Tests\Fixtures\Status;

use function Stravid\Html\div;
use function Stravid\Html\li;
use function Stravid\Html\p;
use function Stravid\Html\span;
use function Stravid\Html\ul;

final class ChildrenTest extends TestCase
{
    public function testEscapesText(): void
    {
        self::assertSame(
            '<p>&lt;b&gt; &amp; &quot;x&quot; &apos;y&apos;</p>',
            (string) p('<b> & "x" \'y\''),
        );
    }

    public function testEscapesAlreadyEscapedTextAgain(): void
    {
        self::assertSame('<p>&amp;amp;</p>', (string) p('&amp;'));
    }

    public function testRendersScalarsEnumsAndStringablesAsEscapedText(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return '<i>';
            }
        };

        self::assertSame('<span>42</span>', (string) span(42));
        self::assertSame('<span>1.5</span>', (string) span(1.5));
        self::assertSame('<span>large</span>', (string) span(Size::Large));
        self::assertSame('<span>2</span>', (string) span(Priority::High));
        self::assertSame('<span>&lt;i&gt;</span>', (string) span($stringable));
    }

    public function testRendersZeroButNotEmptyString(): void
    {
        self::assertSame('<span>0</span>', (string) span(0));
        self::assertSame('<span>0</span>', (string) span('0'));
        self::assertSame('<span></span>', (string) span(''));
    }

    public function testRendersNodesWithoutEscaping(): void
    {
        self::assertSame('<p>a <span>b</span></p>', (string) p('a ', span('b')));
        self::assertSame('<p><span>&lt;</span></p>', (string) p(span('<')));
    }

    public function testFlattensLists(): void
    {
        self::assertSame('<ul><li>a</li><li>b</li></ul>', (string) ul([li('a'), li('b')]));
        self::assertSame(
            '<ul><li>a</li><li>b</li><li>c</li><li>d</li></ul>',
            (string) ul(li('a'), [li('b'), li('c')], li('d')),
        );
        self::assertSame('<ul></ul>', (string) ul([]));
    }

    public function testFlattensNestedLists(): void
    {
        self::assertSame(
            '<div><p>a</p><p>b</p>c<p>d</p></div>',
            (string) div([p('a'), [p('b'), ['c', null, [p('d')]]]]),
        );
    }

    public function testFlattensTraversablesIgnoringKeys(): void
    {
        $generator = (function () {
            yield 'first' => li('a');
            yield 'second' => [li('b'), li('c')];
        })();

        self::assertSame('<ul><li>a</li><li>b</li><li>c</li></ul>', (string) ul($generator));
        self::assertSame(
            '<ul><li>a</li><li>b</li></ul>',
            (string) ul(new ArrayIterator(['x' => li('a'), 'y' => li('b')])),
        );
    }

    public function testSkipsNullAndBooleans(): void
    {
        $loggedIn = false;

        self::assertSame('<p>ab</p>', (string) p('a', null, true, false, 'b'));
        self::assertSame('<p>Welcome</p>', (string) p('Welcome', $loggedIn ? span(' back') : null));
    }

    #[DataProvider('unsupportedValues')]
    public function testUnsupportedTypesThrow(mixed $value, string $type): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Can't render a value of type $type");

        p($value);
    }

    public static function unsupportedValues(): array
    {
        return [
            'object' => [new stdClass(), 'stdClass'],
            'unit enum' => [Status::Active, Status::class],
            'closure' => [fn () => 'x', 'Closure'],
        ];
    }
}
