<?php

declare(strict_types=1);

namespace IaroslavKhmel\ProjectMap\Parser;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;

final class CallParser
{
    /**
     * @param array<string, string> $knownVariableTypes
     * @return array<string, mixed>
     */
    public function parse(Node $node, string $sourceMethod, string $currentClass, array $knownVariableTypes = []): ?array
    {
        if ($node instanceof MethodCall) {
            return $this->methodCall($node, $sourceMethod, $currentClass, $knownVariableTypes);
        }

        if ($node instanceof StaticCall) {
            return $this->staticCall($node, $sourceMethod, $currentClass);
        }

        if ($node instanceof New_) {
            return $this->newCall($node, $sourceMethod);
        }

        return null;
    }

    /**
     * @param array<string, string> $knownVariableTypes
     * @return array<string, mixed>
     */
    private function methodCall(MethodCall $call, string $sourceMethod, string $currentClass, array $knownVariableTypes): array
    {
        $method = $call->name instanceof Node\Identifier ? $call->name->toString() : null;
        $kind = $method === null ? 'unknown_call' : 'calls';
        $targetClass = null;

        if ($call->var instanceof Node\Expr\Variable && $call->var->name === 'this') {
            $targetClass = $currentClass;
        } elseif ($call->var instanceof Node\Expr\Variable && is_string($call->var->name)) {
            $targetClass = $knownVariableTypes[$call->var->name] ?? null;
        } elseif (
            $call->var instanceof Node\Expr\PropertyFetch
            && $call->var->var instanceof Node\Expr\Variable
            && $call->var->var->name === 'this'
            && $call->var->name instanceof Node\Identifier
        ) {
            $targetClass = $knownVariableTypes[$call->var->name->toString()] ?? null;
        }

        if ($targetClass === null || $method === null) {
            $kind = 'unknown_call';
        }

        return [
            'source_method' => $sourceMethod,
            'kind' => $kind,
            'target_class' => $targetClass,
            'target_method' => $targetClass && $method ? $targetClass . '::' . $method : null,
            'method' => $method,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staticCall(StaticCall $call, string $sourceMethod, string $currentClass): array
    {
        $method = $call->name instanceof Node\Identifier ? $call->name->toString() : null;
        $class = null;

        if ($call->class instanceof Name) {
            $className = $call->class->toString();
            $class = in_array(strtolower($className), ['self', 'static'], true) ? $currentClass : $className;
        }

        return [
            'source_method' => $sourceMethod,
            'kind' => $class && $method ? 'calls' : 'unknown_call',
            'target_class' => $class,
            'target_method' => $class && $method ? $class . '::' . $method : null,
            'method' => $method,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function newCall(New_ $call, string $sourceMethod): array
    {
        $class = $call->class instanceof Name ? $call->class->toString() : null;

        return [
            'source_method' => $sourceMethod,
            'kind' => $class ? 'new' : 'unknown_call',
            'target_class' => $class,
            'target_method' => $class ? $class . '::__construct' : null,
            'method' => '__construct',
        ];
    }
}
