<?php
// === Logging Setup ===
error_reporting(0);
ini_set('display_errors', '0');

// Enable error logging
ini_set('log_errors', '1');

// === Log file path & rotation ===
$logFile       = __DIR__ . '/ccc3ad8da957c98c1a510862f5c03c19c7d1cf2faf65abb386e0848f004ee108.log';
$maxFileSize   = 5 * 1024 * 1024; // 5MB
$backupLogDir  = __DIR__ . '/ccc3ad8da957c98c1a510862f5c03c19c7d1cf2faf65abb386e0848f004ee108_log_backups';

// Create backup directory if it doesn't exist
if (!is_dir($backupLogDir)) {
    mkdir($backupLogDir, 0777, true);
}

// Rotate log if it's too large
if (file_exists($logFile) && filesize($logFile) > $maxFileSize) {
    $timestamp   = time();
    $backupLog   = "$backupLogDir/premium_error_$timestamp.log";

    $handle = fopen($logFile, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        rename($logFile, $backupLog);
        fclose($handle);
    }

    file_put_contents($logFile, ""); // Clear log
}

// Tell PHP to use our log file
ini_set('error_log', $logFile);

// === Error & Exception Handlers ===

// Warnings, Notices, etc.
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $message = "[" . date('Y-m-d H:i:s') . "] ERROR [$errno]: $errstr in $errfile on line $errline\n";
    error_log($message);
    return false; // Let PHP's native handler run too
});

// Uncaught Exceptions
set_exception_handler(function ($e) {
    $message = "[" . date('Y-m-d H:i:s') . "] UNCAUGHT EXCEPTION: {$e->getMessage()} in {$e->getFile()} on line {$e->getLine()}\n";
    error_log($message);
});

// Fatal errors on shutdown
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $message = "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}\n";
        error_log($message);
    }
});

// === Cache-Control Headers ===
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");

// === PayPal Setup ===
$PAYPAL_ENV     = 'live';

$WEBHOOK_ID     =  ($PAYPAL_ENV === 'live') 
                ? '37Y64950D71078121'
                : '6AN90139W6338880K';
                
$PLAN_ID        = ($PAYPAL_ENV === 'live') 
                ? 'P-8GL98556M3929433GNAI4SPQ'
                : 'P-00650988UK259205HM7HK4DY';
                
$CLIENT_ID      = ($PAYPAL_ENV === 'live') 
                ? 'AdmiERfejL9fyuwVdZsBr2kO2xhdjjM1BAlMysC1CI-xwQlYbAjhbd9NPm0Jvrt7TfGXEqTwobr2SRnh'
                : 'AaRlZXyyaqIVEwqEUEauoUkdhqlDXqeJZdY2Trb8lmwUq4GTk91uA-SshowufsQTe05jaWqgouSVmLLK';
                
$CLIENT_SECRET  = ($PAYPAL_ENV === 'live') 
                ? 'EC4pNEpvZHKlbbyambyTL_5n70JLONe9tmogVGOJrl0yCH6A9cnatUiLSVexjtIiGGWrkClzCzdczMDs'
                : 'ENaCcrV7YvjOF7-Xm5oWeq4ZOr4kJ2vXZzIjSPUE6BY6z3oxXNnkCce2bhmnS2REeJwWDgcGW4NViENZ';

$API_BASE       = ($PAYPAL_ENV === 'live') 
                ? 'https://api-m.paypal.com' 
                : 'https://api-m.sandbox.paypal.com';

// === App Secret Key ===
$secretKey = 'growthworlds-tools-tahseem';

// === Helper Functions ===

function sanitizeInput($data, $conn) {
    return htmlspecialchars(mysqli_real_escape_string($conn, trim($data)));
}

function returnRandom5xxError() {
    $errors = [500, 501, 502, 503, 504];
    http_response_code($errors[array_rand($errors)]);
    echo 'Service Unavailable';
    exit();
}

function return404() {
    http_response_code(404);
    echo 'Page not found!';
    exit();
}

function returnResp($status, $message) {
	header('Content-Type: application/json');
	echo json_encode([
		'status'  => $status,
		'message' => $message
	]);
	exit();
}

function generateSessionId($uniqueUnicode) {
	$randomString = uniqid(mt_rand(), true) . $uniqueUnicode;
	return hash('sha256', $randomString);
}

