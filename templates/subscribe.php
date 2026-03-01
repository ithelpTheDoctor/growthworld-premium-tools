<section class="hero">
  <h1>Subscription</h1>
  <p>Activate your premium plan to unlock all services and member feedback access.</p>
</section>
<div class="card">
  <p><strong>Plan:</strong> GrowthWorld Premium Tools</p>
  <p><strong>Price:</strong> $<?= number_format((float)cfg('app.monthly_price'), 2) ?> / month</p>
  <?php if (!empty($isSubscribed)): ?>
    <p class="ok">Your subscription is already active.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('/subscribe')) ?>">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button class="btn btn-primary" type="submit">Activate subscription</button>
    </form>
  <?php endif; ?>
</div>
