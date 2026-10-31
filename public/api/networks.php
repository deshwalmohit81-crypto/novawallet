<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

try {
    $rows = nw_networks();
    $result = array_map(static function (array $network): array {
        unset($network['rpc_url']);
        return $network;
    }, $rows);
    nw_json(['networks' => $result]);
} catch (PDOException $e) {
    error_log('NOVAWALLET networks: ' . $e->getMessage());
    nw_json(['error' => 'Network configuration is unavailable. Complete the database setup and retry.'], 503);
}
