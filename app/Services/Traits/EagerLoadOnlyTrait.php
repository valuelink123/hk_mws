<?php
namespace App\Services\Traits;


use LogicException;

trait EagerLoadOnlyTrait
{
    /**
     * @param string $method
     */
    protected function getRelationshipFromMethod($method)
    {
        $modelName = static::class;
        throw new LogicException(
            "EagerLoadedOnlyTrait: Attempting to lazy-load relation '$method' on model '$modelName'"
        );
    }
}