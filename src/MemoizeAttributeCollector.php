<?php

namespace olvlvl\ComposerAttributeCollector;

use Composer\IO\IOInterface;
use Throwable;

use function array_filter;
use function filemtime;

use const ARRAY_FILTER_USE_KEY;

/**
 * @internal
 */
class MemoizeAttributeCollector
{
    private ClassAttributeCollector $classAttributeCollector;
    private Datastore $datastore;
    private IOInterface $io;
    private const KEY = 'attributes';

    /**
     * @var array<class-string, array{
     *     int,
     *     array<TransientTargetClass>,
     *     array<TransientTargetMethod>,
     *     array<TransientTargetProperty>,
     *     array<array<TransientTargetMethodParameter>>,
     * }>
     *     Where _key_ is a class and _value is an array where:
     *     - `0` is a timestamp
     *     - `1` is an array of class attributes
     *     - `2` is an array of method attributes
     *     - `3` is an array of property attributes
     *     - `4` is an array of arrays. _key_ is a method name and _value_ parameter attributes
     */
    private array $state;

    public function __construct(ClassAttributeCollector $classAttributeCollector, Datastore $datastore, IOInterface $io)
    {
        $this->classAttributeCollector = $classAttributeCollector;
        $this->datastore = $datastore;
        $this->io = $io;
        /** @phpstan-ignore-next-line */
        $this->state = $this->datastore->get(self::KEY);
    }

    /**
     * @param array<class-string, string> $classMap
     *     Where _key_ is a class and _value_ its pathname.
     */
    public function collectAttributes(array $classMap): TransientCollection
    {
        $filterClasses = [];
        $classAttributeCollector = $this->classAttributeCollector;
        $collector = new TransientCollection();

        foreach ($classMap as $class => $filepath) {
            $filterClasses[$class] = true;

            [
                $timestamp,
                $classAttributes,
                $methodAttributes,
                $propertyAttributes,
                $methodParameterAttributes,
            ] = $this->state[$class] ?? [ 0, [], [], [], [] ];

            $mtime = filemtime($filepath);

            if ($timestamp < $mtime) {
                if ($timestamp) {
                    $diff = $mtime - $timestamp;
                    $this->io->debug("Refresh attributes of class '$class' in '$filepath' ($diff sec ago)");
                } else {
                    $this->io->debug("Collect attributes of class '$class' in '$filepath'");
                }

                try {
                    [
                        $classAttributes,
                        $methodAttributes,
                        $propertyAttributes,
                        $methodParameterAttributes,
                    ] = $classAttributeCollector->collectAttributes($class);
                } catch (Throwable $e) {
                    $this->io->error(
                        "Attribute collection failed for $class: {$e->getMessage()}",
                    );
                }

                $this->state[$class] = [ time(), $classAttributes, $methodAttributes, $propertyAttributes, $methodParameterAttributes ];
            }

            if (count($classAttributes)) {
                $collector->addClassAttributes($class, $classAttributes);
            }
            if (count($methodAttributes)) {
                $collector->addMethodAttributes($class, $methodAttributes);
            }
            if (count($methodParameterAttributes)) {
                $collector->addMethodParameterAttributes($class, $methodParameterAttributes);
            }
            if (count($propertyAttributes)) {
                $collector->addTargetProperties($class, $propertyAttributes);
            }
        }

        /**
         * Classes might have been removed, we need to filter entries according to the classes found.
         */
        $this->state = array_filter(
            $this->state,
            static fn(string $k): bool => $filterClasses[$k] ?? false,
            ARRAY_FILTER_USE_KEY,
        );

        $this->datastore->set(self::KEY, $this->state);

        return $collector;
    }
}
