<?php

namespace olvlvl\ComposerAttributeCollector;

use function is_iterable;
use function var_export;

/**
 * Renders collected attribute targets as PHP code.
 *
 * @internal
 */
final class TransientCollectionRenderer
{
    public static function render(TransientCollection $collector): string
    {
        $targetClassesCode = self::targetsToCode($collector->classes);
        $targetMethodsCode = self::targetsToCode($collector->methods);
        $targetPropertiesCode = self::targetsToCode($collector->properties);
        $targetMethodParametersCode = self::targetsToCode($collector->methodParameters);

        return <<<PHP
        <?php

        // attributes.php @generated by https://github.com/olvlvl/composer-attribute-collector

        \olvlvl\ComposerAttributeCollector\Attributes::with(fn () => new \olvlvl\ComposerAttributeCollector\Collection(
            // classes
            $targetClassesCode,
            // methods
            $targetMethodsCode,
            // properties
            $targetPropertiesCode,
            // method parameters
            $targetMethodParametersCode,
        ));
        PHP;
    }

    /**
     * //phpcs:disable Generic.Files.LineLength.TooLong
     * @param iterable<class-string, iterable<TransientTargetClass|TransientTargetMethod|TransientTargetProperty|iterable<TransientTargetMethodParameter>>> $targetByClass
     *
     * @return string
     */
    private static function targetsToCode(iterable $targetByClass): string
    {
        $array = self::targetsToArray($targetByClass);

        return var_export($array, true);
    }

    /**
     * //phpcs:disable Generic.Files.LineLength.TooLong
     * @param iterable<class-string, iterable<TransientTargetClass|TransientTargetMethod|TransientTargetProperty|iterable<TransientTargetMethodParameter>>> $targetByClass
     *
     * @return array<class-string, array<array{ array<int|string, mixed>, class-string, 2?:non-empty-string }>>
     */
    private static function targetsToArray(iterable $targetByClass): array
    {
        $by = [];

        foreach ($targetByClass as $class => $targets) {
            foreach ($targets as $target) {
                if (!is_iterable($target)) {
                    $target = [$target];
                }

                foreach ($target as $t) {
                    // args in order how the Target* classes expects them in __construct()
                    $args = [ $t->arguments, $class ];

                    if (
                        $t instanceof TransientTargetMethod
                        || $t instanceof TransientTargetProperty
                        || $t instanceof TransientTargetMethodParameter
                    ) {
                        $args[] = $t->name;
                    }

                    if ($t instanceof TransientTargetMethodParameter) {
                        $args[] = $t->method;
                    }

                    $by[$t->attribute][] = $args;
                }
            }
        }

        return $by;
    }
}
