<?php

/*
 * Copyright (c) 2026 David Strauß
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */

declare(strict_types=1);

namespace Stravid\Html;

use BackedEnum;
use InvalidArgumentException;
use Stringable;
use Traversable;

/**
 * Anything that renders to HTML. Unlike other Stringables a node is not escaped when used as a child.
 */
interface Node extends Stringable
{
}

final readonly class Element implements Node
{
    private const VOID = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    /**
     * Attributes where a `javascript:` value can result in running JavaScript.
     */
    private const URL_ATTRIBUTES = [
        'action' => true,
        'data' => true,
        'formaction' => true,
        'href' => true,
        'src' => true,
        'xlink:href' => true,
    ];

    /**
     * Replacement value for URLs that begin with `javascript:`. A blank page instead of an exception, so one bad URL in user data doesn't
     * break the whole page; the #blocked makes it obvious in the browser why the link goes nowhere.
     */
    private const BLOCKED_URL = 'about:blank#blocked';

    /**
     * @param array<string, string|true> $attributes
     * @param list<Node|string> $children Strings are unescaped text.
     */
    private function __construct(
        public string $name,
        public array $attributes,
        public array $children,
    ) {
    }

    /**
     * @param array<array-key, mixed> $args Children and attributes, as passed to the element functions.
     */
    public static function make(string $name, array $args): self
    {
        if (!preg_match('/^[a-z][a-z0-9._:-]*$/i', $name)) {
            throw new InvalidArgumentException("Invalid element name '$name'");
        }

        [$attributes, $children] = self::parseArguments($args);

        if ($children !== [] && isset(self::VOID[strtolower($name)])) {
            throw new InvalidArgumentException("<$name> is a void element and can't have children");
        }

        return new self($name, $attributes, $children);
    }

    /**
     * Returns a copy with more children and attributes, so attributes can come first:
     * div(class: 'card')(h1('Title'), p('Text')). Also works on existing elements: $button(class: 'primary').
     */
    public function __invoke(mixed ...$args): self
    {
        // Rebuild from scratch instead of mutating, as the class is readonly. The existing attributes
        // go in as an associative array, so they're attributes again (and class/style merge with new
        // ones); existing children come before new ones. Named arguments in $args keep their string
        // keys through the spread.
        //
        // ...$this->children would be the natural way to write this, but it's left out on purpose: the
        // children go in as one nested list, which is flattened like any other list, so the spread makes
        // no difference. Infection would report it as a surviving mutant, as no test can tell it apart.
        return self::make($this->name, [$this->attributes, $this->children, ...$args]);
    }

    public function __toString(): string
    {
        $html = '<' . $this->name;
        foreach ($this->attributes as $name => $value) {
            $html .= $value === true ? " $name" : " $name=\"" . escape($value) . '"';
        }
        $html .= '>';

        if (isset(self::VOID[strtolower($this->name)])) {
            return $html;
        }

        foreach ($this->children as $child) {
            $html .= $child instanceof Node ? $child : escape($child);
        }

        return $html . '</' . $this->name . '>';
    }

    /**
     * Splits arguments into attributes and a flat list of children.
     *
     * @internal
     * @param iterable<array-key, mixed> $args
     * @return array{array<string, string|true>, list<Node|string>}
     */
    public static function parseArguments(iterable $args): array
    {
        $attributes = [];
        $children = [];
        self::addArguments($args, $attributes, $children);

        // Duplicate CSS classes can be removed but not CSS properties in the `style` attribute since they can overwrite each other.
        foreach (['class' => ' ', 'style' => '; '] as $name => $glue) {
            if (isset($attributes[$name])) {
                $values = $name === 'class' ? array_unique($attributes[$name]) : $attributes[$name];
                $attributes[$name] = $values === [] ? null : implode($glue, $values);
            }
        }

        return [array_filter($attributes, fn ($value) => $value !== null), $children];
    }

    /**
     * Decides for each argument whether it's an attribute or a child. The key decides first, then the type,
     * so the same string is a child in div('Hello') and an attribute value in div(class: 'Hello').
     *
     *  - String key: a named argument (the variadic keeps the name as the key), so an attribute.
     *  - Node: a child, rendered as-is.
     *  - Array with string keys: attributes. array_is_list() tells these apart from lists: it's true when
     *    the keys are exactly 0, 1, 2, … (or the array is empty). Int keys mixed in throw.
     *  - List or other iterable: flattened, each item going through these rules again.
     *  - null and booleans: skipped, so `$x ? p('y') : null` works.
     *  - Anything else: text, escaped when rendered.
     *
     * Adds to the arrays passed by reference instead of returning new ones, so items of nested lists end
     * up in the same flat list of children when it recurses.
     */
    private static function addArguments(iterable $args, array &$attributes, array &$children): void
    {
        foreach ($args as $key => $arg) {
            if (is_string($key)) {
                // Named argument. Underscores become hyphens, as hyphens aren't allowed in PHP names.
                self::attribute($attributes, str_replace('_', '-', $key), $arg);
            } elseif ($arg instanceof Node) {
                $children[] = $arg;
            } elseif (is_array($arg) && !array_is_list($arg)) {
                // Names are used verbatim, without the underscore conversion since there is no limitation from PHP on arary keys.
                foreach ($arg as $name => $value) {
                    if (is_int($name)) {
                        throw self::intKeyInAttributes($value);
                    }
                    self::attribute($attributes, $name, $value);
                }
            } elseif (is_array($arg)) {
                // A list has only integer keys, so recursing can't mistake items for named arguments.
                self::addArguments($arg, $attributes, $children);
            } elseif ($arg instanceof Traversable) {
                // Generators may yield string keys, so drop all keys to always treat items as children.
                self::addArguments(iterator_to_array($arg, false), $attributes, $children);
            } elseif ($arg !== null && !is_bool($arg)) {
                $children[] = self::stringify($arg);
            }
        }
    }

    /**
     * Attribute arrays need a name for every value, just like named arguments. Bare names like
     * ['type' => 'email', 'required'] aren't supported: if they were, ['required'] would look like it should
     * work too, but it's a list and so a text child. The library can't tell the two apart, so there's
     * only one way to write a boolean attribute: 'required' => true.
     */
    private static function intKeyInAttributes(mixed $value): InvalidArgumentException
    {
        // A string is either a bare boolean attribute name (['required']) or text that ended up in the array
        // (['class' => 'card', 'Hello']), so the hint names both. Anything else is most likely a child, like
        // p('Text'): it's left out of the message, as an element would be rendered into it as HTML.
        $hint = is_string($value)
            ? "For a boolean attribute, use '$value' => true; for text, pass it as a separate argument."
            : 'Use \'name\' => true for boolean attributes, and pass children as separate arguments.';

        return new InvalidArgumentException("Attribute arrays need string keys. $hint");
    }

    /**
     * Adds one attribute, from a named argument or an entry of an attributes array.
     *
     * Names:
     *
     *     a(href: '/about')                            // <a href="/about">
     *     button(aria_label: 'Close')                  // <button aria-label="Close">: named arguments turn _ into -
     *     div(['x-on:click' => 'go()'])                // names from arrays are used as-is, for names PHP doesn't allow
     *     input(['type' => 'email', 'required' => true])  // <input type="email" required>: int keys throw
     *
     * Values:
     *
     *     a(href: '/?a=1&b=2')                         // <a href="/?a=1&amp;b=2">: values are escaped
     *     input(disabled: true)                        // <input disabled>
     *     input(disabled: false, placeholder: null)    // <input>: false and null leave the attribute out
     *     input(value: '')                             // <input value="">: '' is kept
     *     input(value: 0, size: Size::Large)           // <input value="0" size="large">: numbers, backed enums
     *                                                  // and Stringables are turned into strings
     *     div(data: ['user-id' => 5, 'flag' => true])  // <div data-user-id="5" data-flag>: arrays expand
     *                                                  // into prefixed attributes, each handled like this one
     *     a(href: 'javascript:alert(1)')               // <a href="about:blank#blocked">: see isJavaScriptUrl()
     *
     * class and style merge when given more than once; see classes() and styles(). For all other attributes,
     * the last value wins, so input(type: 'text')(type: 'email') is <input type="email">, and
     * input(disabled: true)(disabled: false) is <input>.
     *
     * Throws an InvalidArgumentException for invalid names and for Nodes as values.
     */
    private static function attribute(array &$attributes, string $name, mixed $value): void
    {
        // Values are escaped when rendered, but names can't be escaped and are output as-is. Names from arrays
        // can come from data, so without this check ['onclick="alert(1)" x' => 'y'] would inject an attribute.
        // Rejects what could end the name early: whitespace, control characters, quotes, <, >, / and =.
        // This is the HTML standard's definition of attribute names, plus <, so names like @click, x-on:click
        // and :class still work. Not checked are Unicode noncharacters and controls in \x80-\x9f, as neither
        // can break out of an attribute.
        // See https://html.spec.whatwg.org/multipage/syntax.html#syntax-attribute-name
        if (!preg_match('/^[^\s\x00-\x1f\x7f"\'<>\/=]+$/', $name)) {
            throw new InvalidArgumentException("Invalid attribute name '$name'");
        }

        // Attribute values are never Nodes, so this is almost always an accidental usage of array_map() over an array
        // with string keys: it keeps the keys, so the result looks like attributes instead of a list of children.
        if ($value instanceof Node) {
            throw new InvalidArgumentException(
                "Attribute '$name' was given a Node. If you meant to pass children, "
                . 'pass a list instead of an associative array by using array_values().',
            );
        }

        if ($name === 'class' || $name === 'style') {
            // Kept as lists while parsing so repeated values merge, joined in parseArguments().
            $values = $name === 'class' ? self::classes($value) : self::styles($value);
            $attributes[$name] = [...$attributes[$name] ?? [], ...$values];
        } elseif (is_array($value)) {
            foreach ($value as $suffix => $item) {
                self::attribute($attributes, "$name-$suffix", $item);
            }
        } else {
            $value = $value === true ? true : self::stringify($value);
            if (is_string($value) && isset(self::URL_ATTRIBUTES[strtolower($name)]) && self::isJavaScriptUrl($value)) {
                $value = self::BLOCKED_URL;
            }
            $attributes[$name] = $value;
        }
    }

    /**
     * Check if a URL would run JavaScript. Escaping keeps a value inside its attribute, but javascript:alert(1)
     * has nothing to escape, so a user's "website" in a(href: $user->website) would run script on click.
     *
     * Only `javascript:` is checked: it's the only scheme that still runs script in modern browsers.
     */
    private static function isJavaScriptUrl(string $url): bool
    {
        // Browsers strip leading control characters and spaces, remove tabs and newlines anywhere, and treat
        // schemes case-insensitively, so " JaVa\tScript:alert(1)" is a javascript: URL too. Character references
        // like &#106; don't matter: escaping turns & into &amp;, so the browser never decodes them.
        // See https://url.spec.whatwg.org/#concept-basic-url-parser
        $url = str_replace(["\t", "\n", "\r"], '', ltrim($url, "\x00..\x20"));

        return stripos($url, 'javascript:') === 0;
    }

    /**
     * Turns a class value into a list of class names that can be joined with " " in parseArguments().
     *
     *     div(class: [
     *         'btn',                                 // int key: a class name
     *         'btn--active'   => $active,            // string key: included when the value is truthy
     *         'btn--disabled' => $disabled,
     *         $active ? 'is-active' : null,          // null, false and '' are skipped
     *         ['icon', 'icon--left' => true],        // int key: a nested array, handled like this one
     *     ])
     *
     * renders class="btn btn--active is-active icon icon--left" when $active is true and $disabled is false.
     * A plain string works too and is split on whitespace, so a key can hold several classes:
     * ['btn btn--active' => $active]. class can be given more than once; everything is merged and
     * duplicates are removed:
     *
     *     div(class: 'btn card')(class: ['btn', 'card--wide'])  // class="btn card card--wide"
     *
     * Values of string keys are conditions, so they are checked for truthiness, like in an if: 0, '0', '', null
     * and [] leave the class out too.
     *
     * @return list<string>
     */
    private static function classes(mixed $value): array
    {
        if (!is_array($value)) {
            return preg_split('/\s+/', self::stringify($value) ?? '', -1, PREG_SPLIT_NO_EMPTY);
        }

        $classes = [];
        foreach ($value as $key => $item) {
            if (is_int($key)) {
                array_push($classes, ...self::classes($item));
            } elseif ($item) {
                array_push($classes, ...self::classes($key));
            }
        }

        return $classes;
    }

    /**
     * Turns a style value into a list of declarations that can be joined with "; " in parseArguments().
     *
     *     div(style: [
     *         'color'   => 'red',                    // property => value
     *         'display' => $hidden ? 'none' : null,  // null, false and '' leave the property out
     *         'width'   => 0,                        // 0 is kept
     *         'margin: 0; padding: 0',               // int key: a style string, used as-is
     *         ['height' => '50%'],                   // int key: a nested array, handled like this one
     *     ])
     *
     * renders style="color: red; width: 0; margin: 0; padding: 0; height: 50%" when $hidden is false.
     * A plain string works too, and style can be given more than once; everything is merged:
     *
     *     div(style: 'color: red;')(style: ['margin' => 0])  // style="color: red; margin: 0"
     *
     * Only the boolean false is left out. The string 'false' is kept like any other string
     * ('display' => 'false' renders display: false). true throws an InvalidArgumentException,
     * as it has no meaning as a CSS value.
     *
     * @return list<string>
     */
    private static function styles(mixed $value): array
    {
        if (!is_array($value)) {
            // trim()'s default whitespace plus ";", as styles are joined with "; " and we don't want to end up with ";; ".
            $style = trim(self::stringify($value) ?? '', " \n\r\t\v\0;");
            return $style === '' ? [] : [$style];
        }

        $styles = [];
        foreach ($value as $property => $item) {
            if (is_int($property)) {
                array_push($styles, ...self::styles($item));
            } else {
                $item = self::stringify($item);
                if ($item !== null && $item !== '') {
                    $styles[] = "$property: $item";
                }
            }
        }

        return $styles;
    }

    /**
     * Tries to turn a value into a string.
     */
    private static function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null, $value === false => null,
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof Stringable => (string) $value,
            default => throw new InvalidArgumentException('Can\'t render a value of type ' . get_debug_type($value)),
        };
    }
}

