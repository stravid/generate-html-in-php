# Generate HTML in PHP (GHP)

## Rationale
I don't like unnecessary abstractions. For example, I don't see a reason to write SASS / SCSS in the year 2026. My attitude to HTML is the same, just write it directly. But then I read a [blog post titled „Lua can be a really cool HTML templating engine“ and it's corresponding discussion](https://lobste.rs/s/ukar7d/lua_can_be_really_cool_html_templating) and it made me question my beliefs that HTML should not be generated in the applications programming language.

If I use a template engine like Twig I don't actually write HTML directly. I use the Twig syntax which I have to learn. Reusing and composing parts requires me to learn even more special syntax. The worst part is that for my IDE, PhpStorm, passing data from PHP to a Twig template means crossing a boundary where I loose a lot of nice things. No syntax check, no automatic refactoring, no insights into a variable.

By using PHP to generate HTML this all goes away. At least that is the hope. Since all existing solutions that I examined were hopelessly uninspiring and ugly I decided to write my own little library to experiment with that idea. Maybe it turns out I was right all along and there is nothing better than Twig, we will see.

## Prior Art
[Discussion on template engines that triggered me](https://lobste.rs/s/ukar7d/lua_can_be_really_cool_html_templating)  
[HTML Components in pure Go](https://github.com/maragudk/gomponents)  
[HTML package of Elm (a friend of mine says Elm is the best thing after sliced bread)](https://package.elm-lang.org/packages/elm/html/latest/Html)  
[Ben Visness made JSX for Lua](https://bvisness.me/luax/)  
[Use Rust to generate HTML](https://github.com/raffomania/htmf)  
[Established Clojure](https://github.com/weavejester/hiccup) 
and [ClojureScript solutions](https://github.com/teropa/hiccups)  
[PHPX which requires a compile step and thereby going completely against the grain of PHP](https://github.com/attitude/phpx)
