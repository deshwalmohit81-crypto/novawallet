<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
nw_page('Activity', 'activity', static function (): void {
    $networks = [];
    try { $networks = nw_networks(); } catch (PDOException $e) { $networkError = 'Database setup is required to load networks.'; }
    ?>
    <div class="page-heading"><div><span class="eyebrow">PUBLIC ONCHAIN RECORDS</span><h1>Activity<span class="heading-period">.</span></h1><p>Transaction history from a configured explorer API, when available.</p></div><span class="read-only-badge">EXPLORER DATA</span></div>
    <?php if (!empty($networkError)): ?><div class="notice notice-error"><?= nw_h($networkError) ?></div><?php endif; ?>
    <section class="panel portfolio-query"><form class="lookup-form" id="activity-form"><label class="sr-only" for="activity-address">Public EVM address</label><span class="input-prefix">⌘</span><input id="activity-address" type="text" inputmode="text" autocomplete="off" spellcheck="false" placeholder="0x... public address" maxlength="42"><label class="sr-only" for="activity-network">Network</label><select class="network-select" id="activity-network"><?php foreach ($networks as $network): ?><option value="<?= (int) $network['chain_id'] ?>"><?= nw_h($network['name']) ?></option><?php endforeach; ?></select><button class="button button-primary" type="submit">Check activity <span>→</span></button></form><div class="form-hint"><span>ⓘ</span> Address is sent to this server and the configured explorer API for this request; it is not stored.</div></section>
    <div class="section-title-row"><div><span class="eyebrow">TRANSACTION HISTORY</span><h2>Recent activity</h2></div><span class="updated-label" id="activity-updated">No address checked yet</span></div>
    <section class="panel activity-list" id="activity-list"><div class="empty-state-inline"><span class="empty-icon">↗</span><strong>No activity loaded</strong><span>Enter a public address to check for verified explorer records.</span></div></section>
    <div class="toast-region" id="toast-region" aria-live="polite"></div>
    <?php
});