/**
 * A list of nodes without a wrapping parent element.
 */
final readonly class Fragment implements Node
{
    /** @var list<Node|string> Strings are unescaped text. */
    public array $children;

    public function __construct(mixed ...$children)
    {
        [$attributes, $this->children] = Element::parseArguments($children);

        if ($attributes !== []) {
            throw new InvalidArgumentException('Fragments can\'t have attributes');
        }
    }

    public function __toString(): string
    {
        $html = '';
        foreach ($this->children as $child) {
            $html .= $child instanceof Node ? $child : escape($child);
        }

        return $html;
    }
}

/**
 * HTML that is output as-is. Never pass user input to this.
 */
final readonly class Raw implements Node
{
    public function __construct(public string $html)
    {
    }

    public function __toString(): string
    {
        return $this->html;
    }
}

function escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * Any element, e.g. for custom elements: el('sl-button', 'Save', variant: 'primary').
 */
function el(string $tagName, mixed ...$args): Element
{
    return Element::make($tagName, $args);
}

function fragment(mixed ...$children): Fragment
{
    return new Fragment(...$children);
}

function text(string $text): Fragment
{
    return new Fragment($text);
}

function raw(string $html): Raw
{
    return new Raw($html);
}

/**
 * Turns each item into a node: ul(each($users, fn (User $user) => li($user->name))).
 *
 * array_map() is fine for plain lists, but has three problems when rendering:
 *
 *  - It keeps the keys of the input. Mapping over an array with string keys (['de' => 'German']) returns
 *    an array with string keys, which element functions treat as attributes, not children.
 *  - It only accepts arrays. Generators, PDOStatement, Doctrine collections and other Traversables would need
 *    iterator_to_array() first.
 *  - The callback only gets the value. To get the key too, you need array_map($fn, $array, array_keys($array)).
 *
 * each() takes any iterable, passes the value and the key to the callback, and returns a Fragment.
 * A Fragment is a single node, so the original keys can never be mistaken for attributes:
 *
 *     select(name: 'language')(each($languages, fn (string $label, string $code) => option(value: $code)($label)))
 */
