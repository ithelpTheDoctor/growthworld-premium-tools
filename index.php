<?php
require __DIR__ . '/core/bootstrap.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = app_base_path();
$pathOnly = $uri ?? '/';
if ($basePath && str_starts_with($pathOnly, $basePath)) {
    $pathOnly = substr($pathOnly, strlen($basePath));
}
$path = trim($pathOnly, '/');
$page = $path ?: 'home';

apply_cors_headers();

$loggedIn = is_logged_in();
$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
$isSubscribed = $loggedIn && $currentUserId > 0 ? user_has_active_subscription($currentUserId) : false;

if ($page === 'api/service/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    header('Content-Type: application/json');
    if (!check_csrf($_POST['csrf'] ?? '')) {
        echo json_encode(['ok' => false, 'errors' => ['Invalid CSRF token.']]);
        exit;
    }

    $decoded = json_decode(xor_decrypt($_POST['payload'] ?? '', cfg('app.xor_key')), true);
    $errors = [];
    $serviceId = (int)($decoded['service_id'] ?? 0);
    $title = trim($decoded['title'] ?? '');
    $slug = trim($decoded['slug'] ?? '');
    $seo = trim($decoded['seo_description'] ?? '');
    $long = trim($decoded['long_description'] ?? '');
    $type = $decoded['service_type'] ?? '';
    $features = $decoded['features'] ?? [];
    $instructions = $decoded['instructions'] ?? [];
    $demoTutorialUrl = trim($decoded['demo_tutorial_url'] ?? '');

    if (!in_array($type, ['browser', 'windows', 'extension'], true)) $errors[] = 'Invalid service type.';
    if (strlen($title) < 20 || strlen($title) > 80) $errors[] = 'Service Title must be 20-80 chars.';
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) < 10 || strlen($slug) > 120) $errors[] = 'Slug is invalid.';
    if (strlen($seo) < 110 || strlen($seo) > 160) $errors[] = 'SEO description must be 110-160 chars.';
    if (strlen($long) < 200 || strlen($long) > 1000) $errors[] = 'Long description must be 200-1000 chars.';
    if (count($features) < 1) $errors[] = 'At least one feature is required.';
    if ($type === 'browser' && empty($decoded['tool_html'])) $errors[] = 'Raw HTML is required for browser service.';
    if ($type === 'windows' && !filter_var($decoded['download_url'] ?? '', FILTER_VALIDATE_URL)) $errors[] = 'Valid executable URL required.';
    if ($type === 'extension' && !filter_var($decoded['extension_url'] ?? '', FILTER_VALIDATE_URL)) $errors[] = 'Valid extension URL required.';
    if ($demoTutorialUrl && !filter_var($demoTutorialUrl, FILTER_VALIDATE_URL)) $errors[] = 'Service Demo Tutorial must be a valid URL.';

    $dup = $pdo->prepare('SELECT id FROM ' . table_name('services') . ' WHERE (title = ? OR slug = ?) AND id <> ? LIMIT 1');
    $dup->execute([$title, $slug, $serviceId]);
    if ($dup->fetch()) $errors[] = 'Duplicate title or slug found.';

    if ($errors) {
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }

    $now = time();
    $imgPath = 'static/images/' . $slug . '.webp';

    if ($serviceId > 0) {
        $stmt = $pdo->prepare('UPDATE ' . table_name('services') . ' SET service_type=?,title=?,slug=?,feature_image=?,seo_description=?,long_description=?,tool_html=?,download_url=?,extension_url=?,demo_tutorial_url=?,updated_at=? WHERE id=?');
        $stmt->execute([$type, $title, $slug, $imgPath, $seo, $long, $decoded['tool_html'] ?? null, $decoded['download_url'] ?? null, $decoded['extension_url'] ?? null, $demoTutorialUrl ?: null, $now, $serviceId]);

        $pdo->prepare('DELETE FROM ' . table_name('service_features') . ' WHERE service_id = ?')->execute([$serviceId]);
        $pdo->prepare('DELETE FROM ' . table_name('service_instructions') . ' WHERE service_id = ?')->execute([$serviceId]);
        $sid = $serviceId;
    } else {
        $stmt = $pdo->prepare('INSERT INTO ' . table_name('services') . ' (service_type,title,slug,feature_image,seo_description,long_description,tool_html,download_url,extension_url,demo_tutorial_url,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$type, $title, $slug, $imgPath, $seo, $long, $decoded['tool_html'] ?? null, $decoded['download_url'] ?? null, $decoded['extension_url'] ?? null, $demoTutorialUrl ?: null, $now, $now]);
        $sid = (int)$pdo->lastInsertId();
    }

    $fStmt = $pdo->prepare('INSERT INTO ' . table_name('service_features') . ' (service_id,feature_text,sort_order) VALUES (?,?,?)');
    foreach ($features as $i => $feature) $fStmt->execute([$sid, substr(trim($feature), 0, 160), $i]);

    $iStmt = $pdo->prepare('INSERT INTO ' . table_name('service_instructions') . ' (service_id,instruction_text,sort_order) VALUES (?,?,?)');
    foreach ($instructions as $i => $instruction) $iStmt->execute([$sid, substr(trim($instruction), 0, 255), $i]);

    echo json_encode(['ok' => true]);
    exit;
}

