<?php

namespace olvlvl\ComposerAttributeCollector;

use Attribute;
use Composer\IO\IOInterface;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Parser;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

use function array_merge;
use function is_string;
use function strtolower;

use const PHP_VERSION_ID;

/**
 * @internal
 */
class ClassAttributeCollector
{
    private IOInterface $io;
    private CachedParser $cachedParser;
    private ClassAstLocator $classAstLocator;

    public function __construct(IOInterface $io, Parser $parser)
    {
        $this->io = $io;
        $this->cachedParser = new CachedParser($parser);
        $this->classAstLocator = new ClassAstLocator($this->cachedParser);
    }

    /**
     * @param array<class-string, string> $classMap
     *     Where _key_ is a class and _value_ its pathname.
     */
    public function setClassMap(array $classMap): void
    {
        $this->classAstLocator->setClassMap($classMap);
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
        if (PHP_VERSION_ID < 80000) {
            // Autoloading the class could cause an unrecoverable fatal error:
            // the source or a class it depends on might use PHP 8 syntax.
            // Everything is collected from the AST instead.
            return $this->collectAttributesUsingParser($class);
        }

        $classReflection = new ReflectionClass($class);

        if ($this->isAttribute($classReflection)) {
            return [ [], [], [], [] ];
        }

        $classAttributes = [];
        $attributes = $classReflection->getAttributes();

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
     * Collects attributes from the AST, without autoloading the class or anything it depends on.
     *
     * Unlike with reflection, attributes inherited from parent classes or traits are not
     * collected.
     *
     * @param class-string $class
     *
     * @return array{
     *     array<TransientTargetClass>,
     *     array<TransientTargetMethod>,
     *     array<TransientTargetProperty>,
     *     array<array<TransientTargetMethodParameter>>,
     * }
     */
    private function collectAttributesUsingParser(string $class): array
    {
        $classNode = $this->classAstLocator->findClassNode($class);

        if ($classNode === null) {
            return [ [], [], [], [] ];
        }

        foreach ($classNode->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === Attribute::class) {
                    return [ [], [], [], [] ];
                }
            }
        }

        $reflector = new AttributeGroupsReflector($class, $this->classAstLocator);

        $classAttributes = [];

        foreach ($reflector->attrGroupsToAttributes($classNode->attrGroups) as $attribute) {
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
        $promotedPropertyAttributes = [];
        $propertyAttributes = [];
        $seenMethods = [];
        $seenProperties = [];

        // like with reflection, non-private methods and properties inherited along
        // the extends chain are collected as members of this class too
        foreach ($this->collectClassChain($class, $classNode) as $chainClass => $chainNode) {
            $isSelf = $chainClass === $class;
            $chainReflector = $isSelf ? $reflector : new AttributeGroupsReflector($chainClass, $this->classAstLocator);
            $this->collectClassLikeMembers(
                $class,
                $chainNode,
                $chainReflector,
                $isSelf,
                $seenMethods,
                $seenProperties,
                $methodAttributes,
                $methodParameterAttributes,
                $propertyAttributes,
                $promotedPropertyAttributes,
            );
        }

        return [
            $classAttributes,
            $methodAttributes,
            array_merge($propertyAttributes, $promotedPropertyAttributes),
            $methodParameterAttributes,
        ];
    }

    /**
     * @param class-string $class
     * @return array<string, Node\Stmt\ClassLike>
     */
    private function collectClassChain(string $class, Node\Stmt\ClassLike $classNode): array
    {
        $chain = [ $class => $classNode ];
        $current = $classNode;

        while ($current instanceof Node\Stmt\Class_ && $current->extends !== null) {
            $parent = $current->extends->toString();
            if (isset($chain[$parent])) {
                break;
            }

            $parentNode = $this->classAstLocator->findClassNode($parent);
            if ($parentNode === null) {
                break;
            }

            $chain[$parent] = $parentNode;
            $current = $parentNode;
        }

        return $chain;
    }

