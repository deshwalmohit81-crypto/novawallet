<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$schema = file_get_contents(__DIR__ . '/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Could not read database/schema.sql.\n");
    exit(1);
}

$statements = preg_split('/;[ \t]*(?:\r?\n|$)/', $schema);
if ($statements === false) {
    fwrite(STDERR, "Could not parse database/schema.sql.\n");
    exit(1);
}

try {
    $pdo = nw_db();
    $installed = 0;
    foreach ($statements as $statement) {
        $statement = preg_replace('/^\s*--[^\r\n]*(?:\r?\n|$)/m', '', $statement);
        if (trim((string) $statement) === '') {
            continue;
        }
        $pdo->exec($statement);
        $installed++;
    }
    fwrite(STDOUT, "NOVAWALLET database schema installed or verified ({$installed} statements).\n");
} catch (PDOException|RuntimeException $e) {
    fwrite(STDERR, "Database installation failed: {$e->getMessage()}\n");
    exit(1);
}
