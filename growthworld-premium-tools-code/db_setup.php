<?php
// Database configuration
$DB_HOST       = 'localhost';
$DB_NAME       = 'u335546481_PremiumTools';
$DB_USER       = 'u335546481_premiumtools';
$DB_PASSWORD   = 'Q@:xt32Ub[';  // If you have a password, put it here

// Establish initial database connection
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASSWORD, $DB_NAME);

if ($conn->connect_error) {
    // Optional: random 5xx error for masking actual DB failure
    $errors = [500, 501, 502, 503, 504];
    http_response_code($errors[array_rand($errors)]);
    echo 'Service Unavailable';
    exit();
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS `$DB_NAME`";
if (!$conn->query($sql)) {
    $errors = [500, 501, 502, 503, 504];
    http_response_code($errors[array_rand($errors)]);
    echo 'Service Unavailable';
    exit();
}

// Select the database
$conn->select_db($DB_NAME);

// Create necessary tables
$createUsersTable = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    subscription_attempt BIGINT DEFAULT NULL,
    created_at BIGINT NOT NULL
)";

$createTempUsersTable = "CREATE TABLE IF NOT EXISTS temp_users (
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    emailhash VARCHAR(300) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at BIGINT NOT NULL
)";

$createSessionsTable = "CREATE TABLE IF NOT EXISTS user_sessions (
    email VARCHAR(255) NOT NULL UNIQUE,
    session_id VARCHAR(255) NOT NULL,
    refreshed_at BIGINT NOT NULL
)";

$createSubscriptionTable = "CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL UNIQUE,
    paypal_subscription_id VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    last_event_time VARCHAR(255) NOT NULL,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    last_event VARCHAR(255) DEFAULT NULL,
    activated_at BIGINT NOT NULL,
    last_checked BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$createWebhookEventsTable = "CREATE TABLE IF NOT EXISTS webhook_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL UNIQUE,
    event_type VARCHAR(255) NOT NULL,
    event_json LONGTEXT NOT NULL,
    created_at BIGINT NOT NULL,
    processed_at BIGINT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$createPaypalTokensTable = "CREATE TABLE IF NOT EXISTS paypal_tokens (
    id INT DEFAULT 1,
    live_access_token TEXT NOT NULL,
    live_expires_at BIGINT NOT NULL,
    sandbox_access_token TEXT NOT NULL,
    sandbox_expires_at BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$createPaypalCancelTable = "CREATE TABLE IF NOT EXISTS pending_cancels (
    sub_id VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$createPaypalPendingTable = "CREATE TABLE IF NOT EXISTS pending_refunds (
    sub_id VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$createFrogetTable = "CREATE TABLE IF NOT EXISTS forget (
    email VARCHAR(255) NOT NULL UNIQUE,
    token VARCHAR(255) NOT NULL,
	emailhash VARCHAR(300) NOT NULL,
    created_at BIGINT NOT NULL
)";

$createAppTokenTable = "CREATE TABLE IF NOT EXISTS app_token (
    email VARCHAR(255) NOT NULL UNIQUE,
    token VARCHAR(255) NOT NULL
)";


if (
    !$conn->query($createUsersTable) ||
    !$conn->query($createTempUsersTable) ||
    !$conn->query($createSessionsTable) ||
    !$conn->query($createSubscriptionTable) ||
    !$conn->query($createPaypalTokensTable) ||
    !$conn->query($createWebhookEventsTable) ||
    !$conn->query($createPaypalCancelTable) ||
    !$conn->query($createPaypalPendingTable) ||
	!$conn->query($createFrogetTable) ||
	!$conn->query($createAppTokenTable)
) {
    $errors = [500, 501, 502, 503, 504];
    http_response_code($errors[array_rand($errors)]);
    echo 'Service Unavailable';
    exit();
}

// Cleanup expired temp users (older than 5 minutes)
function cleanupExpiredTempUsers($conn) {
    $expiryTime = time() - (5 * 60); // 5 minutes ago

    // 1. Fetch only expired users
    $sqlFetch = "SELECT id FROM temp_users WHERE created_at < $expiryTime";
    $result = $conn->query($sqlFetch);

    if ($result && $result->num_rows > 0) {
        $idsToDelete = [];
        while ($row = $result->fetch_assoc()) {
            $idsToDelete[] = (int) $row['id']; // sanitize as integer
        }

        if (!empty($idsToDelete)) {
            $ids = implode(',', $idsToDelete);
            $sqlDelete = "DELETE FROM temp_users WHERE id IN ($ids)";
            if ($conn->query($sqlDelete)) {
                return $conn->affected_rows; // ✅ return how many were deleted
            }
        }
    }

    return 0; // nothing deleted or failed
}


function cleanupExpiredForget($conn) {
    $expiryTime = time() - (15 * 60); // 5 minutes ago

    // 1. Fetch only expired users
    $sqlFetch = "SELECT id FROM forget WHERE created_at < $expiryTime";
    $result = $conn->query($sqlFetch);

    if ($result && $result->num_rows > 0) {
        $idsToDelete = [];
        while ($row = $result->fetch_assoc()) {
            $idsToDelete[] = (int) $row['id']; // sanitize as integer
        }

        if (!empty($idsToDelete)) {
            $ids = implode(',', $idsToDelete);
            $sqlDelete = "DELETE FROM forget WHERE id IN ($ids)";
            if ($conn->query($sqlDelete)) {
                return $conn->affected_rows; // ✅ return how many were deleted
            }
        }
    }

    return 0; // nothing deleted or failed
}



?>
