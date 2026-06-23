<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Graph\Edge;

final readonly class RelationEdge
{
    public function __construct(
        public string $fromModel,
        public ?string $toModel,
        public string $relationType,
    ) {
    }
}
