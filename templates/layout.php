<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php $m = $meta ?? []; ?>
  <title><?= e($m['title'] ?? ($title ?? 'GrowthWorld Premium Tools')) ?></title>
  <meta name="description" content="<?= e($m['description'] ?? 'Premium GrowthWorld tool platform with secure membership, subscriptions, and productivity services.') ?>">
  <meta property="og:title" content="<?= e($m['title'] ?? ($title ?? 'GrowthWorld Premium Tools')) ?>">
  <meta property="og:description" content="<?= e($m['description'] ?? 'Premium GrowthWorld tools platform.') ?>">
  <meta property="og:type" content="<?= e($m['type'] ?? 'website') ?>">
  <meta property="og:url" content="<?= e(url($_SERVER['REQUEST_URI'] ?? '/')) ?>">
  <meta property="og:image" content="<?= e($m['og_image'] ?? url('/static/images/default-og.webp')) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($m['title'] ?? ($title ?? 'GrowthWorld Premium Tools')) ?>">
  <meta name="twitter:description" content="<?= e($m['description'] ?? 'Premium GrowthWorld tools platform.') ?>">
  <meta name="twitter:image" content="<?= e($m['og_image'] ?? url('/static/images/default-og.webp')) ?>">
  <?php if (!empty($m['jsonld'])): ?><script type="application/ld+json"><?= json_encode($m['jsonld'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>

  <link rel="stylesheet" href="<?= e(url('/static/css/app.css')) ?>">
  <script>
    window.GW_XOR_OBF = "<?= e(base64_encode(strrev(cfg('app.xor_key')))) ?>";
    window.GW_BASE = "<?= e(url('')) ?>";
  </script>
  <script defer src="<?= e(url('/static/js/app.js')) ?>"></script>
</head>
<body>
<?php $navSubscribed = is_logged_in() && !empty($_SESSION['user']['id']) && user_has_active_subscription((int)$_SESSION['user']['id']); $isAdmin = !empty($_SESSION['admin']); ?>
<header class="site-header">
  <div class="container nav-row">
    <a class="brand" href="<?= e(url('/')) ?>">GrowthWorld Premium Tools</a>
    <button id="menu-toggle" class="btn btn-muted menu-toggle" type="button" aria-label="Open menu">☰</button>
    <div id="menu-backdrop" class="menu-backdrop"></div>
    <nav id="main-nav">
      <button id="menu-close" class="btn btn-muted menu-close" type="button" aria-label="Close menu">✕</button>
      <a href="<?= e(url('/services')) ?>">Services</a>
      <?php if ($navSubscribed): ?><a href="<?= e(url('/feedback')) ?>">Feedback</a><?php endif; ?>
      <?php if ($isAdmin): ?><a href="<?= e(url('/admin')) ?>">Admin</a><?php endif; ?>
      <?php if ($loggedIn = is_logged_in()): ?><a href="<?= e(url('/account')) ?>">Account</a><?php endif; ?>
      <a href="<?= e(url('/contact-us')) ?>">Contact</a>
      <a href="<?= e(url('/privacy-policy')) ?>">Privacy</a>
      <a href="<?= e(url('/terms-of-service')) ?>">Terms</a>
      <?php if (!$loggedIn): ?>
        <a class="btn btn-primary" href="<?= e(url('/signup')) ?>">Sign up</a>
        <a class="btn btn-muted" href="<?= e(url('/login')) ?>">Login</a>
      <?php else: ?>
        <a class="btn btn-muted" href="<?= e(url('/logout')) ?>">Logout</a>
      <?php endif; ?>
      <?php if ($isAdmin): ?><a class="btn btn-muted" href="<?= e(url('/admin/logout')) ?>">Admin Logout</a><?php endif; ?>
      <button id="theme-toggle" class="btn btn-muted theme-icon" type="button" aria-label="Toggle dark or light mode">🌙</button>
    </nav>
  </div>
</header>
<main><div class="container"><?php if (!empty($_SESSION['flash'])): ?><p class="notice"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></p><?php endif; ?><?php include __DIR__ . '/' . $view . '.php'; ?></div></main>
<footer class="site-footer"><div class="container"><p>Contact: <?= e(cfg('app.public_email')) ?> · Services may be changed or removed at any time without prior notice.</p></div></footer>
</body>
</html>
