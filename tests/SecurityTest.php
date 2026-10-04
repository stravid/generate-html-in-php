<?php

declare(strict_types=1);

namespace Stravid\Html\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Stravid\Html\a;
use function Stravid\Html\div;
use function Stravid\Html\el;
use function Stravid\Html\p;
use function Stravid\Html\script;

/**
 * Escaping and injection. Overlaps with other tests on purpose, to keep the guarantees in one place.
 */
final class SecurityTest extends TestCase
{
    public function testNeutralisesScriptInjectionInText(): void
    {
        self::assertSame(
            '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>',
            (string) p('<script>alert(1)</script>'),
        );
        self::assertSame(
            '<p>&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;</p>',
            (string) p('"><script>alert(1)</script>'),
        );
    }

    public function testEscapesTextInsideScriptElements(): void
    {
        self::assertSame(
            '<script>&lt;/script&gt;&lt;script&gt;alert(1)</script>',
            (string) script('</script><script>alert(1)'),
        );
    }

    #[DataProvider('attributeValueBreakouts')]
    public function testEscapesAttributeValueBreakout(string $value, string $expected): void
    {
        self::assertSame("<a href=\"$expected\"></a>", (string) a(href: $value));
    }

    public function testEscapesClassAndStyleValues(): void
    {
        self::assertSame(
            '<div class="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"></div>',
            (string) div(class: '"><script>alert(1)</script>'),
        );
        self::assertSame(
            '<div style="color: red&quot; onclick=&quot;alert(1)"></div>',
            (string) div(style: ['color' => 'red" onclick="alert(1)']),
        );
    }

    #[DataProvider('attributeNameInjections')]
    public function testAttributeNameInjectionThrows(array $attributes): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid attribute name');

        div($attributes);
    }

    #[DataProvider('elementNameInjections')]
    public function testElementNameInjectionThrows(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid element name');

        el($name);
    }

    public function testSubstitutesInvalidUtf8(): void
    {
        // Without ENT_SUBSTITUTE, htmlspecialchars() returns '' for invalid UTF-8, silently dropping the content.
        self::assertSame("<p>a\u{FFFD}b</p>", (string) p("a\xFFb"));
        self::assertSame("<a title=\"a\u{FFFD}\"></a>", (string) a(title: "a\xC3"));
    }

    #[DataProvider('javaScriptUrls')]
    public function testBlocksJavaScriptUrls(string $url): void
    {
        self::assertSame('<a href="about:blank#blocked"></a>', (string) a(href: $url));
    }

    #[DataProvider('urlAttributes')]
    public function testBlocksJavaScriptUrlsInAllUrlAttributes(string $name): void
    {
        self::assertSame("<a $name=\"about:blank#blocked\"></a>", (string) a([$name => 'javascript:alert(1)']));
    }

    public function testBlocksJavaScriptUrlsAddedThroughInvoke(): void
    {
        self::assertSame('<a href="about:blank#blocked">x</a>', (string) a(href: '/')('x')(href: 'javascript:alert(1)'));
    }

    #[DataProvider('safeUrls')]
    public function testKeepsOtherUrls(string $url): void
    {
        self::assertSame('<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_HTML5) . '"></a>', (string) a(href: $url));
    }

    public function testDoesNotCheckOtherAttributes(): void
    {
        // Not URLs, so the browser never runs them. Everything is still escaped.
        self::assertSame(
            '<a title="javascript:alert(1)" data-href="javascript:alert(1)"></a>',
            (string) a(title: 'javascript:alert(1)', data: ['href' => 'javascript:alert(1)']),
        );
    }

    public static function attributeValueBreakouts(): array
    {
        return [
            'double quote' => ['" onclick="alert(1)', '&quot; onclick=&quot;alert(1)'],
            'single quote' => ["' onclick='alert(1)", '&apos; onclick=&apos;alert(1)'],
            'tag end' => ['"><script>alert(1)</script>', '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;'],
        ];
    }

    public static function javaScriptUrls(): array
    {
        return [
            'plain' => ['javascript:alert(1)'],
            'mixed case' => ['JaVaScRiPt:alert(1)'],
            'leading space' => ['  javascript:alert(1)'],
            'leading control characters' => ["\x00\x01\x1f javascript:alert(1)"],
            'tab inside' => ["java\tscript:alert(1)"],
            'newline inside' => ["java\nscript:alert(1)"],
            'carriage return before colon' => ["javascript\r:alert(1)"],
            'everything' => [" \n JaVa\tScRi\rPt:alert(1)"],
        ];
    }

    public static function urlAttributes(): array
    {
        return [
            'href' => ['href'],
            'src' => ['src'],
            'action' => ['action'],
            'formaction' => ['formaction'],
            'data' => ['data'],
            'xlink:href' => ['xlink:href'],
            'upper case' => ['HREF'],
        ];
    }

    public static function safeUrls(): array
    {
        return [
            'https' => ['https://example.com/?a=1&b=2'],
            'relative path' => ['/path/to/page'],
            'anchor' => ['#section'],
            'mailto' => ['mailto:me@example.com'],
            'tel' => ['tel:+431234567'],
            'path starting with javascript' => ['javascript-guide.html'],
            'javascript: later in the URL' => ['/redirect?to=javascript:alert(1)'],
            'character reference' => ['&#106;avascript:alert(1)'],
        ];
    }

    public static function attributeNameInjections(): array
    {
        return [
            'extra attribute' => [['onclick="alert(1)" x' => 'y']],
            'tag end' => [['x><script>alert(1)</script' => 'y']],
            'equals' => [['onclick=alert(1)' => 'y']],
            'through prefix expansion' => [['data' => ['x" onclick="alert(1)' => 'y']]],
            'through boolean attribute' => [['onclick=alert(1)' => true]],
        ];
    }

    public static function elementNameInjections(): array
    {
        return [
            'extra attribute' => ['div onclick=alert(1)'],
            'tag end' => ['script>alert(1)</script'],
            'image with handler' => ['img src=x onerror=alert(1)'],
        ];
    }
}