    /**
     * @param array<lowercase-string, true> $seenMethods
     * @param array<string, true> $seenProperties
     * @param array<TransientTargetMethod> $methodAttributes
     * @param array<array<TransientTargetMethodParameter>> $methodParameterAttributes
     * @param array<TransientTargetProperty> $propertyAttributes
     * @param array<TransientTargetProperty> $promotedPropertyAttributes
     */
    private function collectClassLikeMembers(
        string $class,
        Node\Stmt\ClassLike $classNode,
        AttributeGroupsReflector $reflector,
        bool $isSelf,
        array &$seenMethods,
        array &$seenProperties,
        array &$methodAttributes,
        array &$methodParameterAttributes,
        array &$propertyAttributes,
        array &$promotedPropertyAttributes
    ): void {
        foreach ($classNode->getMethods() as $methodNode) {
            $method = $methodNode->name->toString();

            if (!$isSelf && $methodNode->isPrivate()) {
                continue;
            }

            if (isset($seenMethods[strtolower($method)])) {
                continue;
            }
            $seenMethods[strtolower($method)] = true;

            foreach ($reflector->attrGroupsToAttributes($methodNode->attrGroups) as $attribute) {
                if (self::isAttributeIgnored($attribute->getName())) {
                    continue;
                }

                $this->io->debug("Found attribute {$attribute->getName()} on $class::$method");

                $methodAttributes[] = new TransientTargetMethod(
                    $attribute->getName(),
                    $attribute->getArguments(),
                    $method,
                );
            }

            $parameterAttributes = [];

            foreach ($methodNode->params as $parameterNode) {
                if (!$parameterNode->var instanceof Node\Expr\Variable || !is_string($parameterNode->var->name)) {
                    continue;
                }

                $parameter = $parameterNode->var->name;
                assert($parameter !== '');

                foreach ($reflector->attrGroupsToAttributes($parameterNode->attrGroups) as $attribute) {
                    if (self::isAttributeIgnored($attribute->getName())) {
                        continue;
                    }

                    $this->io->debug("Found attribute {$attribute->getName()} on $class::$method(\$$parameter)");

                    $parameterAttributes[] = new TransientTargetMethodParameter(
                        $attribute->getName(),
                        $attribute->getArguments(),
                        $method,
                        $parameter,
                    );

                    // an attribute on a promoted constructor property is also visible
                    // through ReflectionProperty; inherited private properties are not
                    if (
                        $parameterNode->flags !== 0
                        && ($isSelf || ($parameterNode->flags & Modifiers::PRIVATE) === 0)
                    ) {
                        $promotedPropertyAttributes[] = new TransientTargetProperty(
                            $attribute->getName(),
                            $attribute->getArguments(),
                            $parameter,
                        );
                    }
                }
            }

            if ($parameterAttributes !== []) {
                $methodParameterAttributes[] = $parameterAttributes;
            }
        }

        foreach ($classNode->getProperties() as $propertyNode) {
            if (!$isSelf && $propertyNode->isPrivate()) {
                continue;
            }

            $propertyNames = [];
            foreach ($propertyNode->props as $propNode) {
                $propertyNames[] = $propNode->name->toString();
            }

            $newPropertyNames = [];
            foreach ($propertyNames as $property) {
                if (isset($seenProperties[$property])) {
                    continue;
                }
                $seenProperties[$property] = true;
                $newPropertyNames[] = $property;
            }

            if ($newPropertyNames === []) {
                continue;
            }

            foreach ($reflector->attrGroupsToAttributes($propertyNode->attrGroups) as $attribute) {
                if (self::isAttributeIgnored($attribute->getName())) {
                    continue;
                }

                foreach ($newPropertyNames as $property) {
                    $this->io->debug("Found attribute {$attribute->getName()} on $class::$property");

                    $propertyAttributes[] = new TransientTargetProperty(
                        $attribute->getName(),
                        $attribute->getArguments(),
                        $property,
                    );
                }
            }
        }
    }

    /**
     * Determines if a class is an attribute.
     *
     * @param ReflectionClass<object> $classReflection
     */
    private function isAttribute(ReflectionClass $classReflection): bool
    {
        foreach ($classReflection->getAttributes() as $attribute) {
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
     * @return ReflectionAttribute<object>[]
     */
    private function getPropertyAttributes(ReflectionProperty $propertyReflection): array
    {
        return $propertyReflection->getAttributes();
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
        $parameterAttributeCollector = new ParameterAttributeCollector($this->io);
        foreach ($methodReflection->getAttributes() as $attribute) {
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
