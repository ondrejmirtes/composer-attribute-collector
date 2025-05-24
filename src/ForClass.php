<?php

namespace olvlvl\ComposerAttributeCollector;

/**
 * @readonly
 */
final class ForClass
{
    /**
     * @var iterable<object>
     */
    public iterable $classAttributes;
    /**
     * @var array<string, iterable<object>>
     */
    public array $methodsAttributes;
    /**
     * @var array<string, iterable<object>>
     */
    public array $propertyAttributes;
    /**
     * @param iterable<object> $classAttributes
     *     Where _value_ is an attribute.
     * @param array<string, iterable<object>> $methodsAttributes
     *     Where _key_ is a method and _value_ and iterable where _value_ is an attribute.
     * @param array<string, iterable<object>> $propertyAttributes
     *     Where _key_ is a property and _value_ and iterable where _value_ is an attribute.
     */
    public function __construct(iterable $classAttributes, array $methodsAttributes, array $propertyAttributes)
    {
        $this->classAttributes = $classAttributes;
        $this->methodsAttributes = $methodsAttributes;
        $this->propertyAttributes = $propertyAttributes;
    }
}
