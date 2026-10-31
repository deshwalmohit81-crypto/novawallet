<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
nw_page('Overview', 'dashboard', static function (): void {
    $networks = [];
    try { $networks = nw_networks(); } catch (PDOException $e) { $networkError = 'Complete database setup to load configured networks.'; }
    ?>
    <div class="page-heading"><div><span class="eyebrow">YOUR ONCHAIN SNAPSHOT</span><h1>Overview<span class="heading-period">.</span></h1><p>A read-only view of balances from public network data.</p></div><span class="read-only-badge">⬡ &nbsp; READ-ONLY VIEW</span></div>
    <?php if (!empty($networkError)): ?><div class="notice notice-error"><?= nw_h($networkError) ?></div><?php endif; ?>
    <section class="welcome-banner"><div class="welcome-orb"></div><div><span class="eyebrow">WELCOME TO YOUR SPACE</span><h2>Your portfolio, <span class="gradient-text">in focus.</span></h2><p>Enter a public address to fetch live balances directly from configured network RPCs.</p></div><div class="welcome-mark">N</div></section>
    <section class="address-entry panel"><div class="panel-heading"><div><span class="eyebrow">PUBLIC WALLET ADDRESS</span><h2>Start with an address</h2></div><span class="privacy-chip">⌑ &nbsp; No connection required</span></div>
      <form class="lookup-form" id="portfolio-form"><label class="sr-only" for="address-input">Public EVM address</label><span class="input-prefix">⌘</span><input id="address-input" name="address" type="text" inputmode="text" autocomplete="off" spellcheck="false" placeholder="0x... enter a public EVM address" maxlength="42"><button class="button button-primary" type="submit"><span>View portfolio</span><span>→</span></button></form>
      <div class="form-hint"><span>ⓘ</span> Public address only. Sent to this server and the selected RPC for this request; not stored. Never enter a seed phrase or key.</div>
    </section>
    <div class="section-title-row"><div><span class="eyebrow">NETWORKS</span><h2>Balance snapshot</h2></div><span class="updated-label" id="last-updated">No address checked yet</span></div>
    <div class="balance-grid" id="balance-grid">
      <?php foreach ($networks as $network): ?>
        <article class="balance-card panel" data-chain-id="<?= (int) $network['chain_id'] ?>"><div class="token-avatar token-<?= (int) $network['chain_id'] ?>"><?= nw_h(substr($network['currency_symbol'], 0, 1)) ?></div><div class="balance-main"><div class="balance-name"><?= nw_h($network['name']) ?></div><div class="balance-symbol"><?= nw_h($network['currency_symbol']) ?> <span class="network-id">· <?= (int) $network['chain_id'] ?></span></div></div><div class="balance-value" data-balance>— <small><?= nw_h($network['currency_symbol']) ?></small></div><span class="network-status status-idle" data-status>Waiting</span></article>
      <?php endforeach; ?>
      <?php if (!$networks): ?><div class="empty-state panel"><span class="empty-icon">◈</span><h3>No networks configured</h3><p>Add the schema to your MySQL database, then refresh.</p></div><?php endif; ?>
    </div>
    <div class="panel disclaimer-panel"><span class="disclaimer-icon">ⓘ</span><p><strong>Public data, not a valuation.</strong> Balances are fetched from configured RPC endpoints when you request them. Fiat prices, bridged assets, and unconfigured tokens are not included. No balance is simulated.</p></div>
    <div class="toast-region" id="toast-region" aria-live="polite"></div>
    <?php
});
