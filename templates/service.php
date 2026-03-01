<article class="card">
  <h1><?= e($service['title']) ?></h1>
  <img class="feature-image" src="<?= e(url('/')) ?><?= e($service['feature_image']) ?>" alt="<?= e($service['title']) ?>" loading="lazy">
  <p><?= e($service['seo_description']) ?></p>

  <?php foreach (preg_split('/\R+/', $service['long_description']) as $para): ?>
    <p class="meta"><?= e($para) ?></p>
  <?php endforeach; ?>

  <h2>Tool features</h2>
  <ul class="list-clean">
    <?php foreach ($features as $f): ?><li><?= e($f['feature_text']) ?></li><?php endforeach; ?>
  </ul>


  <?php if (!empty($service['demo_tutorial_url'])): ?>
    <h2>Service Demo Tutorial</h2>
    <p><a href="<?= e($service['demo_tutorial_url']) ?>" target="_blank" rel="noopener">Watch tutorial on YouTube</a></p>
  <?php endif; ?>

  <h2>How to use</h2>
  <?php if (!is_logged_in()): ?><div class="notice">Create an account and subscribe to unlock complete access.</div><?php endif; ?>

  <?php if ($service['service_type'] === 'windows'): ?>
    <p>Download tool from <a href="<?= e($service['download_url']) ?>">Download Link</a>.</p>
    <p>Use your login credentials in the tool to use it.</p>
  <?php endif; ?>

  <?php if ($service['service_type'] === 'extension'): ?>
    <p>Download extension from <a href="<?= e($service['extension_url']) ?>">Extension</a>.</p>
    <p>Use your login credentials in the tool to use it.</p>
  <?php endif; ?>

  <ul class="list-clean">
    <?php foreach ($instructions as $ins): ?><li><?= e($ins['instruction_text']) ?></li><?php endforeach; ?>
  </ul>

  <?php if ($service['service_type'] === 'browser' && is_logged_in()): ?>
    <div class="card"><?= $service['tool_html'] ?></div>
  <?php else: ?>
    <p class="notice">Subscribe to start using this browser-based tool instantly.</p>
  <?php endif; ?>

  <p class="disclaimer">Disclaimer: Usage of the tool/extension is entirely user responsibility. We are not liable for misuse.</p>
</article>
