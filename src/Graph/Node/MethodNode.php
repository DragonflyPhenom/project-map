<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Graph\Node;

final readonly class MethodNode
{
    public function __construct(
        public string $class,
        public string $name,
        public string $visibility,
    ) {
    }
}
