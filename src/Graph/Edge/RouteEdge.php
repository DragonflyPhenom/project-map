<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Graph\Edge;

final readonly class RouteEdge
{
    public function __construct(
        public string $route,
        public string $controllerMethod,
    ) {
    }
}
