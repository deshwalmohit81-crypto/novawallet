<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

nw_require_method('POST');
nw_require_csrf();
nw_release_session_lock();
try {
    nw_rate_limit('transactions', 20);
    $body = nw_json_input();
    $address = is_array($body) ? trim(nw_text_field($body, 'address')) : '';
    $chainId = is_array($body) ? nw_integer_field($body, 'chain_id') : false;
    if (!nw_validate_address($address) || $chainId === false || $chainId < 1) {
        nw_json(['error' => 'A valid public EVM address and network are required.'], 422);
    }
    $network = nw_network_by_id((int) $chainId);
    if (!$network) {
        nw_json(['error' => 'That network is unavailable or disabled.'], 404);
    }
    if (empty($network['explorer_api_url'])) {
        nw_json(['transactions' => [], 'configured' => false, 'message' => 'No transaction-history provider is configured for this network. No activity is available to show.']);
    }
    $urlParts = parse_url($network['explorer_api_url']);
    if (!$urlParts || ($urlParts['scheme'] ?? '') !== 'https' || empty($urlParts['host']) || isset($urlParts['user']) || isset($urlParts['pass'])) {
        throw new RuntimeException('Explorer API URL must be a valid HTTPS URL.');
    }
    $query = ['chainid' => (int) $network['chain_id'], 'module' => 'account', 'action' => 'txlist', 'address' => $address, 'startblock' => 0, 'endblock' => 99999999, 'page' => 1, 'offset' => 15, 'sort' => 'desc'];
    if (!empty($network['explorer_api_key'])) {
        $query['apikey'] = $network['explorer_api_key'];
    }
    $url = $network['explorer_api_url'] . (str_contains($network['explorer_api_url'], '?') ? '&' : '?') . http_build_query($query);
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Explorer API request failed.');
    }
    $decoded = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || !array_key_exists('result', $decoded)) {
        throw new RuntimeException('Explorer API response format is not supported. Configure an Etherscan-compatible transaction API.');
    }
    if ((string) ($decoded['status'] ?? '') === '0'
        && (string) ($decoded['message'] ?? '') !== 'No transactions found'
        && (string) ($decoded['result'] ?? '') !== 'No transactions found') {
        throw new RuntimeException('Explorer API reported an unsuccessful request.');
    }
    if (!is_array($decoded['result'])) {
        $noTransactions = (string) ($decoded['message'] ?? '') === 'No transactions found'
            || (string) ($decoded['result'] ?? '') === 'No transactions found';
        if ($noTransactions) {
            nw_json(['transactions' => [], 'configured' => true, 'updated_at' => gmdate(DATE_ATOM), 'demo' => false]);
        }
        throw new RuntimeException('Explorer API response format is not supported. Configure an Etherscan-compatible transaction API.');
    }
    $transactions = [];
    foreach (array_slice($decoded['result'], 0, 15) as $transaction) {
        if (!is_array($transaction) || !preg_match('/^[a-fA-F0-9]{64}$/D', (string) ($transaction['hash'] ?? ''))) {
            continue;
        }
        $rawValue = (string) ($transaction['value'] ?? '0');
        if (!preg_match('/^\d+$/D', $rawValue)) {
            continue;
        }
        $transactions[] = [
            'hash' => strtolower($transaction['hash']),
            'from' => (string) ($transaction['from'] ?? ''),
            'to' => (string) ($transaction['to'] ?? ''),
            'value' => nw_format_units($rawValue, 18),
            'timestamp' => is_numeric($transaction['timeStamp'] ?? null) ? gmdate(DATE_ATOM, (int) $transaction['timeStamp']) : null,
            'status' => (($transaction['txreceipt_status'] ?? '') === '0' || ($transaction['isError'] ?? '') === '1')
                ? 'failed'
                : ((is_numeric($transaction['blockNumber'] ?? null) && (int) $transaction['blockNumber'] > 0) ? 'confirmed' : 'recorded'),
        ];
    }
    nw_json(['transactions' => $transactions, 'configured' => true, 'updated_at' => gmdate(DATE_ATOM), 'demo' => false]);
} catch (JsonException $e) {
    nw_json(['error' => 'Request data must be valid JSON.'], 400);
} catch (PDOException $e) {
    error_log('NOVAWALLET transactions database: ' . $e->getMessage());
    nw_json(['error' => 'Transaction history is temporarily unavailable.'], 503);
} catch (Throwable $e) {
    error_log('NOVAWALLET transactions: ' . $e->getMessage());
    nw_json(['error' => 'The configured transaction provider could not return activity. Check the provider configuration and retry.'], 502);
}
