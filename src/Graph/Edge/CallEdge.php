<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Graph\Edge;

final readonly class CallEdge
{
    public function __construct(
        public string $from,
        public ?string $to,
        public string $type = 'calls',
    ) {
    }
}
