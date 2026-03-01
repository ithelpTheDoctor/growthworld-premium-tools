<section class="hero">
  <h1>Premium service catalog</h1>
  <p>Explore specialized tools, scripts, and extension workflows included in your membership.</p>
</section>

<?php if (!$services): ?>
  <section class="card"><p>No services published yet. Please check back soon.</p></section>
<?php else: ?>
<div class="grid-2">
  <?php foreach ($services as $svc): ?>
    <article class="card">
      <p class="tag"><?= e(service_type_label($svc['service_type'] ?? 'browser')) ?></p>
      <h2><?= e($svc['title']) ?></h2>
      <p class="meta"><?= e($svc['seo_description']) ?></p>
      <a class="btn btn-primary" href="<?= e(url('/service/')) ?><?= e($svc['slug']) ?>">Use this service</a>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($hasMore)): ?>
<div class="form-actions">
  <a class="btn btn-muted" href="<?= e(url('/services')) ?>?p=<?= $pageNum + 1 ?>">Load more services</a>
</div>
<?php endif; ?>
