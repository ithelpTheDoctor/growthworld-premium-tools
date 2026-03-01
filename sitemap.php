<?php
require __DIR__ . '/core/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$base = rtrim(cfg('app.base_url'), '/');
$rows = $pdo->query('SELECT slug, updated_at FROM ' . table_name('services') . ' WHERE is_active = 1')->fetchAll();
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach (['', '/services', '/privacy-policy', '/terms-of-service', '/contact-us'] as $p) {
  echo '<url><loc>' . $base . $p . '</loc></url>';
}
foreach ($rows as $r) {
  echo '<url><loc>' . $base . '/service/' . e($r['slug']) . '</loc><lastmod>' . gmdate('c', (int)$r['updated_at']) . '</lastmod></url>';
}
echo '</urlset>';
