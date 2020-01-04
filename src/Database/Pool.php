<?php

declare(strict_types=1);

namespace App\Database;

class Pool
{
    protected \Swoole\Coroutine\Channel $pool;
    private int $size;

    public function __construct(string $dsn, int $size)
    {
        $this->pool = new \Swoole\Coroutine\Channel($size);
        $this->size = $size;

        for ($i = 0; $i < $size; $i++) {
            $pdo = new \PDO($dsn);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);

            $this->push($pdo);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function push(\PDO $connection): void
    {
        $this->pool->push($connection);
    }

    public function get(): \PDO
    {
        return $this->pool->pop();
    }

    public function close(): void
    {
        while (!$this->pool->isEmpty()) {
            $connection = $this->get();
            unset($connection);
        }

        $this->pool->close();
    }
}
