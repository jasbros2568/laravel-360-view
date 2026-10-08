<?php

namespace jasbros2568\View360\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static list<string> frames(string $directory, ?string $disk = null)
 * @method static list<string> paths(string $directory, ?string $disk = null)
 * @method static list<string> store(iterable $files, string $directory, ?string $disk = null, bool $replace = true)
 * @method static void delete(string $directory, ?string $disk = null)
 * @method static array<string, list<mixed>> rules(string $attribute = 'frames', int $min = 2)
 *
 * @see \jasbros2568\View360\View360
 */
class View360 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \jasbros2568\View360\View360::class;
    }
}
