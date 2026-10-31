<?php
declare(strict_types=1);

function nw_h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nw_csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function nw_is_admin(): bool
{
    return !empty($_SESSION['nw_admin']);
}

function nw_release_session_lock(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function nw_networks(bool $enabledOnly = true): array
{
    $query = 'SELECT chain_id, name, short_name, currency_symbol, explorer_url, rpc_url, enabled FROM networks';
    if ($enabledOnly) {
        $query .= ' WHERE enabled = 1';
    }
    $query .= ' ORDER BY sort_order, name';
    return nw_db()->query($query)->fetchAll();
}

function nw_page(string $title, string $active, callable $content): void
{
    $base = rtrim((string) (nw_config()['base_path'] ?? ''), '/');
    $csrf = nw_csrf_token();
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#080a13">
  <meta name="description" content="NOVAWALLET — a read-only, non-custodial EVM portfolio viewer.">
  <meta name="csrf-token" content="<?= nw_h($csrf) ?>">
  <meta name="base-path" content="<?= nw_h($base) ?>">
  <title><?= nw_h($title) ?> · NOVAWALLET</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= nw_h($base) ?>/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= nw_h($base) ?>/index.php"><span class="brand-mark">N</span><span>NOVA<span class="brand-light">WALLET</span></span></a>
    <div class="side-label">WORKSPACE</div>
    <nav class="side-nav" aria-label="Main navigation">
      <?php
      $links = [
          'dashboard' => ['Overview', '◈', '/dashboard.php'],
          'portfolio' => ['Portfolio', '◫', '/portfolio.php'],
          'activity' => ['Activity', '↗', '/activity.php'],
          'wallet' => ['Address viewer', '⌘', '/wallet.php'],
      ];
      foreach ($links as $key => [$label, $icon, $path]): ?>
        <a class="side-link <?= $active === $key ? 'active' : '' ?>" href="<?= nw_h($base . $path) ?>"><span class="side-icon"><?= nw_h($icon) ?></span><?= nw_h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-label side-label-spaced">PREFERENCES</div>
    <nav class="side-nav" aria-label="Preferences">
      <a class="side-link <?= $active === 'settings' ? 'active' : '' ?>" href="<?= nw_h($base) ?>/settings.php"><span class="side-icon">⚙</span>Settings</a>
      <?php if (nw_is_admin()): ?><a class="side-link <?= $active === 'admin' ? 'active' : '' ?>" href="<?= nw_h($base) ?>/admin.php"><span class="side-icon">⌘</span>Admin console</a><?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
      <div class="sidebar-note"><span class="note-shield">⬡</span><div><strong>Read-only by design</strong><small>Your keys stay yours. Always.</small></div></div>
      <div class="sidebar-user"><span class="avatar">N</span><span><strong>Guest session</strong><small>Local preferences only</small></span><span class="user-dots">···</span></div>
    </div>
  </aside>
  <div class="main-column">
    <header class="topbar"><button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">☰</button><div class="breadcrumb">Workspace <span>/</span> <strong><?= nw_h($title) ?></strong></div><div class="top-actions"><span class="live-pill"><i></i> Public data only</span><a class="icon-button" href="<?= nw_h($base) ?>/settings.php" aria-label="Settings">⚙</a><span class="avatar avatar-small">N</span></div></header>
    <main class="page-content"><?php $content(); ?></main>
    <footer class="app-footer"><span>© <?= date('Y') ?> NOVAWALLET</span><span>Independent read-only portfolio viewer · Not financial advice</span></footer>
  </div>
</div>
<script src="<?= nw_h($base) ?>/assets/js/app.js" defer></script>
</body>
</html><?php
}

function nw_network_by_id(int $chainId, bool $enabledOnly = true): ?array
{
    $enabledFilter = $enabledOnly ? ' AND enabled = 1' : '';
    $stmt = nw_db()->prepare('SELECT chain_id, name, short_name, currency_symbol, explorer_url, rpc_url, explorer_api_url, explorer_api_key, enabled FROM networks WHERE chain_id = ?' . $enabledFilter);
    $stmt->execute([$chainId]);
    $network = $stmt->fetch();
    return $network ?: null;
}

function nw_validate_address(string $address): bool
{
    return (bool) preg_match('/^0x[a-fA-F0-9]{40}$/D', $address);
}

function nw_json_input(): mixed
{
    $json = file_get_contents('php://input', false, null, 0, 32769);
    if ($json === false || strlen($json) > 32768) {
        nw_json(['error' => 'Request body is too large.'], 413);
    }
    return json_decode($json, true, 16, JSON_THROW_ON_ERROR);
}

function nw_text_field(array $body, string $key): string
{
    $value = $body[$key] ?? '';
    return is_string($value) ? $value : '';
}

function nw_integer_field(array $body, string $key): int|false
{
    $value = $body[$key] ?? null;
    if (!is_int($value) && !is_string($value)) {
        return false;
    }
    return filter_var($value, FILTER_VALIDATE_INT);
}
