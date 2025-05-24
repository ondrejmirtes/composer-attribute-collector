<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Resource
{
    public string $name;
    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
