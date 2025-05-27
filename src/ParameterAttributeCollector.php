<?php

namespace olvlvl\ComposerAttributeCollector;


use Composer\IO\IOInterface;
use PhpParser\Parser;

/**
 * @internal
 */
class ParameterAttributeCollector
{
    private IOInterface $io;
    private Parser $parser;

    public function __construct(IOInterface $io, Parser $parser)
    {
        $this->io = $io;
        $this->parser = $parser;
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

        // todo: implement PHPParser based inspection
        throw new \LogicException();
    }
}
