<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Graph;

final class GraphBuilder
{
    public function build(): ProjectGraph
    {
        return new ProjectGraph();
    }
}
