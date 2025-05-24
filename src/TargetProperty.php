<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 *
 * @template T of object
 */
final class TargetProperty
{
    /**
     * @var T
     */
    public object $attribute;
    /**
     * @var class-string
     */
    public string $class;
    /**
     * @var non-empty-string
     */
    public string $name;
    /**
     * @param T $attribute
     * @param class-string $class
     *     The name of the target class.
     * @param non-empty-string $name
     *     The name of the target property.
     */
    public function __construct(object $attribute, string $class, string $name)
    {
        $this->attribute = $attribute;
        $this->class = $class;
        $this->name = $name;
    }
}
