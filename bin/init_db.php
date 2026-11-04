<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'iskonnect';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

$sqlFile = dirname(__DIR__) . '/database/schema.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "Schema file not found: {$sqlFile}\n");
    exit(1);
}

$schema = file_get_contents($sqlFile);
if ($schema === false) {
    fwrite(STDERR, "Unable to read schema file.\n");
    exit(1);
}

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");

    $statements = array_filter(
        preg_split('/;\s*(?=(?:CREATE|INSERT|ALTER|DROP|UPDATE|DELETE|USE|SET|SELECT|REPLACE))/', $schema),
        static fn (string $statement): bool => trim($statement) !== ''
    );

    foreach ($statements as $statement) {
        $pdo->exec(trim($statement) . ';');
    }

    fwrite(STDOUT, "Database initialized successfully.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Database setup failed: {$e->getMessage()}\n");
    exit(1);
}
