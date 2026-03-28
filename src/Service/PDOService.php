<?php

namespace App\Service;

use PDO;
use PDOException;

final class PDOService
{
    private ?PDO $pdo = null;

    public function __construct(
        private string $dbHost,
        private string $dbPort,
        private string $dbName,
        private string $dbUser,
        private string $dbPass,
    ) {
    }

    public function getConnection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $this->dbHost,
            $this->dbPort,
            $this->dbName
        );

        $this->pdo = new PDO(
            $dsn,
            $this->dbUser,
            $this->dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $this->pdo;
    }
}