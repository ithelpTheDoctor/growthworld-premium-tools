<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'GrowthWorld Premium Tools') ?></title>
  <meta name="description" content="Premium GrowthWorld tool platform with secure membership, subscriptions, and productivity services.">
  <link rel="stylesheet" href="/static/css/app.css">
  <script>window.GW_XOR_OBF = "<?= e(base64_encode(strrev(cfg('app.xor_key')))) ?>";</script>
  <script defer src="/static/js/app.js"></script>
</head>
<body>
<?php $navSubscribed = is_logged_in() && !empty($_SESSION['user']['id']) && user_has_active_subscription((int)$_SESSION['user']['id']); ?>
<header class="site-header">
  <div class="container nav-row">
    <a class="brand" href="/">GrowthWorld Premium Tools</a>
    <nav>
      <a href="/services">Services</a>
      <?php if ($navSubscribed): ?><a href="/feedback">Feedback</a><?php endif; ?>
      <a href="/contact-us">Contact</a>
      <a href="/privacy-policy">Privacy</a>
      <a href="/terms-of-service">Terms</a>
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
