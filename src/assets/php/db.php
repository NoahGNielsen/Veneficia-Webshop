<?php
// Shared database connection. The credentials live in config.php one level above the web root, so they are never
// committed or deployed (see config.example.php in the repo root for the format).
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/config.php';
        $config = $GLOBALS['db_config'];
        $pdo = new PDO(
            "mysql:host={$config['servername']};dbname={$config['dbname']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}
