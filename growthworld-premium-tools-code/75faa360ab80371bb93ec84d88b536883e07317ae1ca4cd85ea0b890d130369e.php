<?php
require_once 'commons.php'; 
require_once 'db_setup.php';


register_shutdown_function(function () use (&$conn) {
    if ($conn instanceof mysqli) {
        try {
            $conn->close();
        } catch (Exception $e) {
            
        }
    }
});


$lockFilePath = __DIR__ . '/75faa360ab80371bb93ec84d88b536883e07317ae1ca4cd85ea0b890d130369e.lock';
$lockFile = fopen($lockFilePath, 'c'); // Open or create the lock file

if (!$lockFile) {
    http_response_code(500);
    exit("❌ Failed to open lock file.");
}

if (!flock($lockFile, LOCK_EX)) {
    http_response_code(500);
    fclose($lockFile);
    exit("❌ Failed to acquire lock.");
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['checkWebhook'])) {
    $dbOk = false;
    if ($result = $conn->query("SELECT 1 FROM subscriptions LIMIT 1")) {
        $dbOk = true;
        $result->free();
    } else {
        error_log("Health check DB error: " . $conn->error);
    }

    $status = $dbOk ? 'OK' : 'DB_ERROR';
    echo json_encode([
        'webhook_status' => $status,
        'timestamp' => date('c')
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$rawBody = file_get_contents('php://input');

if (!$rawBody) {
    http_response_code(400);
    exit;
}



if (flock($lockFile, LOCK_EX)) {
    try {
        

		$payload = json_decode($rawBody, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			http_response_code(400);
			exit;
		}

		if (!isset($payload['id'], $payload['event_type'], $payload['resource'])) {
			http_response_code(400);
			exit;
		}

		$eventId   = $payload['id'];
		$eventType = $payload['event_type'];
		$eventTime = $payload["create_time"];
	
		if ($eventId) {
			logWebhookEvent($conn, $eventId, $eventType, $rawBody);
		}

		$resource  = $payload['resource'];
		$customId = $resource['custom_id'] ?? null;
		$paypalSubId = $resource['id'] ?? null;
        $plan_id = $resource["plan_id"] ?? null;
		if (!$customId) {
			error_log("Missing custom_id in event: $eventId");
			http_response_code(400);
			exit;
		}

		if (!$paypalSubId) {
			error_log("Missing paypal_subscription_id in event: $eventId");
			http_response_code(400);
			exit;
		}
		
		if (!$eventTime) {
			error_log("Missing create_time in event: $eventId");
			http_response_code(400);
			exit;
		}
		
		if (!$plan_id) {
			error_log("Missing plan_id in event: $eventId");
			http_response_code(400);
			exit;
		}
		
		if ($plan_id!=$PLAN_ID){
			error_log("Mismatch plan_id in event: $eventId");
			http_response_code(400);
			exit;
		}
		
		$accessToken = getPayPalToken($conn, $PAYPAL_ENV, $CLIENT_ID, $CLIENT_SECRET, $API_BASE);
		if (!$accessToken) {
			error_log("[ERROR] Could not retrieve PayPal access token.");
			http_response_code(500);
			exit;
		}

		$headers = [];
		foreach (getallheaders() as $name => $value) {
			$headers[strtoupper($name)] = $value;
		}

		$isVerified = verifyPayPalSignature(
			$rawBody,
			$headers,
			$accessToken,
			$API_BASE,
			$WEBHOOK_ID
		);

		if (!$isVerified) {
			http_response_code(400);
			exit;
		}
		
		if (hasProcessedEvent($conn, $eventId)) {
			http_response_code(200);
			exit;
		}

		$subscriptionInfo = getPayPalSubscriptionDetails($paypalSubId, $accessToken, $API_BASE);

		if (!$subscriptionInfo) {
			error_log("Failed to retrieve subscription details from PayPal for event $eventId");
			http_response_code(500);
			exit;
		}

		$subscriptionStatus = strtoupper($subscriptionInfo['status']);  

		$existing_sub = getSubscriptionByCustomId($conn, $customId);
		if ($existing_sub){
			if ($existing_sub["paypal_subscription_id"]!=$subscriptionInfo["id"]){
				if ($subscriptionStatus=="ACTIVE") {
					$existing_subscriptionInfo = getPayPalSubscriptionDetails($existing_sub["paypal_subscription_id"], $accessToken, $API_BASE);
					$refund = false;
					if (strtoupper($existing_subscriptionInfo["status"])!="ACTIVE") {
						$activated_at = isSubscriptionActiveLive($customId, $accessToken, $conn, $API_BASE);
						if ($activated_at > (time()-(30 * 24 * 60 * 60))){
							$refund=true;
						}
					} else {
						$refund=true;
					}
					if ($refund){
						partialRefundWithMargin($subscriptionInfo["id"], $accessToken, $API_BASE);
					}	
					
				} else {
					insertProcessedEvent($conn, $eventId);
					http_response_code(200);
					echo "OK";
					exit;
				}
			} else {
				$last_event_dt = new DateTimeImmutable($existing_sub["last_event_time"], new DateTimeZone("UTC"));
				$eventTime_dt = new DateTimeImmutable($eventTime, new DateTimeZone("UTC"));
				if ($eventTime_dt < $last_event_dt) {
					insertProcessedEvent($conn, $eventId);
					http_response_code(200);
					echo "OK";
					exit;
				}
			}
		}

		upsertSubscription($conn, $customId, $paypalSubId, $subscriptionStatus, $eventType, $eventTime);
		$last_payment_date = $subscriptionInfo["billing_info"]["last_payment"]["time"] ?? null;

		if ($last_payment_date){
			updateSubscriptionActivation($conn, $customId, strtotime($last_payment_date), time());
		}
		insertProcessedEvent($conn, $eventId);
		http_response_code(200);
		echo "OK";

    } finally {
        flock($lockFile, LOCK_UN);
        fclose($lockFile);
    }
} else {
    http_response_code(500);
    exit;
}

?>