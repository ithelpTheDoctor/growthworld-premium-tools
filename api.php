<?php
require __DIR__ . '/core/bootstrap.php';
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';
if ($action === 'tool-login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $stmt = $pdo->prepare('SELECT id,name,password_hash FROM ' . table_name('users') . ' WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['ok' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    echo json_encode(['ok' => true, 'user' => ['id' => $user['id'], 'name' => $user['name']]]);
    exit;
}
echo json_encode(['ok' => false, 'message' => 'Unsupported endpoint']);
