<section class="hero">
  <h1>Member feedback</h1>
  <p>Your review helps us prioritize better tools and updates. After submission, admin approval is required before it appears publicly.</p>
</section>

<div id="review-errors"></div>
<form id="review-form">
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>Rating (1-5)</label>
  <select name="rating" required>
    <option value="">Select</option>
    <?php for ($i = 1; $i <= 5; $i++): ?>
      <option value="<?= $i ?>" <?= !empty($myReview) && (int)$myReview['rating'] === $i ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
    <?php endfor; ?>
  </select>

  <label>Your review</label>
  <textarea name="review_text" minlength="20" maxlength="800" required><?= e($myReview['review_text'] ?? '') ?></textarea>

  <?php if (!empty($myReview['status'])): ?>
    <p class="meta">Current status: <strong><?= e($myReview['status']) ?></strong></p>
  <?php endif; ?>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Submit feedback</button>
  </div>
</form>