if ($page === 'api/review/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$loggedIn || !$isSubscribed) {
        echo json_encode(['ok' => false, 'errors' => ['Only logged-in subscribed users can submit feedback.']]);
        exit;
    }
    if (!check_csrf($_POST['csrf'] ?? '')) {
        echo json_encode(['ok' => false, 'errors' => ['Invalid CSRF token.']]);
        exit;
    }

    $rating = (int)($_POST['rating'] ?? 0);
    $review = trim($_POST['review_text'] ?? '');
    $errors = [];
    if ($rating < 1 || $rating > 5) $errors[] = 'Rating must be between 1 and 5.';
    if (strlen($review) < 20 || strlen($review) > 800) $errors[] = 'Review must be between 20 and 800 characters.';

    if ($errors) {
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }

    $sql = 'INSERT INTO ' . table_name('reviews') . ' (user_id,rating,review_text,status,is_favorite,created_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating), review_text=VALUES(review_text), status="pending", is_favorite=0, created_at=VALUES(created_at), approved_at=NULL';
    $pdo->prepare($sql)->execute([$currentUserId, $rating, $review, 'pending', 0, time()]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($page === 'api/review/moderate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    header('Content-Type: application/json');
    if (!check_csrf($_POST['csrf'] ?? '')) {
        echo json_encode(['ok' => false, 'errors' => ['Invalid CSRF token.']]);
        exit;
    }

    $reviewId = (int)($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($reviewId < 1 || !in_array($action, ['approve','reject','favorite'], true)) {
        echo json_encode(['ok' => false, 'errors' => ['Invalid moderation request.']]);
        exit;
    }

    if ($action === 'approve') {
        $pdo->prepare('UPDATE ' . table_name('reviews') . ' SET status="approved", approved_at=? WHERE id=?')->execute([time(), $reviewId]);
    } elseif ($action === 'reject') {
        $pdo->prepare('UPDATE ' . table_name('reviews') . ' SET status="rejected", is_favorite=0 WHERE id=?')->execute([$reviewId]);
    } else {
        $pdo->prepare('UPDATE ' . table_name('reviews') . ' SET is_favorite = CASE WHEN is_favorite=1 THEN 0 ELSE 1 END WHERE id=?')->execute([$reviewId]);
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($page === 'admin/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf($_POST['csrf'] ?? '')) {
        $_SESSION['flash'] = 'Invalid CSRF token.';
        header('Location: ' . url('/admin/login'));
        exit;
    }
    if (($_POST['username'] ?? '') === cfg('admin.username') && ($_POST['password'] ?? '') === cfg('admin.password')) {
        $_SESSION['admin'] = true;
        header('Location: ' . url('/admin'));
        exit;
    }
    $_SESSION['flash'] = 'Invalid admin credentials';
    header('Location: ' . url('/admin/login'));
    exit;
}

switch ($page) {
    case 'home':
        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . table_name('services') . ' WHERE is_active = 1')->fetchColumn();
        $featured = $pdo->query('SELECT title,seo_description,slug FROM ' . table_name('services') . ' WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 6')->fetchAll();

        $testimonialSql = 'SELECT r.id,r.rating,r.review_text,r.is_favorite,u.name FROM ' . table_name('reviews') . ' r JOIN ' . table_name('users') . ' u ON u.id = r.user_id WHERE r.status = "approved" ORDER BY r.is_favorite DESC, r.approved_at DESC LIMIT 40';
        $testimonials = $pdo->query($testimonialSql)->fetchAll();

        if (!$testimonials) {
            $testimonials = [
                ['name' => 'Ava M.', 'rating' => 5, 'review_text' => 'Helped me automate daily SEO checks in minutes. Stable, clean, and worth the subscription.', 'is_favorite' => 1],
                ['name' => 'Daniel P.', 'rating' => 5, 'review_text' => 'I use three tools every week. The workflows are straightforward and save me hours.', 'is_favorite' => 0],
                ['name' => 'Samir K.', 'rating' => 4, 'review_text' => 'Great support and practical tools. Looking forward to more extension tutorials.', 'is_favorite' => 0],
                ['name' => 'Jenna C.', 'rating' => 5, 'review_text' => 'The browser tools are fast and the instruction flow is beginner-friendly.', 'is_favorite' => 0],
                ['name' => 'Marcus T.', 'rating' => 5, 'review_text' => 'The subscription paid for itself in one week of productivity gains.', 'is_favorite' => 1],
            ];
        } else {
            $favorites = array_values(array_filter($testimonials, fn($r) => (int)$r['is_favorite'] === 1));
            $nonFav = array_values(array_filter($testimonials, fn($r) => (int)$r['is_favorite'] !== 1));
            shuffle($nonFav);
            $testimonials = array_slice(array_merge($favorites, $nonFav), 0, 10);
        }

        render('home', ['title' => 'GrowthWorld Premium Tools', 'serviceCount' => $count, 'featured' => $featured, 'testimonials' => $testimonials, 'loggedIn' => $loggedIn, 'isSubscribed' => $isSubscribed]);
        break;

    case 'feedback':
        if (!$loggedIn || !$isSubscribed) {
            http_response_code(403);
            echo 'Feedback is available for subscribed members only.';
            exit;
        }
        $myReview = $pdo->prepare('SELECT rating,review_text,status FROM ' . table_name('reviews') . ' WHERE user_id = ? LIMIT 1');
        $myReview->execute([$currentUserId]);
        render('feedback', ['title' => 'Member Feedback', 'myReview' => $myReview->fetch() ?: null]);
        break;

    case 'services':
        $pageNum = max(1, (int)($_GET['p'] ?? 1));
        $offset = ($pageNum - 1) * 25;
        $stmt = $pdo->prepare('SELECT title,seo_description,slug FROM ' . table_name('services') . ' WHERE is_active = 1 ORDER BY id DESC LIMIT 25 OFFSET ' . $offset);
        $stmt->execute();
        render('services', ['title' => 'Browse Services', 'services' => $stmt->fetchAll(), 'pageNum' => $pageNum]);
        break;

    case (preg_match('#^service/([a-z0-9\-]+)$#', $page, $m) ? true : false):
        $slug = $m[1];
        $stmt = $pdo->prepare('SELECT * FROM ' . table_name('services') . ' WHERE slug = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$slug]);
        $service = $stmt->fetch();
        if (!$service) { http_response_code(404); echo 'Not found'; exit; }
        $f = $pdo->prepare('SELECT feature_text FROM ' . table_name('service_features') . ' WHERE service_id = ? ORDER BY sort_order ASC');
        $f->execute([$service['id']]);
        $ins = $pdo->prepare('SELECT instruction_text FROM ' . table_name('service_instructions') . ' WHERE service_id = ? ORDER BY sort_order ASC');
        $ins->execute([$service['id']]);
        render('service', ['title' => $service['title'], 'service' => $service, 'features' => $f->fetchAll(), 'instructions' => $ins->fetchAll()]);
        break;

    case 'admin/login':
        render('admin_login', ['title' => 'Admin Login']);
        break;

    case 'admin':
        require_admin();
        $editService = null;
        if (!empty($_GET['edit'])) {
            $s = $pdo->prepare('SELECT * FROM ' . table_name('services') . ' WHERE id = ? LIMIT 1');
            $s->execute([(int)$_GET['edit']]);
            $editService = $s->fetch() ?: null;
        }
        $allServices = $pdo->query('SELECT id,title,slug,service_type,updated_at FROM ' . table_name('services') . ' ORDER BY updated_at DESC LIMIT 100')->fetchAll();
        $pendingReviews = $pdo->query('SELECT r.id,r.rating,r.review_text,r.status,r.is_favorite,r.created_at,u.name,u.email FROM ' . table_name('reviews') . ' r JOIN ' . table_name('users') . ' u ON u.id=r.user_id ORDER BY r.created_at DESC LIMIT 100')->fetchAll();

        $editFeatures = [];
        $editInstructions = [];
        if ($editService) {
            $ef = $pdo->prepare('SELECT feature_text FROM ' . table_name('service_features') . ' WHERE service_id = ? ORDER BY sort_order ASC');
            $ef->execute([(int)$editService['id']]);
            $editFeatures = array_column($ef->fetchAll(), 'feature_text');
            $ei = $pdo->prepare('SELECT instruction_text FROM ' . table_name('service_instructions') . ' WHERE service_id = ? ORDER BY sort_order ASC');
            $ei->execute([(int)$editService['id']]);
            $editInstructions = array_column($ei->fetchAll(), 'instruction_text');
        }

        render('admin', ['title' => 'Admin Portal', 'servicesAdminList' => $allServices, 'reviewsAdminList' => $pendingReviews, 'editService' => $editService, 'editFeatures' => $editFeatures, 'editInstructions' => $editInstructions]);
        break;

    case 'privacy-policy':
        render('privacy', ['title' => 'Privacy Policy']);
        break;
    case 'terms-of-service':
        render('terms', ['title' => 'Terms of Service']);
        break;
    case 'contact-us':
        render('contact', ['title' => 'Contact Us']);
        break;
    default:
        http_response_code(404);
        echo 'Not found';
}
