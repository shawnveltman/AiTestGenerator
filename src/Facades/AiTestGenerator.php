<?php

namespace Shawnveltman\AiTestGenerator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Shawnveltman\AiTestGenerator\AiTestGenerator
 */
class AiTestGenerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Shawnveltman\AiTestGenerator\AiTestGenerator::class;
    }
}