function logout($conn, $email) {
	$session_id   = generateSessionId($email);
	$refreshed_at = time() - (60 * 60 * 10); // Set to 10 hours ago to invalidate

	$stmt = $conn->prepare("
		INSERT INTO user_sessions (email, session_id, refreshed_at) 
		VALUES (?, ?, ?)
		ON DUPLICATE KEY UPDATE 
			session_id   = VALUES(session_id),
			refreshed_at = VALUES(refreshed_at)
	");
	$stmt->bind_param('ssi', $email, $session_id, $refreshed_at);
	$stmt->execute();
	$conn->commit();

	return [$email, $session_id];
}

function refreshSessionId($conn, $email = "", $session_id = "") {
	// Case 1: Refresh based on session ID
	if (!$email && $session_id) {
		$stmt = $conn->prepare("SELECT email, refreshed_at FROM user_sessions WHERE session_id = ?");
		$stmt->bind_param('s', $session_id);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows === 0) {
			return ["", ""];
		}

		$row            = $result->fetch_assoc();
		$refreshed_at   = $row["refreshed_at"];
		$current_time   = time();
		$elapsed_seconds = abs($current_time - $refreshed_at);

		if ($elapsed_seconds > 3 * 60 * 60) { // more than 3 hours
			return ["", ""];
		}

		$email        = $row["email"];
		$refreshed_at = time();

		$stmt = $conn->prepare("UPDATE user_sessions SET refreshed_at = ? WHERE email = ?");
		$stmt->bind_param('is', $refreshed_at, $email);
		$stmt->execute();
		$conn->commit();

		return [$email, $session_id];
	}

	// Case 2: Refresh based on email (new session ID)
	if ($email) {
		$session_id   = generateSessionId($email);
		$refreshed_at = time();

		$stmt = $conn->prepare("
			INSERT INTO user_sessions (email, session_id, refreshed_at) 
			VALUES (?, ?, ?)
			ON DUPLICATE KEY UPDATE 
				session_id   = VALUES(session_id),
				refreshed_at = VALUES(refreshed_at)
		");
		$stmt->bind_param('ssi', $email, $session_id, $refreshed_at);
		$stmt->execute();
		$conn->commit();

		return [$email, $session_id];
	}

	return ["", ""];
}

function isValidString($string) {
    // Allow "0" but disallow empty, null, or whitespace-only strings
    if (!isset($string) || trim($string) === '') {
        return false;
    }

    // Disallow tabs, newlines, or carriage returns
    if (preg_match('/[\t\r\n]/', $string)) {
        return false;
    }

    // Disallow leading or trailing whitespace
    if (preg_match('/^\s|\s$/', $string)) {
        return false;
    }

    return true;
}

function redirectAlreadyLoggedIn($conn) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $session_id = $_COOKIE["growthworld-tools-premium-session"] ?? '';
        if ($session_id) {
            list($email, $refreshed_session_id) = refreshSessionId($conn, $email = "", $session_id);
            if ($refreshed_session_id) {
                setcookie("growthworld-tools-premium-session", $refreshed_session_id, time() + 86400, "/growthworld-premium-tools");
                header("Refresh: 5; URL=/growthworld-premium-tools/dashboard");
                echo "You will be redirected to the dashboard in 5 seconds...";
                exit();
            }
        }
    }
}

function isOlderThan30Days($dateString) {
    $dateString = trim($dateString);

    // Try both date formats
    $givenDate = DateTime::createFromFormat('Y-m-d H:i', $dateString, new DateTimeZone('UTC'))
        ?: DateTime::createFromFormat('Y-m-d', $dateString, new DateTimeZone('UTC'));

    if (!$givenDate) {
        return false; // Invalid format
    }

    $thirtyDaysAgo = (new DateTime('now', new DateTimeZone('UTC')))->modify('-30 days');
    return $givenDate < $thirtyDaysAgo;
}

function removeCustomBlocks($input) {
    return preg_replace('/\{\{custom_block_.*?\}\}/', '', $input);
}

