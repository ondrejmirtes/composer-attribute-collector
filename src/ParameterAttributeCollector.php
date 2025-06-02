<?php

namespace olvlvl\ComposerAttributeCollector;


use Composer\IO\IOInterface;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;

/**
 * @internal
 */
class ParameterAttributeCollector
{
    private IOInterface $io;
    private CachedParser $cachedParser;

    public function __construct(IOInterface $io, Parser $parser)
    {
        $this->io = $io;
        $this->cachedParser = new CachedParser($parser);
    }

    /**
     * @return array<TransientTargetMethodParameter>
     */
    public function collectAttributes(\ReflectionFunctionAbstract $reflectionFunctionAbstract): array
    {
        $funcParameterAttributes = [];
        foreach($reflectionFunctionAbstract->getParameters() as $parameter) {
            $attributes = $this->getParameterAttributes($parameter);
            $functionName = $reflectionFunctionAbstract->name;
            $parameterName = $parameter->name;
            assert($functionName !== '');
            assert($parameterName !== '');

            $paramLabel = '';
            if ($reflectionFunctionAbstract instanceof \ReflectionMethod) {
                $paramLabel = $reflectionFunctionAbstract->class .'::'.$functionName .'(' . $parameterName .')';
            } elseif ($reflectionFunctionAbstract instanceof \ReflectionFunction) {
                $paramLabel = $functionName . '(' . $parameterName .')';
            }

            foreach($attributes as $attribute) {
                $this->io->debug("Found attribute {$attribute->getName()} on $paramLabel");

                $funcParameterAttributes[] = new TransientTargetMethodParameter(
                    $attribute->getName(),
                    $attribute->getArguments(),
                    $functionName,
                    $parameterName
                );
            }
        }

        return $funcParameterAttributes;
    }

    /**
     * @return \ReflectionAttribute<object>[]
    */
    private function getParameterAttributes(\ReflectionParameter $parameterReflection): array
    {
        if (PHP_VERSION_ID >= 80000) {
            return $parameterReflection->getAttributes();
        }

        $reflectionClass = $parameterReflection->getDeclaringClass();
        if ($reflectionClass === null || $reflectionClass->getFileName() === false) {
            return [];
        }

        $ast = $this->parse($reflectionClass->getFileName());
        $parameterVisitor = new class ($reflectionClass->getName(), $parameterReflection->getDeclaringFunction()->getName(), $parameterReflection->getName()) extends NodeVisitorAbstract {
            private string $className;

            private string $methodName;
            private string $parameterName;

            public ?Node\Param $paramToReturn = null;

            public function __construct(string $className, string $methodName, string $parameterName)
            {
                $this->className = $className;
                $this->methodName = $methodName;
                $this->parameterName = $parameterName;
            }

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof ClassLike) {
                    if ($node->namespacedName === null) {
                        return self::DONT_TRAVERSE_CHILDREN;
                    }
                    if ($node->namespacedName->toString() !== $this->className) {
                        return self::DONT_TRAVERSE_CHILDREN;
                    }
                }

                if ($node instanceof Node\Stmt\ClassMethod) {
                    if ($node->name->toString() !== $this->methodName) {
                        return self::DONT_TRAVERSE_CHILDREN;
                    }
                }

                if ($node instanceof Node\Param) {
                    if (
                        $node->var instanceof Node\Expr\Variable
                        && $node->var->name === $this->parameterName
                    ) {
                        $this->paramToReturn = $node;
                        return self::DONT_TRAVERSE_CHILDREN;
                    }
                }

                return null;
            }
        };
        $traverser = new NodeTraverser($parameterVisitor);
        $traverser->traverse($ast);

        if ($parameterVisitor->paramToReturn === null) {
            return [];
        }

        return (new AttributeGroupsReflector())->attrGroupsToAttributes($parameterVisitor->paramToReturn->attrGroups);
    }

    /**
     * @return Node[]
     */
    private function parse(string $file): array
    {
        return $this->cachedParser->parse($file);
    }

}
