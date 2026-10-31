<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/rpc.php';

nw_require_method('POST');
nw_require_csrf();
nw_release_session_lock();

try {
    nw_rate_limit('balance', 30);
    $body = nw_json_input();
    $address = is_array($body) ? trim(nw_text_field($body, 'address')) : '';
    $chainId = is_array($body) ? nw_integer_field($body, 'chain_id') : false;
    if (!nw_validate_address($address)) {
        nw_json(['error' => 'Enter a valid public EVM address (0x followed by 40 hexadecimal characters).'], 422);
    }
    if ($chainId === false || $chainId < 1) {
        nw_json(['error' => 'Choose a supported network.'], 422);
    }
    $network = nw_network_by_id((int) $chainId);
    if (!$network) {
        nw_json(['error' => 'That network is unavailable or disabled.'], 404);
    }
    if (empty($network['rpc_url'])) {
        nw_json(['error' => 'A public RPC endpoint has not been configured for this network yet.'], 503);
    }
    $balanceHex = nw_rpc($network['rpc_url'], 'eth_getBalance', [$address, 'latest']);
    if (!is_string($balanceHex) || !preg_match('/^0x[0-9a-fA-F]+$/D', $balanceHex)) {
        throw new RuntimeException('RPC returned an invalid balance.');
    }
    $wei = nw_hex_to_decimal(substr($balanceHex, 2));
    $symbol = $network['currency_symbol'];
    $formatted = nw_format_units($wei, 18);
    $tokensStatement = nw_db()->prepare('SELECT contract_address, name, symbol, decimals FROM tokens WHERE chain_id = ? AND enabled = 1 ORDER BY name LIMIT 5');
    $tokensStatement->execute([(int) $network['chain_id']]);
    $tokens = [];
    foreach ($tokensStatement->fetchAll() as $token) {
        $callData = '0x70a08231' . str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
        $tokenHex = nw_rpc($network['rpc_url'], 'eth_call', [['to' => $token['contract_address'], 'data' => $callData], 'latest']);
        if (!is_string($tokenHex) || !preg_match('/^0x[0-9a-fA-F]+$/D', $tokenHex)) {
            continue;
        }
        $tokens[] = [
            'name' => $token['name'],
            'symbol' => $token['symbol'],
            'contract_address' => $token['contract_address'],
            'balance' => nw_format_units(nw_hex_to_decimal(substr($tokenHex, 2)), (int) $token['decimals']),
        ];
    }
    nw_json([
        'address' => $address,
        'network' => ['chain_id' => (int) $network['chain_id'], 'name' => $network['name'], 'symbol' => $symbol],
        'native_balance' => $formatted,
        'tokens' => $tokens,
        'source' => 'public_rpc',
        'updated_at' => gmdate(DATE_ATOM),
        'demo' => false,
    ]);
} catch (JsonException $e) {
    nw_json(['error' => 'Request data must be valid JSON.'], 400);
} catch (PDOException $e) {
    error_log('NOVAWALLET balance database: ' . $e->getMessage());
    nw_json(['error' => 'Wallet data is temporarily unavailable. Check the installation and try again.'], 503);
} catch (Throwable $e) {
    error_log('NOVAWALLET balance: ' . $e->getMessage());
    nw_json(['error' => 'The configured network RPC could not return a balance. Check its status and retry.'], 502);
}
