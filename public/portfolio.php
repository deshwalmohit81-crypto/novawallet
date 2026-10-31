<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
nw_page('Portfolio', 'portfolio', static function (): void {
    $networks = [];
    try { $networks = nw_networks(); } catch (PDOException $e) { $networkError = 'Database setup is needed before live balances are available.'; }
    ?>
    <div class="page-heading"><div><span class="eyebrow">ASSETS ONCHAIN</span><h1>Portfolio<span class="heading-period">.</span></h1><p>Native coin and administrator-configured ERC-20 balances.</p></div><span class="read-only-badge">PUBLIC DATA ONLY</span></div>
    <?php if (!empty($networkError)): ?><div class="notice notice-error"><?= nw_h($networkError) ?></div><?php endif; ?>
    <section class="panel portfolio-query"><form class="lookup-form" id="portfolio-form"><label class="sr-only" for="address-input">Public EVM address</label><span class="input-prefix">⌘</span><input id="address-input" name="address" type="text" inputmode="text" autocomplete="off" spellcheck="false" placeholder="0x... public address" maxlength="42"><label class="sr-only" for="network-select">Network</label><select class="network-select" id="network-select" name="chain_id"><option value="">All networks</option><?php foreach ($networks as $network): ?><option value="<?= (int) $network['chain_id'] ?>"><?= nw_h($network['name']) ?></option><?php endforeach; ?></select><button class="button button-primary" type="submit">Load balances <span>→</span></button></form><div class="form-hint"><span>ⓘ</span> Sent to this server and selected public RPC for this request; not stored. Up to five configured tokens per network.</div></section>
    <div class="section-title-row"><div><span class="eyebrow">ONCHAIN ASSETS</span><h2>Token balances</h2></div><span class="updated-label" id="last-updated">Awaiting a public address</span></div>
    <div class="panel asset-table-wrap"><table class="asset-table"><thead><tr><th>ASSET / NETWORK</th><th>CONTRACT</th><th class="align-right">BALANCE</th></tr></thead><tbody id="token-rows"><tr class="table-empty"><td colspan="3"><div class="empty-state-inline"><span class="empty-icon">◫</span><strong>Nothing to display yet</strong><span>Enter an address to query public onchain balances.</span></div></td></tr></tbody></table></div>
    <div class="panel disclaimer-panel"><span class="disclaimer-icon">ⓘ</span><p>Only the native coin and tokens explicitly configured by the administrator are queried. No fiat prices, guessed token lists, or demo balances are shown.</p></div><div class="toast-region" id="toast-region" aria-live="polite"></div>
    <?php
});
