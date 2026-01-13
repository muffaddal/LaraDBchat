<?php

namespace LaraDBChat\Facades;

use Illuminate\Support\Facades\Facade;
use LaraDBChat\Services\LaraDBChatService;

/**
 * @method static array ask(string $question)
 * @method static void train()
 * @method static string generateSql(string $question)
 * @method static array getHistory(int $limit = 50, int $offset = 0)
 *
 * @see \LaraDBChat\Services\LaraDBChatService
 */
class LaraDBChat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaraDBChatService::class;
    }
}
