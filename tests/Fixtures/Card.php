<?php

declare(strict_types=1);

namespace Stravid\Html\Tests\Fixtures;

use Stravid\Html\Node;

use function Stravid\Html\div;
use function Stravid\Html\h2;

final readonly class Card implements Node
{
    public function __construct(
        private string $title,
        private mixed $body = null,
    ) {
    }

    public function __toString(): string
    {
        return (string) div(class: 'card')(h2($this->title), $this->body);
    }
}
