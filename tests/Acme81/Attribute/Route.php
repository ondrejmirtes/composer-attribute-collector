<?php

namespace Acme81\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route
{
    public string $pattern;
    /**
     * @var Method|Method[]
     */
    public $method;
    public ?string $id;
    /**
     * @param Method|Method[] $method
     */
    public function __construct(string $pattern, $method = Method::GET, ?string $id = null)
    {
        $this->pattern = $pattern;
        $this->method = $method;
        $this->id = $id;
    }
}
