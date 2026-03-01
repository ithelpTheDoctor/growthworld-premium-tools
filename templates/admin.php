<section class="hero">
  <h1>Admin Portal</h1>
  <p>Manage service publishing and moderation from one dashboard.</p>
  <div class="form-actions">
    <a class="btn <?= $tab === 'create' ? 'btn-primary' : 'btn-muted' ?>" href="<?= e(url('/admin')) ?>?tab=create">Create new service</a>
    <a class="btn <?= $tab === 'manage' ? 'btn-primary' : 'btn-muted' ?>" href="<?= e(url('/admin')) ?>?tab=manage">Manage services</a>
    <a class="btn <?= $tab === 'reviews' ? 'btn-primary' : 'btn-muted' ?>" href="<?= e(url('/admin')) ?>?tab=reviews">Review moderate</a>
  </div>
</section>

<?php if ($tab === 'create'): ?>
<section class="card section-block">
  <h2><?= $editService ? 'Edit service #' . (int)$editService['id'] : 'Create new service' ?></h2>
  <div id="admin-errors" class="meta"></div>
  <form id="service-form" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="service_id" value="<?= (int)($editService['id'] ?? 0) ?>">

    <label>Service type</label>
    <select name="service_type">
      <?php $stype = $editService['service_type'] ?? 'browser'; ?>
      <option value="browser" <?= $stype === 'browser' ? 'selected' : '' ?>>Fully client side browser based services</option>
      <option value="windows" <?= $stype === 'windows' ? 'selected' : '' ?>>Windows executables</option>
      <option value="extension" <?= $stype === 'extension' ? 'selected' : '' ?>>Chromium extensions</option>
    </select>

    <label>Service Title</label>
    <input name="title" minlength="20" maxlength="80" required value="<?= e($editService['title'] ?? '') ?>">

    <label>Service url slug</label>
    <input name="slug" pattern="[a-z0-9\-]+" minlength="10" maxlength="120" required value="<?= e($editService['slug'] ?? '') ?>">

    <label>Feature image (jpg/png/webp)</label>
    <input name="feature_image" type="file" accept="image/jpeg,image/png,image/webp">

    <label>SEO/Short description</label>
    <textarea name="seo_description" minlength="110" maxlength="160" rows="3" required><?= e($editService['seo_description'] ?? '') ?></textarea>

    <label>Long description</label>
    <textarea name="long_description" minlength="200" maxlength="1000" rows="6" required><?= e($editService['long_description'] ?? '') ?></textarea>

    <div id="features-wrap">
      <label>Tool Features</label>
      <?php $featuresInitial = $editFeatures ?: ['']; foreach ($featuresInitial as $idx => $f): ?>
        <?php if ($idx === 0): ?>
          <input name="features[]" minlength="5" maxlength="160" required value="<?= e($f) ?>">
        <?php else: ?>
          <div class="inline-item"><button type="button" class="x" onclick="this.parentNode.remove()">×</button><input name="features[]" maxlength="160" value="<?= e($f) ?>"></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="btn btn-muted" type="button" id="add-feature">Add new feature</button></div>

    <div id="instructions-wrap">
      <label>How to use instructions</label>
      <?php $insInitial = $editInstructions ?: ['']; foreach ($insInitial as $idx => $i): ?>
        <?php if ($idx === 0): ?>
          <input name="instructions[]" minlength="5" maxlength="255" value="<?= e($i) ?>">
        <?php else: ?>
          <div class="inline-item"><button type="button" class="x" onclick="this.parentNode.remove()">×</button><input name="instructions[]" maxlength="255" value="<?= e($i) ?>"></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="btn btn-muted" type="button" id="add-ins">Add new instruction</button></div>

    <div id="download-row" style="display:none">
      <label>Executable download URL</label>
      <input name="download_url" type="url" value="<?= e($editService['download_url'] ?? '') ?>">
    </div>

    <div id="extension-row" style="display:none">
      <label>Chrome store extension link</label>
      <input name="extension_url" type="url" value="<?= e($editService['extension_url'] ?? '') ?>">
    </div>

    <div id="html-row">
      <label>Raw HTML</label>
      <textarea name="tool_html" rows="5"><?= e($editService['tool_html'] ?? '') ?></textarea>
    </div>

    <label>Service Demo Tutorial</label>
    <input name="demo_tutorial_url" type="url" placeholder="https://www.youtube.com/watch?v=..." value="<?= e($editService['demo_tutorial_url'] ?? '') ?>">

    <div class="form-actions"><button id="service-submit" class="btn btn-primary" type="submit">Save service</button></div>
  </form>
</section>
<?php endif; ?>

<?php if ($tab === 'manage'): ?>
<section class="card section-block">
  <h2>Manage services</h2>
  <ul class="list-clean tight">
    <?php foreach ($servicesAdminList as $svc): ?>
      <li><strong><?= e($svc['title']) ?></strong> <span class="meta">(<?= e($svc['service_type']) ?>)</span> · <a href="<?= e(url('/admin')) ?>?tab=create&edit=<?= (int)$svc['id'] ?>">Edit</a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($tab === 'reviews'): ?>
<section class="card section-block">
  <h2>Review moderation</h2>
  <input type="hidden" id="admin-review-csrf" value="<?= e(csrf_token()) ?>">
  <div id="review-admin-msg" class="meta"></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>User</th><th>Rating</th><th>Review</th><th>Status</th><th>Favorite</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($reviewsAdminList as $r): ?>
        <tr>
          <td><?= e($r['name']) ?><br><span class="meta"><?= e($r['email']) ?></span></td>
          <td><?= (int)$r['rating'] ?>/5</td>
          <td><?= e($r['review_text']) ?></td>
          <td><?= e($r['status']) ?></td>
          <td><?= (int)$r['is_favorite'] ? 'Yes' : 'No' ?></td>
          <td>
            <button class="btn btn-muted review-action" data-id="<?= (int)$r['id'] ?>" data-action="approve">Approve</button>
            <button class="btn btn-muted review-action" data-id="<?= (int)$r['id'] ?>" data-action="reject">Reject</button>
            <button class="btn btn-muted review-action" data-id="<?= (int)$r['id'] ?>" data-action="favorite">Toggle Favorite</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
