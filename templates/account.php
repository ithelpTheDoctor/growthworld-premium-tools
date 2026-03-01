<section class="hero">
  <h1>My Account</h1>
  <p>Manage your profile and subscription status.</p>
</section>
<section class="card">
  <p><strong>Name:</strong> <?= e($_SESSION['user']['name'] ?? '') ?></p>
  <p><strong>Email:</strong> <?= e($_SESSION['user']['email'] ?? '') ?></p>
  <p><strong>Subscription status:</strong> <?= e($sub['status'] ?? 'NONE') ?></p>
  <div class="form-actions">
    <?php if (($sub['status'] ?? '') === 'ACTIVE'): ?>
      <form method="post" action="<?= e(url('/account/subscription')) ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel"><button class="btn btn-muted" type="submit">Cancel subscription</button></form>
    <?php else: ?>
      <form method="post" action="<?= e(url('/account/subscription')) ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="resume"><button class="btn btn-primary" type="submit">Resume subscription</button></form>
    <?php endif; ?>
  </div>
</section>
