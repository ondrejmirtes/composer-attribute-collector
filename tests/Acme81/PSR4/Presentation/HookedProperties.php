<?php // lint >= 8.4

namespace Acme81\PSR4\Presentation;

use Acme\Attribute\Subscribe;
use Acme81\Attribute\ParameterA;

final class HookedProperties {
    private bool $modified = false;

    public string $foo = 'default value' {
        get {
            if ($this->modified) {
                return $this->foo . ' (modified)';
            }
            return $this->foo;
        }
        #[Subscribe]
        set(
            #[ParameterA("a hook parameter")]
            string $value
        ) {
            $this->foo = strtolower($value);
            $this->modified = true;
        }
    }
}
