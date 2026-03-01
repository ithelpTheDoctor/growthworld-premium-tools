<section class="hero">
  <h1>Contact GrowthWorld Premium Tools</h1>
  <p>Need support with billing, account access, subscriptions, or service usage? Our team reviews every message and responds as quickly as possible.</p>
  <p>Before submitting, please include as much detail as possible so we can help you in one reply.</p>
</section>

<section class="grid-2 section-block">
  <article class="card">
    <h2>Support information</h2>
    <p><strong>Public support email:</strong> <?= e(cfg('app.public_email')) ?></p>
    <p><strong>Internal routing:</strong> all support form messages are handled securely by our internal support workflow.</p>
    <p>We support users globally. If your request is related to data rights or account privacy, mention your country/region in the form.</p>
  </article>

  <form method="post" action="<?= e(url('/contact-us')) ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Your name</label>
    <input name="name" required minlength="2" maxlength="120">
    <label>Email address</label>
    <input name="email" type="email" required>
    <label>Region (optional)</label>
    <input name="region" maxlength="120" placeholder="e.g. United States, Germany">
    <label>Message</label>
    <textarea name="message" required minlength="20" maxlength="2000" rows="8" placeholder="Please describe the issue, what you expected, and what happened."></textarea>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Send message</button>
    </div>
  </form>
</section>
