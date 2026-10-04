<?php

declare(strict_types=1);

namespace Stravid\Html\Tests;

use PHPUnit\Framework\TestCase;
use Stravid\Html\Element;
use Stravid\Html\Fragment;
use Stravid\Html\Tests\Fixtures\Card;

use function Stravid\Html\button;
use function Stravid\Html\dd;
use function Stravid\Html\dl;
use function Stravid\Html\dt;
use function Stravid\Html\each;
use function Stravid\Html\fragment;
use function Stravid\Html\main;
use function Stravid\Html\p;

final class ComponentTest extends TestCase
{
    public function testRendersCustomNodeClassWithoutEscaping(): void
    {
        self::assertSame(
            '<main><div class="card"><h2>Title</h2><p>Body</p></div></main>',
            (string) main(new Card('Title', p('Body'))),
        );
    }

    public function testCustomNodeClassEscapesItsOwnContent(): void
    {
        self::assertSame(
            '<div class="card"><h2>&lt;b&gt;</h2>&lt;i&gt;</div>',
            (string) new Card('<b>', '<i>'),
        );
    }

    public function testFunctionReturningElementWorksAsComponent(): void
    {
        $button = fn (string $label, string $variant = 'primary'): Element
            => button(class: ['btn', "btn--$variant"], type: 'button')($label);

        self::assertSame(
            '<main><button class="btn btn--primary" type="button">Save</button>'
            . '<button class="btn btn--secondary" type="button">Cancel</button></main>',
            (string) main($button('Save'), $button('Cancel', variant: 'secondary')),
        );
    }

    public function testCallerCanCustomiseReturnedElement(): void
    {
        $button = fn (string $label): Element => button(class: 'btn', type: 'button')($label);

        self::assertSame(
            '<button class="btn btn--wide" type="submit">Save</button>',
            (string) $button('Save')(class: 'btn--wide', type: 'submit'),
        );
    }

    public function testFunctionReturningFragmentRendersSeveralNodes(): void
    {
        $term = fn (string $term, string $definition): Fragment => fragment(dt($term), dd($definition));

        self::assertSame(
            '<dl><dt>PHP</dt><dd>A language</dd><dt>HTML</dt><dd>A markup language</dd></dl>',
            (string) dl(
                each(['PHP' => 'A language', 'HTML' => 'A markup language'], fn (string $d, string $t) => $term($t, $d)),
            ),
        );
    }
}
