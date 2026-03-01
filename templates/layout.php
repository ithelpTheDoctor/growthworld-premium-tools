<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'GrowthWorld Premium Tools') ?></title>
  <meta name="description" content="Premium GrowthWorld tool platform with secure membership, subscriptions, and productivity services.">
  <link rel="stylesheet" href="<?= e(url('/static/css/app.css')) ?>">
  <script>
    window.GW_XOR_OBF = "<?= e(base64_encode(strrev(cfg('app.xor_key')))) ?>";
    window.GW_BASE = "<?= e(url('')) ?>";
  </script>
  <script defer src="<?= e(url('/static/js/app.js')) ?>"></script>
</head>
<body>
<?php $navSubscribed = is_logged_in() && !empty($_SESSION['user']['id']) && user_has_active_subscription((int)$_SESSION['user']['id']); ?>
<header class="site-header">
  <div class="container nav-row">
    <a class="brand" href="<?= e(url('/')) ?>">GrowthWorld Premium Tools</a>
    <nav>
      <a href="<?= e(url('/services')) ?>">Services</a>
      <?php if ($navSubscribed): ?><a href="<?= e(url('/feedback')) ?>">Feedback</a><?php endif; ?>
      <a href="<?= e(url('/contact-us')) ?>">Contact</a>
      <a href="<?= e(url('/privacy-policy')) ?>">Privacy</a>
      <a href="<?= e(url('/terms-of-service')) ?>">Terms</a>
      <button id="theme-toggle" class="btn btn-muted" type="button" aria-label="Toggle dark or light mode">Theme</button>
    </nav>
  </div>
</header>
<main>
  <div class="container">
    <?php include __DIR__ . '/' . $view . '.php'; ?>
  </div>
</main>
<footer class="site-footer">
  <div class="container">
    <p>Contact: <?= e(cfg('app.public_email')) ?> · Services may be changed or removed at any time without prior notice.</p>
  </div>
</footer>
</body>
</html>
