<?php

namespace olvlvl\ComposerAttributeCollector;

use Attribute;
use Composer\IO\IOInterface;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

use function file_get_contents;

use const PHP_VERSION_ID;

/**
 * @internal
 */
class ClassAttributeCollector
{
    private IOInterface $io;
    private Parser $parser;

    /** @var array<string, Node[]> */
    private array $parserCache = [];
    public function __construct(IOInterface $io, Parser $parser)
    {
        $this->io = $io;
        $this->parser = $parser;
    }
    /**
     * @param class-string $class
     *
     * @return array{
     *     array<TransientTargetClass>,
     *     array<TransientTargetMethod>,
     *     array<TransientTargetProperty>,
     * }
     *
     * @throws ReflectionException
     */
    public function collectAttributes(string $class): array
    {
        $classReflection = new ReflectionClass($class);

        if ($this->isAttribute($classReflection)) {
            return [ [], [], [] ];
        }

        $classAttributes = [];
        $attributes = $this->getClassAttributes($classReflection);

        foreach ($attributes as $attribute) {
            if (self::isAttributeIgnored($attribute->getName())) {
                continue;
            }

            $this->io->debug("Found attribute {$attribute->getName()} on $class");

            $classAttributes[] = new TransientTargetClass(
                $attribute->getName(),
                $attribute->getArguments(),
            );
        }

        $methodAttributes = [];

        foreach ($classReflection->getMethods() as $methodReflection) {
            foreach ($this->getMethodAttributes($methodReflection) as $attribute) {
                if (self::isAttributeIgnored($attribute->getName())) {
                    continue;
                }

                $method = $methodReflection->name;

                $this->io->debug("Found attribute {$attribute->getName()} on $class::$method");

                $methodAttributes[] = new TransientTargetMethod(
                    $attribute->getName(),
                    $attribute->getArguments(),
                    $method,
                );
            }
        }

        $propertyAttributes = [];

        foreach ($classReflection->getProperties() as $propertyReflection) {
            foreach ($this->getPropertyAttributes($propertyReflection) as $attribute) {
                if (self::isAttributeIgnored($attribute->getName())) {
                    continue;
                }

                $property = $propertyReflection->name;
                assert($property !== '');

                $this->io->debug("Found attribute {$attribute->getName()} on $class::$property");

                $propertyAttributes[] = new TransientTargetProperty(
                    $attribute->getName(),
                    $attribute->getArguments(),
                    $property,
                );
            }
        }

        return [ $classAttributes, $methodAttributes, $propertyAttributes ];
    }

    /**
     * Determines if a class is an attribute.
     *
     * @param ReflectionClass<object> $classReflection
     */
    private function isAttribute(ReflectionClass $classReflection): bool
    {
        foreach ($this->getClassAttributes($classReflection) as $attribute) {
            if ($attribute->getName() === Attribute::class) {
                return true;
            }
        }

        return false;
    }

    private static function isAttributeIgnored(string $name): bool
    {
        static $ignored = [
            \ReturnTypeWillChange::class => true,
            \Override::class => true,
            \SensitiveParameter::class => true,
            \Deprecated::class => true,
            \AllowDynamicProperties::class,
        ];

        return isset($ignored[$name]); // @phpstan-ignore offsetAccess.nonOffsetAccessible
    }

    /**
     * @param ReflectionClass<object> $classReflection
     * @return ReflectionAttribute<object>[]
     */
    private function getClassAttributes(ReflectionClass $classReflection): array
    {
        if (PHP_VERSION_ID >= 80000) {
            return $classReflection->getAttributes();
        }

        if ($classReflection->getFileName() === false) {
            return [];
        }

        $ast = $this->parse($classReflection->getFileName());
        $classVisitor = new class ($classReflection->getName()) extends NodeVisitorAbstract {
            private string $className;

            public ?ClassLike $classNodeToReturn = null;

            public function __construct(string $className)
            {
                $this->className = $className;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof ClassLike) {
                    if ($node->namespacedName !== null && $node->namespacedName->toString() === $this->className) {
                        $this->classNodeToReturn = $node;
                    }
                }

                return null;
            }
        };
        $traverser = new NodeTraverser($classVisitor);
        $traverser->traverse($ast);

        if ($classVisitor->classNodeToReturn === null) {
            return [];
        }

        return $this->attrGroupsToAttributes($classVisitor->classNodeToReturn->attrGroups);
    }

    /**
     * @return Node[]
     */
    private function parse(string $file): array
    {
        if (isset($this->parserCache[$file])) {
            return $this->parserCache[$file];
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            return [];
        }

        $ast = $this->parser->parse($contents);
        assert($ast !== null);
        $nameTraverser = new NodeTraverser(new NameResolver());

        return $this->parserCache[$file] = $nameTraverser->traverse($ast);
    }

    /**
     * @param Node\AttributeGroup[] $attrGroups
     * @return ReflectionAttribute<object>[]
     */
    private function attrGroupsToAttributes(array $attrGroups): array
    {
        $evaluator = new ConstExprEvaluator();

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

    /**
     * @return ReflectionAttribute<object>[]
     */
    private function getPropertyAttributes(ReflectionProperty $propertyReflection): array
    {
        if (PHP_VERSION_ID >= 80000) {
            return $propertyReflection->getAttributes();
        }

        if ($propertyReflection->getDeclaringClass()->getFileName() === false) {
            return [];
        }

        $ast = $this->parse($propertyReflection->getDeclaringClass()->getFileName());
        $propertyVisitor = new class ($propertyReflection->getDeclaringClass()->getName(), $propertyReflection->getName()) extends NodeVisitorAbstract {
            private string $className;

            private string $propertyName;

            public ?Node\Stmt\Property $propertyNodeToReturn = null;

            public function __construct(string $className, string $propertyName)
            {
                $this->className = $className;
                $this->propertyName = $propertyName;
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

                if ($node instanceof Node\Stmt\Property) {
                    foreach ($node->props as $prop) {
                        if ($prop->name->toString() === $this->propertyName) {
                            $this->propertyNodeToReturn = $node;
                        }
                    }
                }

                return null;
            }
        };
        $traverser = new NodeTraverser($propertyVisitor);
        $traverser->traverse($ast);

        if ($propertyVisitor->propertyNodeToReturn === null) {
            return [];
        }

        return $this->attrGroupsToAttributes($propertyVisitor->propertyNodeToReturn->attrGroups);
    }

    /**
     * @return ReflectionAttribute<object>[]
     */
    private function getMethodAttributes(\ReflectionMethod $methodReflection): array
    {
        if (PHP_VERSION_ID >= 80000) {
            return $methodReflection->getAttributes();
        }

        if ($methodReflection->getDeclaringClass()->getFileName() === false) {
            return [];
        }

        $ast = $this->parse($methodReflection->getDeclaringClass()->getFileName());
        $methodVisitor = new class ($methodReflection->getDeclaringClass()->getName(), $methodReflection->getName()) extends NodeVisitorAbstract {
            private string $className;

            private string $methodName;

            public ?Node\Stmt\ClassMethod $methodNodeToReturn = null;

            public function __construct(string $className, string $methodName)
            {
                $this->className = $className;
                $this->methodName = $methodName;
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
                    if ($node->name->toString() === $this->methodName) {
                        $this->methodNodeToReturn = $node;
                    }
                }

                return null;
            }
        };
        $traverser = new NodeTraverser($methodVisitor);
        $traverser->traverse($ast);

        if ($methodVisitor->methodNodeToReturn === null) {
            return [];
        }

        return $this->attrGroupsToAttributes($methodVisitor->methodNodeToReturn->attrGroups);
    }
}
