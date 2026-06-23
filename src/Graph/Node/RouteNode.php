<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Graph\Node;

final readonly class RouteNode
{
    public function __construct(
        public string $method,
        public string $uri,
        public ?string $name = null,
    ) {
    }
}
