<?php
// Shared database connection. The credentials live in veneficiaConfig.php one level above the web root, so they are
// never committed or deployed (see config.example.php in the repo root for the format).
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $config = require dirname($_SERVER['DOCUMENT_ROOT']) . '/veneficiaConfig.php';
        $pdo = new PDO(
            "mysql:host={$config['dbHost']};dbname={$config['dbName']};charset=utf8mb4",
            $config['dbUser'],
            $config['dbPassword'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}
