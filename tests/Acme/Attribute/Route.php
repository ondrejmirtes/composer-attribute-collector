<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Acme\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route
{
    public string $pattern;
    /**
     * @var string|string[]
     */
    public $method;
    public ?string $id;
    /**
     * @param string|string[] $method
     */
    public function __construct(string $pattern, $method = 'GET', ?string $id = null)
    {
        $this->pattern = $pattern;
        $this->method = $method;
        $this->id = $id;
    }
}
