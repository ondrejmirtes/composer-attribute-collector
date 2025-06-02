<?php

namespace olvlvl\ComposerAttributeCollector;

use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use ReflectionAttribute;

/**
 * @internal
 */
class AttributeGroupsReflector {
    /**
     * @param Node\AttributeGroup[] $attrGroups
     * @return ReflectionAttribute<object>[]
     */
    public function attrGroupsToAttributes(array $attrGroups): array
    {
        $evaluator = new ConstExprEvaluator(function (Expr $expr) {
            if ($expr instanceof Expr\ClassConstFetch && $expr->class instanceof Node\Name && $expr->name instanceof Node\Identifier) {
                return constant(sprintf('%s::%s', $expr->class->toString(), $expr->name->toString()));
            }

            throw new ConstExprEvaluationException("Expression of type {$expr->getType()} cannot be evaluated");
        });

        $attributes = [];
        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $argValues = [];
                foreach ($attr->args as $i => $arg) {
                    if ($arg->name === null) {
                        $argValues[$i] = $evaluator->evaluateDirectly($arg->value);
                        continue;
                    }

                    $argValues[$arg->name->toString()] = $evaluator->evaluateDirectly($arg->value);
                }
                $attributes[] = new FakeAttribute(
                    $attr->name,
                    $argValues,
                );
            }
        }

        return $attributes; // @phpstan-ignore return.type
    }
}
