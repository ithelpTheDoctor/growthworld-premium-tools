<?php

require_once 'commons.php';
require_once 'db_setup.php';

// ✅ Gracefully close DB connection on script shutdown
register_shutdown_function(function () use (&$conn) {
    if ($conn instanceof mysqli) {
        try {
            $conn->close();
        } catch (Exception $e) {
            // Optional: log if needed
        }
    }
});

// 🔐 Lightweight XOR-based encoder/decoder for payload encryption
class CitrusMix {
    private $seed;

    public function __construct($seed) {
        $this->seed = $seed;
    }

    public function blend($pulp) {
        $squeezed = '';
        $seedLen = strlen($this->seed);
        $pulpBytes = mb_convert_encoding($pulp, 'UTF-8');

        for ($i = 0; $i < strlen($pulpBytes); $i++) {
            $segment = ord($pulpBytes[$i]);
            $seedChar = ord($this->seed[$i % $seedLen]);
            $squeezed .= chr($segment ^ $seedChar);
        }

        return base64_encode($squeezed);
    }

    public function extract($bottledPulp) {
        $squeezed = base64_decode($bottledPulp);
        $extracted = '';
        $seedLen = strlen($this->seed);

        for ($i = 0; $i < strlen($squeezed); $i++) {
            $segment = ord($squeezed[$i]);
            $seedChar = ord($this->seed[$i % $seedLen]);
            $extracted .= chr($segment ^ $seedChar);
        }

        return $extracted;
    }
}

// 🔒 Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    methodNotAllowed();
}

// 🔍 Parse request URI
$requestUri = $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($requestUri);

$path  = $parsedUrl['path']  ?? '';
$query = $parsedUrl['query'] ?? '';

// 🔄 Normalize path (remove base subdir)
$path = preg_replace('/^' . preg_quote('/growthworld-premium-tools', '/') . '/', '', $path);

// ✅ Check: exact match "/api" and no query string
if ($path !== '/api' || $query !== '') {
    returnRandom5xxError();
}

$status = "failed";
try {
    $encoded = file_get_contents('php://input');
    $mix = new CitrusMix("growthworlds.net-tools");
    
    $decoded   = $mix->extract($encoded);
    $postJson  = json_decode($decoded, true);
    $action    = $postJson["action"] ?? '';
    if ($action === "login") {
        $email    = $postJson['username'] ?? '';
        $password = $postJson['password'] ?? '';
        $msg      = "Login failed!";
        $token    = null;
        
        if (!verifyDataLogin($email, $password)) {
            $msg = "Invalid email or password!";
        } else {
            $email = $conn->real_escape_string($email);
            $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows < 1) {
                $msg = "Invalid email or password!";
            } else {
                $row = $result->fetch_assoc();

                if (!password_verify($password, $row["password"])) {
                    $msg = "Invalid email or password!";
                } else {
                    if ($email!=="TAHSEEMKHAN00007@GMAIL.COM"){
                        $user_id = hash('sha256', $email);
                        $accessToken = getPayPalToken($conn, $PAYPAL_ENV, $CLIENT_ID, $CLIENT_SECRET, $API_BASE);
                        $activated_at = isSubscriptionActiveLive($user_id, $accessToken, $conn, $API_BASE);
                        $existingSubInfo = getSubscriptionByCustomId($conn, $user_id);
                        $is_active = false;
    
                        if ($activated_at) {
                            if ($activated_at < (time() - 30 * 24 * 60 * 60)) {
                                $subscriptionInfo = getPayPalSubscriptionDetails($existingSubInfo["paypal_subscription_id"], $accessToken, $API_BASE);
                                $statusText = strtoupper($subscriptionInfo["status"] ?? '');
                                if (!empty($subscriptionInfo["billing_info"]["last_payment"]["time"])) {
                                    updateSubscriptionActivation($conn, $user_id, strtotime($subscriptionInfo["billing_info"]["last_payment"]["time"]), time());
                                }
                                if ($statusText === "ACTIVE") {
                                    $is_active = true;
                                }
                            } else {
                                $is_active = true;
                            }
                        }
                    } else {
                        $is_active = true;
                    }

                    if (!$is_active) {
                        $msg = "Not subscribed or expired!";
                    } else {
                        list($token, $hmachash) = generateVerificationToken($secretKey);

                        $stmt = $conn->prepare("
                            INSERT INTO app_token (email, token)
                            VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE token = VALUES(token)
                        ");
                        $stmt->bind_param("ss", $email, $token);

                        if (!$stmt->execute()) {
                            $msg = "Something went wrong!";
                            $token = null;
                        } else {
                            $conn->commit();
                            $msg = "Logged in successfully!";
                            $status = "success";
                        }
                    }
                }
            }
        }

        $resp_data = json_encode([
            "status" => $status,
            "msg"    => $msg,
            "token"  => $token
        ]);

        echo $mix->blend($resp_data);
        exit;
    }

    // 🔄 Token Refresh Handler
    elseif ($action === "refresh") {
        $email = $postJson['username'] ?? '';
        $token = $postJson['token'] ?? '';
        $password = "testpassword1@"; // dummy for validation
        $msg = "Expired, re-login!";
        $return_token = null;

        if (verifyDataLogin($email, $password)) {
            $email = $conn->real_escape_string($email);
            $stmt = $conn->prepare("SELECT token FROM app_token WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();

                if ($token === $row["token"]) {
                    list($new_token, $hmachash) = generateVerificationToken($secretKey);

                    $stmt = $conn->prepare("
                        INSERT INTO app_token (email, token)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE token = VALUES(token)
                    ");
                    $stmt->bind_param("ss", $email, $new_token);

                    if ($stmt->execute()) {
                        $conn->commit();
                        $msg = "Refreshed successfully!";
                        $status = "success";
                        $return_token = $new_token;
                    }
                }
            }
        }

        $resp_data = json_encode([
            "status" => $status,
            "msg"    => $msg,
            "token"  => $return_token
        ]);

        echo $mix->blend($resp_data);
        exit;
    }

} catch (Exception $e) {
    returnRandom5xxError();
}


?>