<article class="card">
  <p class="tag"><?= e(service_type_label($service['service_type'] ?? 'browser')) ?></p>
  <h1><?= e($service['title']) ?></h1>
  <img class="feature-image" src="<?= e(url('')) ?>/<?= e(ltrim($service['feature_image'], '/')) ?>" alt="<?= e($service['title']) ?>" loading="lazy">
  <p><?= e($service['seo_description']) ?></p>

  <div class="share-row">
    <span>Share:</span>
    <?php $u = urlencode(url('/service/' . $service['slug'])); $t = urlencode($service['title']); ?>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $u ?>">Facebook</a>
    <a href="https://twitter.com/intent/tweet?url=<?= $u ?>&text=<?= $t ?>">X/Twitter</a>
    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $u ?>">LinkedIn</a>
    <a href="https://wa.me/?text=<?= urlencode($service['title'] . ' ' . url('/service/' . $service['slug'])) ?>">WhatsApp</a>
    <a href="https://t.me/share/url?url=<?= $u ?>&text=<?= $t ?>">Telegram</a>
  </div>

  <?php foreach (preg_split('/\R+/', $service['long_description']) as $para): ?><p class="meta"><?= e($para) ?></p><?php endforeach; ?>

  <h2>Tool features</h2>
  <ul class="list-clean"><?php foreach ($features as $f): ?><li><?= e($f['feature_text']) ?></li><?php endforeach; ?></ul>

  <?php $embed = youtube_embed_url($service['demo_tutorial_url'] ?? ''); ?>
  <?php if ($embed): ?>
    <h2>Service Demo Tutorial</h2>
    <div class="video-wrap"><iframe src="<?= e($embed) ?>" title="Service tutorial" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
  <?php elseif (!empty($service['demo_tutorial_url'])): ?>
    <p><a href="<?= e($service['demo_tutorial_url']) ?>" target="_blank" rel="noopener noreferrer">Watch tutorial on YouTube</a></p>
  <?php endif; ?>

  <h2>How to use</h2>
  <?php if (!is_logged_in()): ?><div class="notice">Create an account and subscribe to unlock complete access.</div><?php endif; ?>

  <?php if ($service['service_type'] === 'windows'): ?>
    <p>Download tool from <a href="<?= e($service['download_url']) ?>" target="_blank" rel="noopener noreferrer">Download Link</a>.</p>
    <p>Use your login credentials in the tool to use it.</p>
  <?php endif; ?>

  <?php if ($service['service_type'] === 'extension'): ?>
    <p>Download extension from <a href="<?= e($service['extension_url']) ?>" target="_blank" rel="noopener noreferrer">Extension</a>.</p>
    <p>Use your login credentials in the tool to use it.</p>
  <?php endif; ?>

  <ul class="list-clean"><?php foreach ($instructions as $ins): ?><li><?= e($ins['instruction_text']) ?></li><?php endforeach; ?></ul>

  <?php if ($service['service_type'] === 'browser'): ?>
    <?php if ($isSubscribed): ?><div class="card"><?= $service['tool_html'] ?></div><?php else: ?><p class="notice">Subscribe to start using this browser-based tool instantly.</p><?php endif; ?>
  <?php elseif (!$isSubscribed): ?>
    <p class="notice">Subscribe to access this <?= e(strtolower(service_type_label($service['service_type']))) ?> fully.</p>
  <?php endif; ?>

  <p class="disclaimer">Disclaimer: Usage of the tool/extension is entirely user responsibility. We are not liable for misuse.</p>
</article>
