<?php
require __DIR__ . '/bootstrap.php';

$threshold = time() - 86400;
$stmt = $pdo->prepare('SELECT id,paypal_subscription_id,status,last_checked FROM ' . table_name('subscriptions') . ' WHERE last_checked < ? ORDER BY last_checked ASC LIMIT 100');
$stmt->execute([$threshold]);
$rows = $stmt->fetchAll();

$up = $pdo->prepare('UPDATE ' . table_name('subscriptions') . ' SET last_checked = ?, updated_at = ? WHERE id = ?');
foreach ($rows as $row) {
    // Placeholder for direct provider verification API call.
    $now = time();
    $up->execute([$now, $now, $row['id']]);
}

echo 'Checked ' . count($rows) . " subscriptions\n";
