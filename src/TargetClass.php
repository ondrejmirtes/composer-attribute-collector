<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 *
 * @template T of object
 */
final class TargetClass
{
    /**
     * @var T
     */
    public object $attribute;
    /**
     * @var class-string
     */
    public string $name;
    /**
     * @param T $attribute
     * @param class-string $name
     *     The name of the target class.
     */
    public function __construct(object $attribute, string $name)
    {
        $this->attribute = $attribute;
        $this->name = $name;
    }
}
