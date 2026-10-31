<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/rpc.php';

nw_require_method('POST');
nw_require_csrf();
try {
    nw_rate_limit('admin', 20);
    $body = nw_json_input();
    if (!is_array($body)) {
        nw_json(['error' => 'Request data must be an object.'], 400);
    }
    $action = nw_text_field($body, 'action');

    if ($action === 'login') {
        $hash = (string) (nw_config()['admin_password_hash'] ?? '');
        $password = nw_text_field($body, 'password');
        if (str_starts_with($hash, 'replace-') || strlen($password) > 1024 || !password_verify($password, $hash)) {
            nw_json(['error' => 'Admin password is incorrect or admin credentials are not configured.'], 401);
        }
        session_regenerate_id(true);
        unset($_SESSION['_csrf']);
        $_SESSION['nw_admin'] = true;
        nw_json(['ok' => true]);
    }

    if (!nw_is_admin()) {
        nw_json(['error' => 'Admin authentication is required.'], 401);
    }
    if ($action === 'logout') {
        unset($_SESSION['nw_admin']);
        session_regenerate_id(true);
        unset($_SESSION['_csrf']);
        nw_json(['ok' => true]);
    }
    if ($action === 'network_update') {
        $chainId = nw_integer_field($body, 'chain_id');
        $name = trim(nw_text_field($body, 'name'));
        $symbol = strtoupper(trim(nw_text_field($body, 'currency_symbol')));
        $explorer = trim(nw_text_field($body, 'explorer_url'));
        $rpc = trim(nw_text_field($body, 'rpc_url'));
        $apiUrl = trim(nw_text_field($body, 'explorer_api_url'));
        $apiKey = trim(nw_text_field($body, 'explorer_api_key'));
        $enabled = !empty($body['enabled']) ? 1 : 0;
        $clearApiKey = !empty($body['clear_explorer_api_key']) ? 1 : 0;
        if ($chainId === false || $chainId < 1 || !$name || strlen($name) > 80 || !preg_match('/^[A-Z0-9]{2,12}$/D', $symbol)) {
            nw_json(['error' => 'Enter a network name and a valid currency symbol.'], 422);
        }
        $explorerParts = parse_url($explorer);
        if (!filter_var($explorer, FILTER_VALIDATE_URL) || !$explorerParts || ($explorerParts['scheme'] ?? '') !== 'https'
            || empty($explorerParts['host']) || isset($explorerParts['user']) || isset($explorerParts['pass'])
            || isset($explorerParts['query']) || isset($explorerParts['fragment']) || strlen($explorer) > 255) {
            nw_json(['error' => 'Explorer link must be a valid HTTPS URL.'], 422);
        }
        if ($rpc !== '' && !nw_valid_rpc_url($rpc)) {
            nw_json(['error' => 'RPC endpoint must be a public HTTPS URL without embedded credentials or IP hosts.'], 422);
        }
        $apiParts = $apiUrl === '' ? [] : parse_url($apiUrl);
        if ($apiUrl !== '' && (!filter_var($apiUrl, FILTER_VALIDATE_URL) || !$apiParts || ($apiParts['scheme'] ?? '') !== 'https'
            || empty($apiParts['host']) || isset($apiParts['user']) || isset($apiParts['pass']) || strlen($apiUrl) > 500)) {
            nw_json(['error' => 'Explorer API URL must be a valid HTTPS URL.'], 422);
        }
        if (strlen($apiKey) > 255) {
            nw_json(['error' => 'Explorer API key is too long.'], 422);
        }
        $statement = nw_db()->prepare('UPDATE networks SET name = ?, currency_symbol = ?, explorer_url = ?, rpc_url = ?, explorer_api_url = ?, explorer_api_key = IF(?, NULL, IF(? = \'\', explorer_api_key, ?)), enabled = ? WHERE chain_id = ?');
        $statement->execute([$name, $symbol, rtrim($explorer, '/'), $rpc !== '' ? $rpc : null, $apiUrl !== '' ? $apiUrl : null, $clearApiKey, $apiKey, $apiKey, $enabled, $chainId]);
        if ($statement->rowCount() === 0) {
            $exists = nw_db()->prepare('SELECT 1 FROM networks WHERE chain_id = ?');
            $exists->execute([$chainId]);
            if (!$exists->fetchColumn()) {
                nw_json(['error' => 'Network not found.'], 404);
            }
        }
        nw_json(['ok' => true, 'message' => 'Network configuration saved.']);
    }
    if ($action === 'token_add') {
        $chainId = nw_integer_field($body, 'chain_id');
        $address = trim(nw_text_field($body, 'contract_address'));
        $name = trim(nw_text_field($body, 'name'));
        $symbol = strtoupper(trim(nw_text_field($body, 'symbol')));
        $decimals = nw_integer_field($body, 'decimals');
        if ($chainId === false || !nw_validate_address($address) || !$name || strlen($name) > 80 || !preg_match('/^[A-Z0-9]{1,20}$/D', $symbol) || $decimals === false || $decimals < 0 || $decimals > 36) {
            nw_json(['error' => 'Enter a valid chain, token contract, name, symbol, and decimals (0–36).'], 422);
        }
        $statement = nw_db()->prepare('INSERT INTO tokens (chain_id, contract_address, name, symbol, decimals) SELECT chain_id, ?, ?, ?, ? FROM networks WHERE chain_id = ?');
        $statement->execute([strtolower($address), $name, $symbol, $decimals, $chainId]);
        if ($statement->rowCount() !== 1) {
            nw_json(['error' => 'Network does not exist or token is already configured.'], 409);
        }
        nw_json(['ok' => true, 'message' => 'Token added to the read-only balance list.']);
    }
    if ($action === 'token_delete') {
        $tokenId = nw_integer_field($body, 'token_id');
        if ($tokenId === false || $tokenId < 1) {
            nw_json(['error' => 'Select a valid token.'], 422);
        }
        $statement = nw_db()->prepare('DELETE FROM tokens WHERE id = ?');
        $statement->execute([$tokenId]);
        nw_json(['ok' => true, 'message' => 'Token removed.']);
    }
    if ($action === 'health_check') {
        $chainId = nw_integer_field($body, 'chain_id');
        $network = $chainId === false ? null : nw_network_by_id((int) $chainId, false);
        if (!$network || empty($network['rpc_url'])) {
            nw_json(['error' => 'Configure and enable an RPC endpoint before checking its health.'], 422);
        }
        try {
            $result = nw_rpc($network['rpc_url'], 'eth_chainId', []);
            $ok = is_string($result) && hexdec($result) === (int) $network['chain_id'];
        } catch (RuntimeException|JsonException $e) {
            $ok = false;
        }
        $update = nw_db()->prepare('UPDATE networks SET last_health_check = UTC_TIMESTAMP(), last_health_ok = ? WHERE chain_id = ?');
        $update->execute([$ok ? 1 : 0, $chainId]);
        nw_json(['ok' => $ok, 'message' => $ok ? 'RPC is reachable and chain ID matches.' : 'RPC check failed or returned a different chain ID.', 'checked_at' => gmdate(DATE_ATOM)]);
    }
    nw_json(['error' => 'Unknown admin action.'], 422);
} catch (JsonException $e) {
    nw_json(['error' => 'Request data must be valid JSON.'], 400);
} catch (PDOException $e) {
    error_log('NOVAWALLET admin database: ' . $e->getMessage());
    nw_json(['error' => 'The database operation failed. Check your schema and network configuration.'], 503);
} catch (Throwable $e) {
    error_log('NOVAWALLET admin: ' . $e->getMessage());
    nw_json(['error' => 'The admin request could not be completed.'], 500);
}
