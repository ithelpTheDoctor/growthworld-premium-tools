<section class="hero split-hero">
  <div>
    <span class="tag">Premium automation membership</span>
    <h1>Build faster workflows with curated premium tools for browser, Windows, and extension users.</h1>
    <p>Join members who use GrowthWorld tools to save time, improve consistency, and launch faster every day.</p>
    <div class="form-actions">
      <?php if (!$loggedIn): ?>
        <a class="btn btn-primary" href="<?= e(url('/signup')) ?>">Create free account</a>
        <a class="btn btn-muted" href="<?= e(url('/login')) ?>">Login</a>
      <?php elseif (!$isSubscribed): ?>
        <a class="btn btn-primary" href="<?= e(url('/subscribe')) ?>">Start subscription</a>
        <a class="btn btn-muted" href="<?= e(url('/services')) ?>">Browse services</a>
      <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('/services')) ?>">Use premium services</a>
        <a class="btn btn-muted" href="<?= e(url('/feedback')) ?>">Leave feedback</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <h3>What you get</h3>
    <ul class="list-clean tight">
      <li>Unlimited access to available premium tools.</li>
      <li>Regular updates and practical tutorials.</li>
      <li>Member-first support and roadmap improvements.</li>
    </ul>
    <p class="meta">Only $<?= number_format((float)cfg('app.monthly_price'), 2) ?>/month · Cancel anytime.</p>
  </div>
</section>

<section class="section-block">
  <h2 class="section-title">Why professionals choose GrowthWorld</h2>
  <div class="grid-3">
    <article class="card"><h3>1. Discover</h3><p class="meta">Find the right tool quickly from a focused premium catalog built for action.</p></article>
    <article class="card"><h3>2. Subscribe</h3><p class="meta">Activate your plan and unlock secure account-based usage across services.</p></article>
    <article class="card"><h3>3. Scale</h3><p class="meta">Apply repeatable workflows and compound productivity with each release.</p></article>
  </div>
</section>

<section class="section-block">
  <h2 class="section-title">Featured services</h2>
  <div class="grid-3">
    <?php foreach ($featured as $item): ?>
      <article class="card">
        <h3><?= e($item['title']) ?></h3>
        <p class="meta"><?= e($item['seo_description']) ?></p>
        <a class="btn btn-muted" href="<?= e(url('/service/')) ?><?= e($item['slug']) ?>">View service</a>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="section-block">
  <h2 class="section-title">Testimonials & feedback</h2>
  <div class="grid-2">
    <?php foreach ($testimonials as $t): ?>
      <article class="card testimonial">
        <div class="stars"><?= str_repeat('★', (int)$t['rating']) . str_repeat('☆', max(0, 5 - (int)$t['rating'])) ?></div>
        <p>“<?= e($t['review_text']) ?>”</p>
        <p class="meta">— <?= e($t['name']) ?> <?= !empty($t['is_favorite']) ? '· ⭐ Favorite' : '' ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>