function each(iterable $items, callable $callback): Fragment
{
    $nodes = [];
    foreach ($items as $key => $item) {
        $nodes[] = $callback($item, $key);
    }

    return new Fragment($nodes);
}

function doctype(): Raw
{
    return new Raw('<!doctype html>');
}

/**
 * Helper function to generate a complete HTML5 page.
 *
 * @param mixed $head takes anything an Element would take.
 * @param mixed $body takes anything an Element would take.
 */
function document(
    string $title,
    mixed $head = null,
    mixed $body = null,
    string $lang = 'en',
    ?string $description = null,
): Fragment {
    return new Fragment(
        doctype(),
        html(lang: $lang)(
            head(
                meta(charset: 'utf-8'),
                meta(name: 'viewport', content: 'width=device-width, initial-scale=1'),
                title($title),
                $description === null ? null : meta(name: 'description', content: $description),
                $head,
            ),
            body($body),
        ),
    );
}

// List of element functions for all known HTML elements.
// <var> is the exception since `var` is a reserved keyword in PHP.
// The element function for <var> is called `var_`.
function a(mixed ...$args): Element { return Element::make('a', $args); }
function abbr(mixed ...$args): Element { return Element::make('abbr', $args); }
function address(mixed ...$args): Element { return Element::make('address', $args); }
function area(mixed ...$args): Element { return Element::make('area', $args); }
function article(mixed ...$args): Element { return Element::make('article', $args); }
function aside(mixed ...$args): Element { return Element::make('aside', $args); }
function audio(mixed ...$args): Element { return Element::make('audio', $args); }
function b(mixed ...$args): Element { return Element::make('b', $args); }
function base(mixed ...$args): Element { return Element::make('base', $args); }
function bdi(mixed ...$args): Element { return Element::make('bdi', $args); }
function bdo(mixed ...$args): Element { return Element::make('bdo', $args); }
function blockquote(mixed ...$args): Element { return Element::make('blockquote', $args); }
function body(mixed ...$args): Element { return Element::make('body', $args); }
function br(mixed ...$args): Element { return Element::make('br', $args); }
function button(mixed ...$args): Element { return Element::make('button', $args); }
function canvas(mixed ...$args): Element { return Element::make('canvas', $args); }
function caption(mixed ...$args): Element { return Element::make('caption', $args); }
function cite(mixed ...$args): Element { return Element::make('cite', $args); }
function code(mixed ...$args): Element { return Element::make('code', $args); }
function col(mixed ...$args): Element { return Element::make('col', $args); }
function colgroup(mixed ...$args): Element { return Element::make('colgroup', $args); }
function data(mixed ...$args): Element { return Element::make('data', $args); }
function datalist(mixed ...$args): Element { return Element::make('datalist', $args); }
function dd(mixed ...$args): Element { return Element::make('dd', $args); }
function del(mixed ...$args): Element { return Element::make('del', $args); }
function details(mixed ...$args): Element { return Element::make('details', $args); }
function dfn(mixed ...$args): Element { return Element::make('dfn', $args); }
function dialog(mixed ...$args): Element { return Element::make('dialog', $args); }
function div(mixed ...$args): Element { return Element::make('div', $args); }
function dl(mixed ...$args): Element { return Element::make('dl', $args); }
function dt(mixed ...$args): Element { return Element::make('dt', $args); }
function em(mixed ...$args): Element { return Element::make('em', $args); }
function embed(mixed ...$args): Element { return Element::make('embed', $args); }
function fieldset(mixed ...$args): Element { return Element::make('fieldset', $args); }
function figcaption(mixed ...$args): Element { return Element::make('figcaption', $args); }
function figure(mixed ...$args): Element { return Element::make('figure', $args); }
function footer(mixed ...$args): Element { return Element::make('footer', $args); }
function form(mixed ...$args): Element { return Element::make('form', $args); }
function h1(mixed ...$args): Element { return Element::make('h1', $args); }
function h2(mixed ...$args): Element { return Element::make('h2', $args); }
function h3(mixed ...$args): Element { return Element::make('h3', $args); }
function h4(mixed ...$args): Element { return Element::make('h4', $args); }
function h5(mixed ...$args): Element { return Element::make('h5', $args); }
function h6(mixed ...$args): Element { return Element::make('h6', $args); }
function head(mixed ...$args): Element { return Element::make('head', $args); }
function header(mixed ...$args): Element { return Element::make('header', $args); }
function hgroup(mixed ...$args): Element { return Element::make('hgroup', $args); }
function hr(mixed ...$args): Element { return Element::make('hr', $args); }
function html(mixed ...$args): Element { return Element::make('html', $args); }
function i(mixed ...$args): Element { return Element::make('i', $args); }
function iframe(mixed ...$args): Element { return Element::make('iframe', $args); }
function img(mixed ...$args): Element { return Element::make('img', $args); }
function input(mixed ...$args): Element { return Element::make('input', $args); }
function ins(mixed ...$args): Element { return Element::make('ins', $args); }
function kbd(mixed ...$args): Element { return Element::make('kbd', $args); }
function label(mixed ...$args): Element { return Element::make('label', $args); }
function legend(mixed ...$args): Element { return Element::make('legend', $args); }
function li(mixed ...$args): Element { return Element::make('li', $args); }
function link(mixed ...$args): Element { return Element::make('link', $args); }
function main(mixed ...$args): Element { return Element::make('main', $args); }
function map(mixed ...$args): Element { return Element::make('map', $args); }
function mark(mixed ...$args): Element { return Element::make('mark', $args); }
function menu(mixed ...$args): Element { return Element::make('menu', $args); }
function meta(mixed ...$args): Element { return Element::make('meta', $args); }
function meter(mixed ...$args): Element { return Element::make('meter', $args); }
function nav(mixed ...$args): Element { return Element::make('nav', $args); }
function noscript(mixed ...$args): Element { return Element::make('noscript', $args); }
function object(mixed ...$args): Element { return Element::make('object', $args); }
function ol(mixed ...$args): Element { return Element::make('ol', $args); }
function optgroup(mixed ...$args): Element { return Element::make('optgroup', $args); }
function option(mixed ...$args): Element { return Element::make('option', $args); }
function output(mixed ...$args): Element { return Element::make('output', $args); }
function p(mixed ...$args): Element { return Element::make('p', $args); }
function picture(mixed ...$args): Element { return Element::make('picture', $args); }
function pre(mixed ...$args): Element { return Element::make('pre', $args); }
function progress(mixed ...$args): Element { return Element::make('progress', $args); }
function q(mixed ...$args): Element { return Element::make('q', $args); }
function rp(mixed ...$args): Element { return Element::make('rp', $args); }
function rt(mixed ...$args): Element { return Element::make('rt', $args); }
function ruby(mixed ...$args): Element { return Element::make('ruby', $args); }
function s(mixed ...$args): Element { return Element::make('s', $args); }
function samp(mixed ...$args): Element { return Element::make('samp', $args); }
function script(mixed ...$args): Element { return Element::make('script', $args); }
function search(mixed ...$args): Element { return Element::make('search', $args); }
function section(mixed ...$args): Element { return Element::make('section', $args); }
function select(mixed ...$args): Element { return Element::make('select', $args); }
function slot(mixed ...$args): Element { return Element::make('slot', $args); }
function small(mixed ...$args): Element { return Element::make('small', $args); }
function source(mixed ...$args): Element { return Element::make('source', $args); }
function span(mixed ...$args): Element { return Element::make('span', $args); }
function strong(mixed ...$args): Element { return Element::make('strong', $args); }
function style(mixed ...$args): Element { return Element::make('style', $args); }
function sub(mixed ...$args): Element { return Element::make('sub', $args); }
function summary(mixed ...$args): Element { return Element::make('summary', $args); }
function sup(mixed ...$args): Element { return Element::make('sup', $args); }
function svg(mixed ...$args): Element { return Element::make('svg', $args); }
function table(mixed ...$args): Element { return Element::make('table', $args); }
function tbody(mixed ...$args): Element { return Element::make('tbody', $args); }
function td(mixed ...$args): Element { return Element::make('td', $args); }
function template(mixed ...$args): Element { return Element::make('template', $args); }
function textarea(mixed ...$args): Element { return Element::make('textarea', $args); }
function tfoot(mixed ...$args): Element { return Element::make('tfoot', $args); }
function th(mixed ...$args): Element { return Element::make('th', $args); }
function thead(mixed ...$args): Element { return Element::make('thead', $args); }
function time(mixed ...$args): Element { return Element::make('time', $args); }
function title(mixed ...$args): Element { return Element::make('title', $args); }
function tr(mixed ...$args): Element { return Element::make('tr', $args); }
function track(mixed ...$args): Element { return Element::make('track', $args); }
function u(mixed ...$args): Element { return Element::make('u', $args); }
function ul(mixed ...$args): Element { return Element::make('ul', $args); }
function var_(mixed ...$args): Element { return Element::make('var', $args); }
function video(mixed ...$args): Element { return Element::make('video', $args); }
function wbr(mixed ...$args): Element { return Element::make('wbr', $args); }
