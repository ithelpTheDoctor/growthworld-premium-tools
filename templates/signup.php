<section class="hero">
  <h1>Create your account</h1>
  <p>Sign up to get access to premium services and member-only features.</p>
</section>
<form method="post" action="<?= e(url('/signup')) ?>">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>Full name</label>
  <input name="name" required minlength="2" maxlength="120">
  <label>Email</label>
  <input type="email" name="email" required>
  <label>Password</label>
  <input type="password" name="password" required minlength="8">
  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Sign up</button>
    <a class="btn btn-muted" href="<?= e(url('/login')) ?>">Already have account?</a>
  </div>
</form>