function hasProcessedEvent($conn, $eventId) {
    $stmt = $conn->prepare("SELECT 1 FROM webhook_events WHERE event_id = ? AND processed_at IS NOT NULL LIMIT 1");
    $stmt->bind_param("s", $eventId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function insertProcessedEvent($conn, $eventId) {
    $processedAt = time();
    $stmt = $conn->prepare("UPDATE webhook_events SET processed_at = ? WHERE event_id = ?");
    $stmt->bind_param("is", $processedAt, $eventId);
    $stmt->execute();
    $stmt->close();
}

function logWebhookEvent($conn, $eventId, $eventType, $rawJson) {
    $createdAt = time();
    $stmt = $conn->prepare("
        INSERT INTO webhook_events (event_id, event_type, event_json, created_at) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE event_json = VALUES(event_json)
    ");
    $stmt->bind_param("sssi", $eventId, $eventType, $rawJson, $createdAt);
    $stmt->execute();
    $stmt->close();
}

function getSubscriptionByCustomId($conn, $customId) {
    $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("s", $customId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

function upsertSubscription($conn, $customId, $paypalSubId, $status, $eventType, $event_time) {
    $current_time = time();

    $stmt = $conn->prepare("
        INSERT INTO subscriptions (
            user_id, paypal_subscription_id, status, last_event_time, 
            created_at, updated_at, last_event, last_checked
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            paypal_subscription_id = VALUES(paypal_subscription_id),
            last_event_time = VALUES(last_event_time),
            status = VALUES(status),
            updated_at = VALUES(updated_at),
            last_event = VALUES(last_event)
    ");

    $stmt->bind_param(
        "ssssiisi", 
        $customId, $paypalSubId, $status, $event_time,
        $current_time, $current_time, $eventType, $current_time
    );

    $stmt->execute();
    $stmt->close();
}

function updateSubscriptionActivation($conn, $customId, $activatedAt, $last_checked) {
    $stmt = $conn->prepare("
        UPDATE subscriptions 
        SET activated_at = ?, last_checked = ? 
        WHERE user_id = ?
    ");
    $stmt->bind_param("iis", $activatedAt, $last_checked, $customId);
    $stmt->execute();
    $stmt->close();
}

function getPayPalSubscriptionDetails($subscriptionId, $token, $API_BASE) {
    $url = "{$API_BASE}/v1/billing/subscriptions/{$subscriptionId}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer {$token}"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("[SUBSCRIPTION DETAILS] cURL error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("[SUBSCRIPTION DETAILS] HTTP code {$httpCode}. Response: {$response}");
        return null;
    }

    return json_decode($response, true);
}

function verifyPayPalSignature($rawBody, $headers, $token, $API_BASE, $WEBHOOK_ID) {
    $transmissionId   = $headers['PAYPAL-TRANSMISSION-ID']  ?? '';
    $transmissionSig  = $headers['PAYPAL-TRANSMISSION-SIG'] ?? '';
    $transmissionTime = $headers['PAYPAL-TRANSMISSION-TIME'] ?? '';
    $certUrl          = $headers['PAYPAL-CERT-URL']         ?? '';
    $authAlgo         = $headers['PAYPAL-AUTH-ALGO']        ?? '';

    if (!$transmissionId || !$transmissionSig || !$transmissionTime || !$certUrl || !$authAlgo) {
        error_log("[VERIFY SIGNATURE] Missing PayPal verification headers.");
        return false;
    }

    $verifyPayload = [
        'auth_algo'         => $authAlgo,
        'cert_url'          => $certUrl,
        'transmission_id'   => $transmissionId,
        'transmission_sig'  => $transmissionSig,
        'transmission_time' => $transmissionTime,
        'webhook_id'        => $WEBHOOK_ID,
        'webhook_event'     => json_decode($rawBody, true),
    ];

    $ch = curl_init("{$API_BASE}/v1/notifications/verify-webhook-signature");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "Authorization: Bearer {$token}"
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($verifyPayload),
        CURLOPT_RETURNTRANSFER => true,
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("[VERIFY SIGNATURE] cURL error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("[VERIFY SIGNATURE] HTTP code {$httpCode}. Response: {$response}");
        return false;
    }

    $json = json_decode($response, true);
    if (isset($json['verification_status']) &&
        in_array($json['verification_status'], ['SUCCESS', 'SUCCESSFUL', 'VALID', 'VERIFIED'])) {
        return true;
    }

    error_log("[VERIFY SIGNATURE] Verification failed. Response: {$response}");
    return false;
}

function getPayPalToken($conn, $PAYPAL_ENV, $CLIENT_ID, $CLIENT_SECRET, $API_BASE) {
    $columns = [
        'sandbox' => ['access_token' => 'sandbox_access_token', 'expires_at' => 'sandbox_expires_at'],
        'live'    => ['access_token' => 'live_access_token',    'expires_at' => 'live_expires_at']
    ];

    if (!isset($columns[$PAYPAL_ENV])) {
        throw new InvalidArgumentException("Invalid PayPal environment: $PAYPAL_ENV");
    }

    $colAccessToken = $columns[$PAYPAL_ENV]['access_token'];
    $colExpiresAt   = $columns[$PAYPAL_ENV]['expires_at'];

    // Step 1: Try existing token
    $stmt = $conn->prepare("SELECT $colAccessToken, $colExpiresAt FROM paypal_tokens WHERE id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $tokenData = $result->fetch_assoc();

    if ($tokenData && isset($tokenData[$colAccessToken], $tokenData[$colExpiresAt]) && time() < $tokenData[$colExpiresAt]) {
        return $tokenData[$colAccessToken]; // ✅ Valid cached token
    }

    // Step 2: Fetch new token
    $newTokenData = (function () use ($CLIENT_ID, $CLIENT_SECRET, $API_BASE) {
        $ch = curl_init("{$API_BASE}/v1/oauth2/token");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Accept-Language: en_US'
            ],
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => "{$CLIENT_ID}:{$CLIENT_SECRET}",
            CURLOPT_POSTFIELDS     => "grant_type=client_credentials",
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log("[OAUTH] cURL error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[OAUTH] HTTP code {$httpCode}. Response: {$result}");
            return null;
        }

        $json = json_decode($result, true);
        return [
            'access_token' => $json['access_token'] ?? null,
            'expires_in'   => (int)($json['expires_in'] ?? 0)
        ];
    })();

    if (empty($newTokenData['access_token']) || empty($newTokenData['expires_in'])) {
        return null; // ❌ Failed to get new token
    }

    // Step 3: Store new token
    $expiresAt = time() + $newTokenData['expires_in'];

    $stmt = $conn->prepare("
        INSERT INTO paypal_tokens (id, $colAccessToken, $colExpiresAt) 
        VALUES (1, ?, ?)
        ON DUPLICATE KEY UPDATE 
            $colAccessToken = VALUES($colAccessToken),
            $colExpiresAt   = VALUES($colExpiresAt)
    ");
    $stmt->bind_param("si", $newTokenData['access_token'], $expiresAt);
    $stmt->execute();

    return $newTokenData['access_token'];
}

function isSubscriptionActiveLive($customId, $token, $conn, $API_BASE) {
    $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE user_id = ?");
    $stmt->bind_param('s', $customId);
    $stmt->execute();
    $result = $stmt->get_result();

    $activated_at = 0;

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $subscriptionId = $row["paypal_subscription_id"];
        $activated_at   = $row["activated_at"];
        $last_checked   = $row["last_checked"];

        // Refresh only if not checked in last 28 days
        if ($last_checked < (time() - (28 * 24 * 60 * 60))) {
            $subscriptionInfo = getPayPalSubscriptionDetails($subscriptionId, $token, $API_BASE);

            if (!$subscriptionInfo) {
                http_response_code(500);
                exit;
            }

            $last_payment_date_str = $subscriptionInfo["billing_info"]["last_payment"]["time"] ?? null;
            if ($last_payment_date_str) {
                $last_payment_date = strtotime($last_payment_date_str);
                updateSubscriptionActivation($conn, $customId, $last_payment_date, time());
                $activated_at = $last_payment_date;
            }
        }
    }

    return $activated_at;
}

function partialRefundWithMargin($subscriptionId, $token, $API_BASE, $marginPercent = 10) {
    // Step 1: Get subscription details
    $subUrl = "{$API_BASE}/v1/billing/subscriptions/{$subscriptionId}";
    $ch = curl_init($subUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $subResponse = curl_exec($ch);
    curl_close($ch);

    $subData = json_decode($subResponse, true);
    $paymentTimeStr = $subData['billing_info']['last_payment']['time'] ?? null;

    if (!$paymentTimeStr) {
        error_log("[REFUND] Last payment time not found for subscription $subscriptionId.");
        return false;
    }

    // Step 2: Get transaction window
    $paymentTime = strtotime($paymentTimeStr);
    $startTime   = date('Y-m-d\TH:i:s\Z', $paymentTime - 7200);
    $endTime     = date('Y-m-d\TH:i:s\Z', $paymentTime + 7200);

    $txnUrl = "{$API_BASE}/v1/billing/subscriptions/{$subscriptionId}/transactions?start_time={$startTime}&end_time={$endTime}";
    $ch = curl_init($txnUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $txnResponse = curl_exec($ch);
    curl_close($ch);

    $txnData = json_decode($txnResponse, true);
    $transactions = $txnData['transactions'] ?? [];

    if (empty($transactions)) {
        error_log("[REFUND] No transactions found in time window for $subscriptionId.");
        return false;
    }

    // Step 3: Find capture ID of completed payment
    $paymentTxn = null;
    foreach ($transactions as $txn) {
        if (($txn['status'] ?? '') === 'COMPLETED' && ($txn['type'] ?? '') === 'PAYMENT') {
            $paymentTxn = $txn;
            break;
        }
    }

    if (!$paymentTxn) {
        error_log("[REFUND] No completed payment found to refund.");
        return false;
    }

    $captureId = $paymentTxn['id'];
    $amount    = floatval($paymentTxn['amount_with_breakdown']['gross_amount']['value']);
    $currency  = $paymentTxn['amount_with_breakdown']['gross_amount']['currency_code'];

    // Step 4: Calculate refund
    $margin       = $amount * ($marginPercent / 100);
    $refundAmount = round($amount - $margin, 2);

    // Step 5: Process refund
    $refundUrl = "{$API_BASE}/v2/payments/captures/{$captureId}/refund";
    $ch = curl_init($refundUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => json_encode([
            "amount" => [
                "value"         => number_format($refundAmount, 2, '.', ''),
                "currency_code" => $currency
            ],
            "note_to_payer" => "Refund for duplicate subscription, some processing fees deducted."
        ])
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        error_log("[REFUND] Successfully refunded {$refundAmount} {$currency} from capture ID: {$captureId}");
        return true;
    }

    error_log("[REFUND] Refund failed with status {$httpCode}. Response: {$response}");
    return false;
}

function isWebhookActive() {
    $url = "https://growthworld.net/growthworld-premium-tools/75faa360ab80371bb93ec84d88b536883e07317ae1ca4cd85ea0b890d130369e.php?checkWebhook=true";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $data = json_decode($response, true);
    return isset($data['webhook_status']) && $data['webhook_status'] === "OK";
}

function redirectIfAlreadyLoggedIn($conn) {
    $session_id = $_COOKIE["growthworld-tools-premium-session"] ?? '';
    if ($session_id) {
        list($email, $refreshed_session_id) = refreshSessionId($conn, session_id: $session_id);
        if ($refreshed_session_id) {
            setcookie("growthworld-tools-premium-session", $refreshed_session_id, time() + 86400, "/growthworld-premium-tools");
            header("Refresh: 1; URL=/growthworld-premium-tools/dashboard");
            echo "Redirecting to dashboard in 1 second...";
            exit();
        }
    }
}

function redirectToOther($other_url) {
    header("Location: /growthworld-premium-tools/" . ltrim($other_url, '/'));
    exit();
}

function returnHTML($final_html) {
    header("Content-Type: text/html; charset=UTF-8");
    echo removeCustomBlocks($final_html);
    exit();
}

function methodNotAllowed() {
    http_response_code(405);
    echo "Method not allowed!";
    exit();
}

function getUrlPath() {
    $url = $_SERVER['REQUEST_URI'];
    $parsedUrl = parse_url($url);
    $path = strtolower($parsedUrl['path'] ?? '/');

    $path = preg_replace('/^' . preg_quote('/growthworld-premium-tools', '/') . '/', '', $path);
    return rtrim($path, '/');
}

function getPostJson() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function verifyDataLogin($email, $password) {
    if (!(isValidString($email) && isValidString($password))) {
        return false;
    }

    if ($email !== strtoupper($email)) {
        return false;
    }

    if (
        strlen($email) > 100 ||
        strlen($password) > 20 ||
        strlen($password) < 8
    ) {
        return false;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    return true;
}

function verifyDataSignup($email, $password, $confirm) {
    if (!(isValidString($email) && isValidString($password) && isValidString($confirm))) {
        return false;
    }

    if ($email !== strtoupper($email)) {
        return false;
    }

    if (
        strlen($email) > 100 ||
        strlen($password) > 20 || strlen($password) < 8 ||
        strlen($confirm)  > 20 || strlen($confirm)  < 8
    ) {
        return false;
    }

    if ($password !== $confirm) {
        return false;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    return true;
}

function verifyDataReset($password, $confirm) {
    if (!(isValidString($password) && isValidString($confirm))) {
        return false;
    }

    if (
        strlen($password) > 20 || strlen($password) < 8 ||
        strlen($confirm)  > 20 || strlen($confirm)  < 8
    ) {
        return false;
    }

    if ($password !== $confirm) {
        return false;
    }

    return true;
}

function verifyDataToken($emailhash, $token) {
    if (
        !$token ||
        !$emailhash ||
        strlen($token) !== 97 ||
        strlen($emailhash) !== 64 ||
        substr_count($token, '.') !== 1 ||
        $token[0] === '.' || $token[strlen($token) - 1] === '.'
    ) {
        return false;
    }

    return true;
}

function verifyTokenIntegrity($token, $secretKey) {
    $parts = explode('.', $token);

    if (count($parts) !== 2) {
        return false;
    }

    list($randomToken, $providedHash) = $parts;
    $expectedHash = hash_hmac('sha256', $randomToken, $secretKey);

    if (!hash_equals($expectedHash, $providedHash)) {
        return false;
    }

    return $randomToken;
}

function generateVerificationToken($secretKey) {
    $randomToken = bin2hex(random_bytes(16)); // 32-character hex token
    $hash = hash_hmac('sha256', $randomToken, $secretKey);
    return [$randomToken, $hash];
}

function verify_reset($inputToken, $emailhash, $conn, $password) {
    $current_time = time();

    // Step 1: Validate token from forget table
    $stmt = $conn->prepare("SELECT created_at, email FROM forget WHERE token = ? AND emailhash = ?");
    $stmt->bind_param("ss", $inputToken, $emailhash);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows < 1) {
        return false;
    }

    $row = $result->fetch_assoc();
    $created_at = $row['created_at'];
    $email = $row['email'];

    // Step 2: Expiry check (15 minutes max)
    if (($current_time - $created_at) > (15 * 60)) {
        return false;
    }

    // Step 3: If only verifying token without changing password
    if (!$password) {
        return true;
    }

    try {
        // Step 4: Remove token entry
        $stmt = $conn->prepare("DELETE FROM forget WHERE token = ?");
        $stmt->bind_param("s", $inputToken);
        $stmt->execute();
        $conn->commit();

        // Step 5: Hash and update password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        $stmt->execute();
        $conn->commit();

        return true;

    } catch (Exception $e) {
        error_log("[verify_reset] Exception: " . $e->getMessage());
        return false;
    }
}


function verify_token($inputToken, $emailhash, $conn) {
    $current_time = time();

    // Step 1: Fetch from temp_users
    $stmt = $conn->prepare("SELECT created_at, email, password FROM temp_users WHERE token = ? AND emailhash = ?");
    $stmt->bind_param("ss", $inputToken, $emailhash);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows < 1) {
        return false;
    }

    $row = $result->fetch_assoc();
    $created_at = $row['created_at'];
    $email = $row['email'];
    $password = $row['password'];

    // Step 2: Expiry check (5 minutes max)
    if (($current_time - $created_at) > (5 * 60)) {
        return false;
    }

    try {
        // Step 3: Delete from temp_users
        $stmt = $conn->prepare("DELETE FROM temp_users WHERE token = ?");
        $stmt->bind_param("s", $inputToken);
        $stmt->execute();
        $conn->commit();

        // Step 4: Insert into users
        $created_at = time();
        $stmt = $conn->prepare("INSERT INTO users (email, password, created_at) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $email, $password, $created_at);
        $stmt->execute();
        $conn->commit();

        // Optional: double check insert
        $stmt = $conn->prepare("SELECT created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        return ($result->num_rows > 0);

    } catch (Exception $e) {
        error_log("[verify_token] Exception: " . $e->getMessage());
        return false;
    }
}


?>