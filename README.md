# Generate HTML in PHP (GHP)

## Rationale
I don't like unnecessary abstractions. For example, I don't see a reason to write SASS / SCSS in the year 2026. My attitude to HTML is the same, just write it directly. But then I read a [blog post titled „Lua can be a really cool HTML templating engine“ and it's corresponding discussion](https://lobste.rs/s/ukar7d/lua_can_be_really_cool_html_templating) and it made me question my beliefs that HTML should not be generated in the applications programming language.

If I use a template engine like Twig I don't actually write HTML directly. I use the Twig syntax which I have to learn. Reusing and composing parts requires me to learn even more special syntax. The worst part is that for my IDE, PhpStorm, passing data from PHP to a Twig template means crossing a boundary where I loose a lot of nice things. No syntax check, no automatic refactoring, no insights into a variable.

By using PHP to generate HTML this all goes away. At least that is the hope. Since all existing solutions that I examined were hopelessly uninspiring and ugly I decided to write my own little library to experiment with that idea. Maybe it turns out I was right all along and there is nothing better than Twig, we will see.

## Installation
The library is a single file without dependencies and needs PHP 8.2 or newer.

### With Composer
Add the repository to your `composer.json` first:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/stravid/generate-html-in-php" }
]
```

```bash
composer require stravid/generate-html-in-php:dev-main
```

Composer loads the library through `vendor/autoload.php`, so there's nothing else to do.

### Copying the file
Copy [`src/html.php`](src/html.php) into your project and load it once:

```php
require_once __DIR__ . '/html.php';
```

If your project uses Composer anyway, you can let it load the file instead by adding it to `autoload.files` in your `composer.json`.

## Usage
Every HTML element is a function. Import the ones you need:

```php
use Stravid\Html\Element;
use Stravid\Html\Node;
use function Stravid\Html\{a, div, document, each, h1, li, main, option, p, select, strong, ul};

echo p('Hello, world!');
// <p>Hello, world!</p>
```

Attributes are named arguments. Call the result again to add children:

```php
echo a(href: '/about', class: 'link')('About us');
// <a href="/about" class="link">About us</a>
```

Text and attribute values are escaped:

```php
echo p('Fish & Chips <3');
// <p>Fish &amp; Chips &lt;3</p>
```

Classes can be conditional:

```php
echo div(class: ['card', 'card--active' => $isActive])(h1('Title'), p('Text'));
// <div class="card card--active"><h1>Title</h1><p>Text</p></div> when $isActive is true
```

Lists of children are flattened, and `null` renders nothing:

```php
echo ul(array_map(fn (string $fruit) => li($fruit), ['Apple', 'Banana']));
// <ul><li>Apple</li><li>Banana</li></ul>

echo p('Hello', $name !== null ? strong(' ', $name) : null);
// <p>Hello</p> when $name is null
```

`each()` works like `array_map()`, but also passes the key and accepts generators and other iterables:

```php
$languages = ['de' => 'German', 'en' => 'English'];

echo select(name: 'language')(each($languages, fn (string $label, string $code) => option(value: $code)($label)));
// <select name="language"><option value="de">German</option><option value="en">English</option></select>
```

Use it instead of `array_map()` for arrays with string keys: `array_map()` keeps the keys, and an array with string keys is treated as attributes.

Components are plain functions:

```php
function card(string $title, string $text): Element
{
    return div(class: 'card')(h1($title), p($text));
}

echo card('News', 'Something happened.');
// <div class="card"><h1>News</h1><p>Something happened.</p></div>
```

Or classes, by implementing `Node`. Its output isn't escaped again when it's used as a child:

```php
final readonly class Card implements Node
{
    public function __construct(private string $title, private string $text)
    {
    }

    public function __toString(): string
    {
        return (string) div(class: 'card')(h1($this->title), p($this->text));
    }
}

echo main(new Card('News', 'Something happened.'));
// <main><div class="card"><h1>News</h1><p>Something happened.</p></div></main>
```

And `document()` builds a complete page:

```php
echo document(title: 'Home', body: [h1('Welcome'), p('Nice to see you.')]);
// <!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Home</title></head><body><h1>Welcome</h1><p>Nice to see you.</p></body></html>
```

## Development
PHPUnit and Infection are included as PHARs in `tools/`. This is required due to the Composer `autoload.files => src/html.php`. Otherwise, PHPUnit would trigger the Composer Autoloader and Infection could never mutate the library. A nice side effect is a mostly empty `composer.json`.

### Running the tests
```bash
composer test
```
Arguments after `--` are passed to PHPUnit, for example `composer test -- --filter ClassTest`. In PhpStorm, set *Settings → PHP → Test Frameworks* to "Path to phpunit.phar" and choose `tools/phpunit.phar`.

### Mutation testing
```bash
composer mutation
```
The report is written to `.infection/infection.html`.

### Updating the tools
```bash
tools/update.sh
```
Downloads the specified version of PHPUnit and Infection. Acts primarily to have a place to set versions and remember the Curl command.

## Prior Art
[Discussion on template engines that triggered me](https://lobste.rs/s/ukar7d/lua_can_be_really_cool_html_templating)  
[HTML Components in pure Go](https://github.com/maragudk/gomponents)  
[HTML package of Elm (a friend of mine says Elm is the best thing after sliced bread)](https://package.elm-lang.org/packages/elm/html/latest/Html)  
[Ben Visness made JSX for Lua](https://bvisness.me/luax/)  
[Use Rust to generate HTML](https://github.com/raffomania/htmf)  
[Established Clojure](https://github.com/weavejester/hiccup) 
and [ClojureScript solutions](https://github.com/teropa/hiccups)  
[PHPX which requires a compile step and thereby going completely against the grain of PHP](https://github.com/attitude/phpx)
