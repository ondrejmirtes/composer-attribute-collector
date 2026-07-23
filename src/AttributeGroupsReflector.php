<?php

namespace olvlvl\ComposerAttributeCollector;

use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\ClassLike;
use ReflectionAttribute;

use function array_map;
use function constant;
use function dirname;
use function in_array;
use function sprintf;
use function strtolower;

/**
 * @internal
 */
class AttributeGroupsReflector
{
    private ?string $selfClass;
    private ?ClassAstLocator $classAstLocator;

    /** @var array<string, true> */
    private array $constantsBeingResolved = [];

    /**
     * @param string|null $selfClass
     *     The class the attribute groups belong to, used to resolve `self::*` references.
     */
    public function __construct(?string $selfClass = null, ?ClassAstLocator $classAstLocator = null)
    {
        $this->selfClass = $selfClass;
        $this->classAstLocator = $classAstLocator;
    }

    /**
     * @param Node\AttributeGroup[] $attrGroups
     * @return ReflectionAttribute<object>[]
     */
    public function attrGroupsToAttributes(array $attrGroups): array
    {
        $evaluator = $this->createEvaluator($this->selfClass);

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

    private function createEvaluator(?string $selfClass): ConstExprEvaluator
    {
        return new ConstExprEvaluator(function (Expr $expr) use ($selfClass) {
            if ($expr instanceof Node\Scalar\MagicConst\Class_ && $selfClass !== null) {
                return $selfClass;
            }

            if ($expr instanceof Node\Scalar\MagicConst\Dir || $expr instanceof Node\Scalar\MagicConst\File) {
                $filepath = $selfClass !== null && $this->classAstLocator !== null
                    ? $this->classAstLocator->findFilepath($selfClass)
                    : null;

                if ($filepath !== null) {
                    return $expr instanceof Node\Scalar\MagicConst\Dir ? dirname($filepath) : $filepath;
                }
            }

            if ($expr instanceof Expr\ClassConstFetch && $expr->class instanceof Node\Name && $expr->name instanceof Node\Identifier) {
                $className = $expr->class->toString();
                if (in_array(strtolower($className), ['self', 'static'], true)) {
                    if ($selfClass === null) {
                        throw new ConstExprEvaluationException("Cannot resolve $className:: outside of a class context");
                    }

                    $className = $selfClass;
                }

                if ($expr->name->toLowerString() === 'class') {
                    return $className;
                }

                return $this->resolveClassConstant($className, $expr->name->toString());
            }

            throw new ConstExprEvaluationException("Expression of type {$expr->getType()} cannot be evaluated");
        });
    }

    /**
     * Resolves a class constant from the AST when the class is part of the class map,
     * so that the class does not have to be autoloaded.
     *
     * @return mixed
     * @throws ConstExprEvaluationException
     */
    private function resolveClassConstant(string $class, string $constant)
    {
        $classNode = $this->classAstLocator !== null ? $this->classAstLocator->findClassNode($class) : null;

        if ($classNode === null) {
            // The class is outside of the scanned paths (PHP itself or a dependency),
            // loading it to read the constant is safe.
            return constant(sprintf('%s::%s', $class, $constant));
        }

        $key = "$class::$constant";
        if (isset($this->constantsBeingResolved[$key])) {
            throw new ConstExprEvaluationException("Circular reference while resolving $key");
        }

        $this->constantsBeingResolved[$key] = true;

        try {
            foreach ($classNode->getConstants() as $constantsNode) {
                foreach ($constantsNode->consts as $constNode) {
                    if ($constNode->name->toString() === $constant) {
                        return $this->createEvaluator($class)->evaluateDirectly($constNode->value);
                    }
                }
            }

            foreach ($this->getParentNames($classNode) as $parentName) {
                try {
                    return $this->resolveClassConstant($parentName, $constant);
                } catch (ConstExprEvaluationException $e) {
                    continue;
                }
            }
        } finally {
            unset($this->constantsBeingResolved[$key]);
        }

        throw new ConstExprEvaluationException("Couldn't find constant $key");
    }

    /**
     * @return string[]
     */
    private function getParentNames(ClassLike $classNode): array
    {
        if ($classNode instanceof Node\Stmt\Class_) {
            $names = [];
            if ($classNode->extends !== null) {
                $names[] = $classNode->extends->toString();
            }
            foreach ($classNode->implements as $name) {
                $names[] = $name->toString();
            }

            return $names;
        }

        if ($classNode instanceof Node\Stmt\Interface_) {
            return array_map(static fn(Node\Name $name): string => $name->toString(), $classNode->extends);
        }

        if ($classNode instanceof Node\Stmt\Enum_) {
            return array_map(static fn(Node\Name $name): string => $name->toString(), $classNode->implements);
        }

        return [];
    }
}
