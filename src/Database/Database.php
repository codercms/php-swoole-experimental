<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

class Database
{
    private Pool $pool;

    private array $statements = [];

    public function __construct(string $dsn, int $poolSize = 1)
    {
        $this->pool = new Pool($dsn, $poolSize);
    }

    public function select(string $query, array $bindings = [])
    {
        $key = md5($query);
        $pdo = $this->pool->get();

        $statement = $this->statements[$key] ?? null;

        try {
            if (null === $statement) {
                $this->statements[$key] = $statement = $pdo->prepare($query);
            }

            foreach ($bindings as $key => $value) {
                $statement->bindValue(
                    is_string($key) ? $key : $key + 1,
                    $value,
                    is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
                );
            }

            $statement->execute();

            return $statement->fetchAll();
        } finally {
            $this->pool->push($pdo);
        }
    }
}
