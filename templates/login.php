<section class="hero">
  <h1>Login</h1>
  <p>Access your account to use premium services and manage subscription benefits.</p>
</section>
<form method="post" action="<?= e(url('/login')) ?>">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>Email</label>
  <input type="email" name="email" required>
  <label>Password</label>
  <input type="password" name="password" required>
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Login</button>
    <a class="btn btn-muted" href="<?= e(url('/signup')) ?>">Create account</a>
  </div>
</form>
