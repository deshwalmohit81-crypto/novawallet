<?php
declare(strict_types=1);

function nw_rpc(string $url, string $method, array $params): mixed
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('RPC URL must be an HTTPS URL without embedded credentials.');
    }
    $host = strtolower($parts['host']);
    if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || filter_var($host, FILTER_VALIDATE_IP)) {
        throw new RuntimeException('RPC host is not permitted.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required.');
    }
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    curl_close($handle);
    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('RPC request failed: ' . $curlError);
    }
    $decoded = json_decode($response, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || isset($decoded['error']) || !array_key_exists('result', $decoded)) {
        throw new RuntimeException('RPC returned an error.');
    }
    return $decoded['result'];
}

function nw_hex_to_decimal(string $hex): string
{
    $decimal = '0';
    foreach (str_split(strtolower($hex)) as $digit) {
        $decimal = nw_decimal_add_small(nw_decimal_multiply_small($decimal, 16), hexdec($digit));
    }
    return $decimal;
}

function nw_decimal_multiply_small(string $number, int $factor): string
{
    $carry = 0;
    $result = '';
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $value = ((int) $number[$i]) * $factor + $carry;
        $result = ($value % 10) . $result;
        $carry = intdiv($value, 10);
    }
    while ($carry > 0) {
        $result = ($carry % 10) . $result;
        $carry = intdiv($carry, 10);
    }
    return ltrim($result, '0') ?: '0';
}

function nw_decimal_add_small(string $number, int $add): string
{
    $result = '';
    $carry = $add;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $value = (int) $number[$i] + $carry;
        $result = ($value % 10) . $result;
        $carry = intdiv($value, 10);
    }
    while ($carry > 0) {
        $result = ($carry % 10) . $result;
        $carry = intdiv($carry, 10);
    }
    return ltrim($result, '0') ?: '0';
}

function nw_format_units(string $baseUnits, int $decimals): string
{
    if ($decimals === 0) {
        return $baseUnits;
    }
    $padded = str_pad($baseUnits, $decimals + 1, '0', STR_PAD_LEFT);
    $whole = substr($padded, 0, -$decimals);
    $fraction = rtrim(substr($padded, -$decimals), '0');
    return $whole . ($fraction === '' ? '' : '.' . $fraction);
}

function nw_valid_rpc_url(string $url): bool
{
    $parts = parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }
    $host = strtolower($parts['host']);
    return $host !== 'localhost'
        && !str_ends_with($host, '.localhost')
        && !str_ends_with($host, '.local')
        && !filter_var($host, FILTER_VALIDATE_IP)
        && strlen($url) <= 500;
}
