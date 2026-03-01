<section class="hero">
  <h1>Verify your email</h1>
  <p>Enter the 6-digit OTP sent to your inbox to activate your account.</p>
</section>
<form method="post" action="<?= e(url('/verify-otp')) ?>">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>Email</label>
  <input type="email" name="email" required value="<?= e($email ?? '') ?>">
  <label>OTP Code</label>
  <input name="otp" required pattern="[0-9]{6}" maxlength="6" placeholder="6-digit OTP">
  <div class="form-actions"><button class="btn btn-primary" type="submit">Verify OTP</button></div>
</form>
