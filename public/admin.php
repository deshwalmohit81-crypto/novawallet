<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
nw_page('Admin console', 'admin', static function (): void {
    if (!nw_is_admin()) {
        ?>
        <div class="page-heading"><div><span class="eyebrow">SITE OPERATIONS</span><h1>Admin console<span class="heading-period">.</span></h1><p>Sign in to manage supported network configuration.</p></div><span class="read-only-badge">RESTRICTED ACCESS</span></div>
        <section class="panel admin-login-card"><div class="settings-icon">⌘</div><span class="eyebrow">ADMIN ACCESS</span><h2>Sign in to manage NOVAWALLET</h2><p>Admin access is configured on the server. No user account is required to view a public portfolio.</p><form id="admin-login-form" class="admin-login-form"><label for="admin-password">Admin password</label><input id="admin-password" type="password" autocomplete="current-password" maxlength="1024" required><button class="button button-primary" type="submit">Continue <span>→</span></button></form><div class="admin-feedback" id="admin-feedback" aria-live="polite"></div></section>
        <?php
        return;
    }
    $networks = [];
    $tokens = [];
    try {
        $networks = nw_db()->query('SELECT chain_id, name, short_name, currency_symbol, explorer_url, rpc_url, explorer_api_url, explorer_api_key, enabled, last_health_check, last_health_ok FROM networks ORDER BY sort_order, name')->fetchAll();
        $tokens = nw_db()->query('SELECT id, chain_id, contract_address, name, symbol, decimals FROM tokens ORDER BY chain_id, name')->fetchAll();
    } catch (PDOException $e) {
        ?>
        <div class="page-heading"><div><span class="eyebrow">SITE OPERATIONS</span><h1>Admin console<span class="heading-period">.</span></h1></div></div><div class="notice notice-error">Database setup is incomplete. Import database/schema.sql before managing network configuration.</div>
        <?php return;
    }
    ?>
    <div class="page-heading"><div><span class="eyebrow">SITE OPERATIONS</span><h1>Admin console<span class="heading-period">.</span></h1><p>Manage RPCs, explorer APIs, network availability, and read-only token metadata.</p></div><button class="button button-outline" id="admin-logout" type="button">Sign out</button></div>
    <div class="notice notice-info">RPC and explorer endpoints are stored on the server and never included in public network responses. Use trusted providers and HTTPS URLs.</div>
    <div class="section-title-row"><div><span class="eyebrow">NETWORK CONFIGURATION</span><h2>Supported networks</h2></div><span class="updated-label">7 networks</span></div>
    <div class="admin-network-list">
    <?php foreach ($networks as $network): ?>
      <details class="panel admin-network" data-chain-id="<?= (int) $network['chain_id'] ?>"><summary><span class="token-avatar token-<?= (int) $network['chain_id'] ?>"><?= nw_h(substr($network['currency_symbol'], 0, 1)) ?></span><span class="admin-network-name"><strong><?= nw_h($network['name']) ?></strong><small>Chain <?= (int) $network['chain_id'] ?> · <?= nw_h($network['currency_symbol']) ?></small></span><span class="health-tag <?= $network['last_health_ok'] === null ? 'health-unknown' : ((int) $network['last_health_ok'] === 1 ? 'health-good' : 'health-bad') ?>" title="<?= nw_h($network['last_health_check'] ? gmdate('Y-m-d H:i:s', strtotime($network['last_health_check'])) . ' UTC' : 'No health check recorded') ?>"><?= $network['last_health_ok'] === null ? 'Not checked' : ((int) $network['last_health_ok'] === 1 ? 'Healthy' : 'Check failed') ?></span><span class="details-chevron">⌄</span></summary>
        <form class="network-settings-form">
          <div class="admin-fields"><label>Display name<input name="name" value="<?= nw_h($network['name']) ?>" maxlength="80" required></label><label>Native symbol<input name="currency_symbol" value="<?= nw_h($network['currency_symbol']) ?>" maxlength="12" required></label><label class="field-wide">Public RPC URL<input name="rpc_url" type="url" value="<?= nw_h($network['rpc_url']) ?>" placeholder="https://public-rpc.example" maxlength="500"><small>Server-side only. Provider credentials, if used, are never returned to visitor browsers.</small></label><label>Explorer URL<input name="explorer_url" type="url" value="<?= nw_h($network['explorer_url']) ?>" maxlength="255" required></label><label>Explorer API URL<input name="explorer_api_url" type="url" value="<?= nw_h($network['explorer_api_url']) ?>" placeholder="Etherscan-compatible API endpoint" maxlength="500"></label><label class="field-wide">Explorer API key<input name="explorer_api_key" type="password" autocomplete="new-password" maxlength="255" placeholder="Optional; stored server-side"><small>Use an Etherscan-compatible account/txlist API. Leave blank to keep the current key.</small><span class="admin-inline-check"><input name="clear_explorer_api_key" type="checkbox" value="1"> Clear the currently stored key</span></label><label class="settings-toggle admin-enabled"><span><strong>Network enabled</strong><small>Disabled networks are not returned to public visitors.</small></span><input name="enabled" type="checkbox" <?= (int) $network['enabled'] === 1 ? 'checked' : '' ?>><i></i></label></div>
          <div class="admin-form-actions"><button class="button button-primary button-small" type="submit">Save network</button><button class="button button-outline button-small health-check" type="button">Check RPC health</button><span class="admin-feedback" aria-live="polite"></span></div>
        </form>
      </details>
    <?php endforeach; ?>
    </div>
    <div class="section-title-row admin-section-spaced"><div><span class="eyebrow">TOKEN METADATA</span><h2>Read-only ERC-20 list</h2></div></div>
    <section class="panel token-admin-panel"><p>Only configured contracts are queried. Confirm the contract address, symbol, and decimals using a trusted source before adding.</p><form id="token-add-form" class="admin-fields token-add-form"><label>Network<select name="chain_id" required><?php foreach ($networks as $network): ?><option value="<?= (int) $network['chain_id'] ?>"><?= nw_h($network['name']) ?></option><?php endforeach; ?></select></label><label>Token name<input name="name" maxlength="80" required></label><label>Symbol<input name="symbol" maxlength="20" required></label><label>Decimals<input name="decimals" type="number" min="0" max="36" value="18" required></label><label class="field-wide">Contract address<input name="contract_address" spellcheck="false" maxlength="42" placeholder="0x..." required></label><div class="admin-form-actions"><button class="button button-primary button-small" type="submit">Add token</button><span class="admin-feedback" aria-live="polite"></span></div></form>
      <div class="configured-tokens"><?php if (!$tokens): ?><div class="empty-state-inline"><strong>No ERC-20 tokens configured</strong><span>Native coin balances remain available when RPC is configured.</span></div><?php else: foreach ($tokens as $token): ?><div class="configured-token"><span><strong><?= nw_h($token['symbol']) ?></strong><small><?= nw_h($token['name']) ?> · chain <?= (int) $token['chain_id'] ?> · <?= (int) $token['decimals'] ?> decimals</small></span><code><?= nw_h($token['contract_address']) ?></code><button class="button button-outline button-small token-delete" type="button" data-token-id="<?= (int) $token['id'] ?>" aria-label="Remove <?= nw_h($token['symbol']) ?>">Remove</button></div><?php endforeach; endif; ?></div>
    </section>
    <div class="toast-region" id="toast-region" aria-live="polite"></div>
    <?php
});
