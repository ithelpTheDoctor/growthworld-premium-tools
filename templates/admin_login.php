<section class="hero">
  <h1>Admin dashboard access</h1>
  <p>Secure sign-in for service publishing and subscription operations.</p>
</section>

<form method="post" action="<?= e(url('/admin/login')) ?>">
  <?php if (!empty($_SESSION['flash'])): ?><p class="notice"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></p><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>User</label>
  <input name="username" required>
  <label>Password</label>
  <input type="password" name="password" required>
  <div class="form-actions">
    <button class="btn btn-primary">Login</button>
  </div>
</form>
