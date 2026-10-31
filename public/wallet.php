<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
nw_page('Address viewer', 'wallet', static function (): void {
    ?>
    <div class="page-heading"><div><span class="eyebrow">PUBLIC ADDRESS TOOLS</span><h1>Address viewer<span class="heading-period">.</span></h1><p>Inspect a public address and make a QR code locally in your browser.</p></div><span class="read-only-badge">NO WALLET CONNECTION</span></div>
    <section class="panel address-view-panel"><div class="panel-heading"><div><span class="eyebrow">EVM ADDRESS</span><h2>Look up a public address</h2></div><span class="privacy-chip">⌑ &nbsp; Stays in this browser</span></div><form class="lookup-form" id="address-view-form"><label class="sr-only" for="viewer-address">Public EVM address</label><span class="input-prefix">⌘</span><input id="viewer-address" type="text" inputmode="text" autocomplete="off" spellcheck="false" placeholder="0x... enter a public address" maxlength="42"><button class="button button-primary" type="submit">View address <span>→</span></button></form><div class="form-hint"><span>ⓘ</span> This tool never connects a wallet or requests secret credentials.</div></section>
    <section class="address-result-grid"><article class="panel address-details" id="address-details"><span class="eyebrow">ADDRESS DETAILS</span><div class="address-placeholder"><span class="empty-icon">⌘</span><h3>Ready when you are</h3><p>Enter a public EVM address to view its format and explorer links.</p></div></article><article class="panel qr-panel"><span class="eyebrow">SCAN ADDRESS</span><div id="qr-display" class="qr-display"><span class="qr-placeholder">QR<br>PREVIEW</span></div><p>Generated locally in your browser. The address is not sent to a QR service.</p><button class="button button-outline button-small" id="generate-qr" type="button" disabled>Generate QR code</button></article></section>
    <div class="toast-region" id="toast-region" aria-live="polite"></div>
    <?php
});
