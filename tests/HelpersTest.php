<?php

declare(strict_types=1);

namespace Stravid\Html\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Stravid\Html\Node;

use function Stravid\Html\div;
use function Stravid\Html\doctype;
use function Stravid\Html\document;
use function Stravid\Html\each;
use function Stravid\Html\fragment;
use function Stravid\Html\h1;
use function Stravid\Html\li;
use function Stravid\Html\link;
use function Stravid\Html\main;
use function Stravid\Html\option;
use function Stravid\Html\p;
use function Stravid\Html\raw;
use function Stravid\Html\select;
use function Stravid\Html\span;
use function Stravid\Html\text;
use function Stravid\Html\ul;

final class HelpersTest extends TestCase
{
    public function testFragmentRendersChildrenWithoutWrapper(): void
    {
        self::assertSame('<p>a</p>b &amp; c<span>d</span>', (string) fragment(p('a'), 'b & c', [span('d')]));
        self::assertSame('<div><p>a</p><p>b</p></div>', (string) div(fragment(p('a'), p('b'))));
        self::assertSame('', (string) fragment());
    }

    public function testFragmentWithAttributesThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Fragments can't have attributes");

        fragment(p('a'), class: 'x');
    }

    public function testFragmentWithAttributeArrayThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Fragments can't have attributes");

        fragment(['class' => 'x'], p('a'));
    }

    public function testRawOutputsAsIs(): void
    {
        self::assertSame('<b>bold</b> &amp; <i>', (string) raw('<b>bold</b> &amp; <i>'));
        self::assertSame('<p>a<br>b</p>', (string) p('a', raw('<br>'), 'b'));
    }

    public function testTextEscapes(): void
    {
        self::assertSame('&lt;b&gt; &amp; &quot;x&quot; &apos;y&apos;', (string) text('<b> & "x" \'y\''));
        self::assertInstanceOf(Node::class, text('a'));
    }

    public function testEachPassesValueAndKey(): void
    {
        $list = each(['a', 'b'], fn (string $value, int $key) => li("$key: $value"));

        self::assertSame('<ul><li>0: a</li><li>1: b</li></ul>', (string) ul($list));
        self::assertSame('', (string) each([], fn () => li('never')));
    }

    public function testEachWorksWithStringKeys(): void
    {
        $languages = ['de' => 'German', 'en' => 'English'];

        self::assertSame(
            '<select name="language"><option value="de">German</option><option value="en">English</option></select>',
            (string) select(name: 'language')(
                each($languages, fn (string $label, string $code) => option(value: $code)($label)),
            ),
        );
    }

    public function testEachWorksWithGenerators(): void
    {
        $generator = (function () {
            yield 'x' => 1;
            yield 'y' => 2;
        })();

        self::assertSame(
            '<ul><li>x: 1</li><li>y: 2</li></ul>',
            (string) ul(each($generator, fn (int $value, string $key) => li("$key: $value"))),
        );
    }

    public function testDoctype(): void
    {
        self::assertSame('<!doctype html>', (string) doctype());
    }

    public function testDocumentRendersPageStructure(): void
    {
        self::assertSame(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Hi &amp; bye</title></head><body></body></html>',
            (string) document(title: 'Hi & bye'),
        );
        self::assertStringContainsString('<html lang="de">', (string) document(title: 'Hallo', lang: 'de'));
    }

    public function testDocumentIncludesDescriptionOnlyWhenGiven(): void
    {
        self::assertStringNotContainsString('name="description"', (string) document(title: 'Hi'));
        self::assertStringContainsString(
            '<title>Hi</title><meta name="description" content="A &amp; B"></head>',
            (string) document(title: 'Hi', description: 'A & B'),
        );
    }

    public function testDocumentPlacesHeadAndBodyContent(): void
    {
        self::assertSame(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Hi</title><link rel="stylesheet" href="/app.css"></head>'
            . '<body><h1>Hi</h1><p>Text</p></body></html>',
            (string) document(
                title: 'Hi',
                head: link(rel: 'stylesheet', href: '/app.css'),
                body: [h1('Hi'), p('Text')],
            ),
        );
    }

    public function testDocumentPassesBodyAttributes(): void
    {
        self::assertStringContainsString(
            '<body class="dark"><main>Content</main></body>',
            (string) document(title: 'Hi', body: [['class' => 'dark'], main('Content')]),
        );
    }
}
