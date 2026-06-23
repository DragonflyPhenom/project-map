<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Graph\Node;

final readonly class ClassNode
{
    public function __construct(
        public string $name,
        public ?string $namespace,
        public string $type,
    ) {
    }
}
