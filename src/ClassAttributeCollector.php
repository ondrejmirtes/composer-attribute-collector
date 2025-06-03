<?php

namespace olvlvl\ComposerAttributeCollector;

use Attribute;
use Composer\IO\IOInterface;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

use function file_get_contents;
use function sprintf;

use const PHP_VERSION_ID;

/**
 * @internal
 */
class ClassAttributeCollector
{
    private IOInterface $io;
    private Parser $parser;
    private CachedParser $cachedParser;

    public function __construct(IOInterface $io, Parser $parser)
    {
        $this->io = $io;
        $this->parser = $parser;
        $this->cachedParser = new CachedParser($parser);
    }
    /**
     * @param class-string $class
     *
     * @return array{
     *     array<TransientTargetClass>,
     *     array<TransientTargetMethod>,
     *     array<TransientTargetProperty>,
     *     array<array<TransientTargetMethodParameter>>,
     * }
     *
     * @throws ReflectionException
     */
    public function collectAttributes(string $class): array
    {
        $classReflection = new ReflectionClass($class);

        if ($this->isAttribute($classReflection)) {
            return [ [], [], [], [] ];
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
        $methodParameterAttributes = [];

        foreach ($classReflection->getMethods() as $methodReflection) {
            $this->collectMethodAndParameterAttributes(
                $class,
                $methodReflection,
                $methodAttributes,
                $methodParameterAttributes,
            );
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

        return [ $classAttributes, $methodAttributes, $propertyAttributes, $methodParameterAttributes ];
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

        return (new AttributeGroupsReflector())->attrGroupsToAttributes($classVisitor->classNodeToReturn->attrGroups);
    }

    /**
     * @return Node[]
     */
    private function parse(string $file): array
    {
        return $this->cachedParser->parse($file);
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

        return (new AttributeGroupsReflector())->attrGroupsToAttributes($propertyVisitor->propertyNodeToReturn->attrGroups);
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

        return (new AttributeGroupsReflector())->attrGroupsToAttributes($methodVisitor->methodNodeToReturn->attrGroups);
    }

    /**
     * @param string $class
     * @param ReflectionMethod $methodReflection
     * @param array<TransientTargetMethod> $methodAttributes
     * @param array<array<TransientTargetMethodParameter>> $methodParameterAttributes
     * @return void
     */
    private function collectMethodAndParameterAttributes(string $class, \ReflectionMethod $methodReflection, array &$methodAttributes, array &$methodParameterAttributes): void
    {
        $parameterAttributeCollector = new ParameterAttributeCollector($this->io, $this->cachedParser);
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

        $parameterAttributes = $parameterAttributeCollector->collectAttributes($methodReflection);
        if ($parameterAttributes !== []) {
            $methodParameterAttributes[] = $parameterAttributes;
        }
    }
}
