<?php

namespace olvlvl\ComposerAttributeCollector;

use RuntimeException;
use Throwable;

use function array_map;

/**
 * @internal
 */
final class Collection
{
    /**
     * @var array<class-string, array<array{mixed[], class-string}>>
     */
    private array $targetClasses;
    /**
     * @var array<class-string, array<array{mixed[], class-string, non-empty-string}>>
     */
    private array $targetMethods;
    /**
     * @var array<class-string, array<array{mixed[], class-string, non-empty-string}>>
     */
    private array $targetProperties;
    /**
     * @var array<class-string, array<array{mixed[], class-string, non-empty-string, non-empty-string}>>
     */
    private array $targetMethodParameters;
    /**
     * @param array<class-string, array<array{ mixed[], class-string }>> $targetClasses
     *     Where _key_ is an attribute class and _value_ an array of arrays
     *     where 0 are the attribute arguments and 1 is a target class.
     * @param array<class-string, array<array{ mixed[], class-string, non-empty-string }>> $targetMethods
     *     Where _key_ is an attribute class and _value_ an array of arrays
     *     where 0 are the attribute arguments, 1 is a target class, and 2 is the target method.
     * @param array<class-string, array<array{ mixed[], class-string, non-empty-string }>> $targetProperties
     *     Where _key_ is an attribute class and _value_ an array of arrays
     *     where 0 are the attribute arguments, 1 is a target class, and 2 is the target property.
     * @param array<class-string, array<array{ mixed[], class-string, non-empty-string, non-empty-string }>> $targetMethodParameters
     *     Where _key_ is an attribute class and _value_ an array of arrays
     *     where 0 are the attribute arguments, 1 is a target class, 2 is the target method, and 3 is the target parameter.
     */
    public function __construct(array $targetClasses, array $targetMethods, array $targetProperties, array $targetMethodParameters)
    {
        $this->targetClasses = $targetClasses;
        $this->targetMethods = $targetMethods;
        $this->targetProperties = $targetProperties;
        $this->targetMethodParameters = $targetMethodParameters;
    }
    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return array<TargetClass<T>>
     */
    public function findTargetClasses(string $attribute): array
    {
        return array_map(
            fn(array $t) => self::createClassAttribute($attribute, ...$t),
            $this->targetClasses[$attribute] ?? [],
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     * @param array<mixed> $arguments
     * @param class-string $class
     *
     * @return TargetClass<T>
     */
    private static function createClassAttribute(string $attribute, array $arguments, string $class): object
    {
        try {
            $a = new $attribute(...$arguments);
            return new TargetClass($a, $class);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "An error occurred while instantiating attribute $attribute on class $class",
                0,
                $e,
            );
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return array<TargetMethod<T>>
     */
    public function findTargetMethods(string $attribute): array
    {
        return array_map(
            fn(array $t) => self::createMethodAttribute($attribute, ...$t),
            $this->targetMethods[$attribute] ?? [],
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     * @param array<mixed> $arguments
     * @param class-string $class
     * @param non-empty-string $method
     *
     * @return TargetMethod<T>
     */
    private static function createMethodAttribute(string $attribute, array $arguments, string $class, string $method): object
    {
        try {
            $a = new $attribute(...$arguments);
            return new TargetMethod($a, $class, $method);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "An error occurred while instantiating attribute $attribute on method $class::$method",
                0,
                $e,
            );
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return array<TargetMethodParameter<T>>
     */
    public function findTargetMethodParameters(string $attribute): array
    {
        return array_map(
            fn(array $t) => self::createMethodParameterAttribute($attribute, ...$t),
            $this->targetMethodParameters[$attribute] ?? [],
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     * @param array<mixed> $arguments
     * @param class-string $class
     * @param non-empty-string $method
     * @param non-empty-string $parameter
     *
     * @return TargetMethodParameter<T>
     */
    private static function createMethodParameterAttribute(string $attribute, array $arguments, string $class, string $method, string $parameter): object
    {
        try {
            $a = new $attribute(...$arguments);
            return new TargetMethodParameter($a, $class, $method, $parameter);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "An error occurred while instantiating attribute $attribute on method $class::$method($parameter)",
                0,
                $e,
            );
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return array<TargetProperty<T>>
     */
    public function findTargetProperties(string $attribute): array
    {
        return array_map(
            fn(array $t) => self::createPropertyAttribute($attribute, ...$t),
            $this->targetProperties[$attribute] ?? [],
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     * @param array<mixed> $arguments
     * @param class-string $class
     * @param non-empty-string $property
     *
     * @return TargetProperty<T>
     */
    private static function createPropertyAttribute(string $attribute, array $arguments, string $class, string $property): object
    {
        try {
            $a = new $attribute(...$arguments);
            return new TargetProperty($a, $class, $property);
        } catch (Throwable $e) {
            throw new RuntimeException(
                "An error occurred while instantiating attribute $attribute on property $class::$property",
                0,
                $e,
            );
        }
    }

    /**
     * @param callable(class-string $attribute, class-string $class):bool $predicate
     *
     * @return array<TargetClass<object>>
     */
    public function filterTargetClasses(callable $predicate): array
    {
        $ar = [];

        foreach ($this->targetClasses as $attribute => $references) {
            foreach ($references as [$arguments, $class]) {
                if ($predicate($attribute, $class)) {
                    $ar[] = self::createClassAttribute($attribute, $arguments, $class);
                }
            }
        }

        return $ar;
    }

    /**
     * @param callable(class-string $attribute, class-string $class, non-empty-string $method):bool $predicate
     *
     * @return array<TargetMethod<object>>
     */
    public function filterTargetMethods(callable $predicate): array
    {
        $ar = [];

        foreach ($this->targetMethods as $attribute => $references) {
            foreach ($references as [$arguments, $class, $method]) {
                if ($predicate($attribute, $class, $method)) {
                    $ar[] = self::createMethodAttribute(
                        $attribute,
                        $arguments,
                        $class,
                        $method,
                    );
                }
            }
        }

        return $ar;
    }

    /**
     * @param callable(class-string $attribute, class-string $class, non-empty-string $method, non-empty-string $parameter):bool $predicate
     *
     * @return array<TargetMethodParameter<object>>
     */
    public function filterTargetMethodParameters(callable $predicate): array
    {
        $ar = [];

        foreach ($this->targetMethodParameters as $attribute => $references) {
            foreach ($references as [$arguments, $class, $method, $parameter]) {
                if ($predicate($attribute, $class, $method, $parameter)) {
                    $ar[] = self::createMethodParameterAttribute(
                        $attribute,
                        $arguments,
                        $class,
                        $method,
                        $parameter
                    );
                }
            }
        }

        return $ar;
    }

    /**
     * @param callable(class-string $attribute, class-string $class, non-empty-string $property):bool $predicate
     *
     * @return array<TargetProperty<object>>
     */
    public function filterTargetProperties(callable $predicate): array
    {
        $ar = [];

        foreach ($this->targetProperties as $attribute => $references) {
            foreach ($references as [$arguments, $class, $property]) {
                if ($predicate($attribute, $class, $property)) {
                    $ar[] = self::createPropertyAttribute(
                        $attribute,
                        $arguments,
                        $class,
                        $property,
                    );
                }
            }
        }

        return $ar;
    }

    /**
     * @param class-string $class
     */
    public function forClass(string $class): ForClass
    {
        $classAttributes = [];

        foreach ($this->filterTargetClasses(fn($a, $c): bool => $c === $class) as $targetClass) {
            $classAttributes[] = $targetClass->attribute;
        }

        $methodAttributes = [];

        foreach ($this->filterTargetMethods(fn($a, $c): bool => $c === $class) as $targetMethod) {
            $methodAttributes[$targetMethod->name][] = $targetMethod->attribute;
        }

        $propertyAttributes = [];

        foreach ($this->filterTargetProperties(fn($a, $c): bool => $c === $class) as $targetProperty) {
            $propertyAttributes[$targetProperty->name][] = $targetProperty->attribute;
        }

        return new ForClass(
            $classAttributes,
            $methodAttributes,
            $propertyAttributes,
        );
    }
}
