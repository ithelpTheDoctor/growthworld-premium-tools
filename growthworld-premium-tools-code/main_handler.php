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


$paragraph = '
<p style="text-align: center; width: 100%; display: block; margin: 1rem auto; font-weight: 500; {{custom_block_para_color}}">
{{custom_block_para_text}}
</p>'
;


if (!in_array($_SERVER['REQUEST_METHOD'],  ["GET","POST"])) {
    methodNotAllowed();
}

$url_path = preg_replace('/[^A-Za-z0-9\-_\/]/', '', strtolower(getUrlPath()));

$session_id = $_COOKIE["growthworld-tools-premium-session"] ?? '';
$refreshed_session_id = "";
$email = "";
$user_id = "";

if ($session_id) {
    list($email, $refreshed_session_id) = refreshSessionId($conn, session_id: $session_id);
}

$base_html = file_get_contents("base.html");
$subscription_text = $credits_text = "";

if ($refreshed_session_id) {
    $accessToken = getPayPalToken($conn, $PAYPAL_ENV, $CLIENT_ID, $CLIENT_SECRET, $API_BASE);
    if (!$accessToken) {
        error_log("[ERROR] Could not retrieve PayPal access token.");
        http_response_code(500);
        exit;
    }

    $user_id = hash('sha256', $email);
    if ($email!=="TAHSEEMKHAN00007@GMAIL.COM"){
        $activated_at = isSubscriptionActiveLive($user_id, $accessToken, $conn, $API_BASE);
        $existingSubInfo = getSubscriptionByCustomId($conn, $user_id);
        $subscription_text = "Not subscribed";
        $credits_text = "0 Credits";
    
        if ($activated_at) {
            $expire_at = date("Y-m-d", $activated_at + (30 * 24 * 60 * 60));
    
            if ($activated_at < (time() - (30 * 24 * 60 * 60))) {
                $subscriptionInfo = getPayPalSubscriptionDetails($existingSubInfo["paypal_subscription_id"], $accessToken, $API_BASE);
                $status = strtoupper($subscriptionInfo["status"] ?? '');
    
                $subscription_text = $expire_at . ($status === "ACTIVE" ? " (Active)" : " (Expired)");
    
                if (!empty($subscriptionInfo["billing_info"]["last_payment"]["time"])) {
                    updateSubscriptionActivation($conn, $user_id, strtotime($subscriptionInfo["billing_info"]["last_payment"]["time"]), time());
                }
            } else {
                $subscription_text = $expire_at . " (Active)";
            }
        }
    } else {
        $subscription_text = "Never Expire (Active)";
    }

    // Update cookie
    setcookie("growthworld-tools-premium-session", $refreshed_session_id, time() + 86400, "/growthworld-premium-tools");

    // Inject credit UI block
    $base_html = str_replace(
        "{{custom_block_credit_link}}",
        '<div><span>Remaining Credits:</span></br><input type="text" id="subscription" name="subscription" placeholder="' . $credits_text . '" disabled></br></br><a href="/growthworld-premium-tools/purchase-credits" class="btn-x12yz">Purchase Credits</a></div></br>',
        $base_html
    );

    // Inject subscription block with conditional subscribe button
    $subscription_html = '<div><span>Subscription Expires:</span></br><input type="text" id="subscription" name="subscription" placeholder="' . $subscription_text . '" disabled> </br></br>';
    if (str_contains($subscription_text, "Expired") || str_contains($subscription_text, "Not subscribed")) {
        $subscription_html .= '<a href="/growthworld-premium-tools/subscribe" class="btn-x12yz">Subscribe Now</a>';
    }
    $subscription_html .= '</div></br>';

    $base_html = str_replace("{{custom_block_subscription_link}}", $subscription_html, $base_html);

    // Replace other UI elements
    $base_html = str_replace([
        '<div id="shareSidebar" style="display:block;">',
        'Checkout Subscription Tools',
        'Checkout Credit Tools',
        '{{custom_block_logout}}'
    ], [
        '<div id="shareSidebar" style="display:none;">',
        'Subscription Tools',
        'Credit Tools',
        '<a href="logout" class="nav-item">Logout</a>'
    ], $base_html);
}


if ($url_path === "/logout") {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($email) {
            logout($conn, $email);
            redirectToOther("");
        }
    } else {
        methodNotAllowed();
    }
}

elseif ($url_path === "/login") {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        try {
            if ($refreshed_session_id) {
                redirectToOther('dashboard');
            }

            $base_html = str_replace("{{custom_block_sideheading}}", 'Login', $base_html);
            $base_html = str_replace("{{custom_block_page}}", file_get_contents("custom_blocks/login.html"), $base_html);
            $base_html = str_replace("{{custom_block_seo_meta}}", file_get_contents("custom_blocks/login_meta.html"), $base_html);
            $base_html = str_replace("{{custom_block_accessnow}}", '<a href="/growthworld-premium-tools/" class="btn-x12yz">Home</a></br></br>', $base_html);

            returnHTML($base_html);
        } catch (Exception $e) {
            returnRandom5xxError();
        }
    }

    elseif ($method === 'POST') {
        try {
            $login_data = getPostJson();
            $email    = $login_data['email'] ?? '';
            $password = $login_data['password'] ?? '';

            if (!verifyDataLogin($email, $password)) {
                returnResp("failed", "Invalid email or password!");
            }

            $email = $conn->real_escape_string($email);
            $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows < 1) {
                returnResp("failed", "Invalid email or password!");
            }

            $row = $result->fetch_assoc();
            if (!password_verify($password, $row["password"])) {
                returnResp("failed", "Invalid email or password!");
            }

            list($email, $refreshed_session_id) = refreshSessionId($conn, email: $email);

            if ($refreshed_session_id) {
                setcookie("growthworld-tools-premium-session", $refreshed_session_id, time() + 86400, "/growthworld-premium-tools");
                returnResp("success", "Logged in successfully!");
            }

            returnResp("failed", "Login failed, please try again!");
        } catch (Exception $e) {
            returnResp("failed", "Internal Server Error");
        }
    }

    else {
        methodNotAllowed();
    }
}
elseif ($url_path === "/signup") {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if ($refreshed_session_id) {
            redirectToOther('dashboard');
        } else {
            redirectToOther("login");
        }
    }

    elseif ($method === 'POST') {
        try {
            $signup_data = getPostJson();
            $email           = $signup_data['email'] ?? '';
            $password        = $signup_data['password'] ?? '';
            $confirmPassword = $signup_data['confirm_password'] ?? '';

            if (!verifyDataSignup($email, $password, $confirmPassword)) {
                returnResp("failed", "Invalid email or password!");
            }

            $email = $conn->real_escape_string($email);

            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                returnResp("failed", "User already exists, try login!");
            }

            list($verificationToken, $hmachash) = generateVerificationToken($secretKey);
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $hashedemail    = hash('sha256', $email);
            $created_at     = time();

            cleanupExpiredTempUsers($conn);

            $stmt = $conn->prepare("
                INSERT INTO temp_users (email, password, emailhash, token, created_at) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    password = VALUES(password), 
                    token = VALUES(token), 
                    created_at = VALUES(created_at)
            ");
            $stmt->bind_param("ssssi", $email, $hashedPassword, $hashedemail, $verificationToken, $created_at);

            if (!$stmt->execute()) {
                returnResp("failed", "Internal Server Error");
            }

            $conn->commit();

            $verificationLink = "https://growthworld.net/growthworld-premium-tools/verify-email?token=$verificationToken.$hmachash&id=$hashedemail";

            require_once 'phpmailer/src/PHPMailer.php';
            require_once 'phpmailer/src/SMTP.php';
            require_once 'phpmailer/src/Exception.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'no-reply@growthworld.net';
            $mail->Password   = 'xT?8=v@nz^';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('no-reply@growthworld.net', 'GrowthWorld Productivity Tools HUB');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Email Address';
            $mail->Body    = "Click the following link to verify your account:</br><a href='$verificationLink'>$verificationLink</a>";

            $mail->send();
            returnResp("success", "Verification email sent. Please check your inbox or spam.");
        } catch (Exception $e) {
            returnResp("failed", "Internal Server Error");
        }
    }

    else {
        methodNotAllowed();
    }
}
elseif ($url_path === "/verify-email") {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            $token     = $_GET['token'] ?? '';
            $emailhash = $_GET['id'] ?? '';
            $msg       = '<span style="color:red;">The verification link has expired or is invalid. Please try signing up again!</span>';

            if (verifyDataToken($emailhash, $token)) {
                $verificationToken = verifyTokenIntegrity($token, $secretKey);

                if ($verificationToken) {
                    if (verify_token($verificationToken, $emailhash, $conn)) {
                        $msg = '<span style="color:green;">Email verified successfully. Now you can log in!</span>';
                    }
                }
            }

            $base_html = str_replace("{{custom_block_sideheading}}", 'Login', $base_html);
            $base_html = str_replace("{{custom_block_credit_link}}", '<div><a href="/growthworld-premium-tools/" class="btn-x12yz">Premium Tools</a></div></br>', $base_html);
            $base_html = str_replace("{{custom_block_page}}", file_get_contents("custom_blocks/login.html"), $base_html);
            $base_html = str_replace("{{custom_block_seo_meta}}", file_get_contents("custom_blocks/verify_meta.html"), $base_html);
            $base_html = str_replace("{{custom_block_verify}}", $msg, $base_html);

            returnHTML($base_html);
        } catch (Exception $e) {
            returnRandom5xxError();
        }
    } else {
        methodNotAllowed();
    }
}
elseif ($url_path === "/forget-password") {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $login_data = getPostJson();
            $email = $login_data['email'] ?? '';

            if (!$email) {
                returnResp("failed", "Invalid email!");
            }

            $email = $conn->real_escape_string($email);
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows < 1) {
                returnResp("failed", "Invalid email!");
            }

            list($verificationToken, $hmachash) = generateVerificationToken($secretKey);
            $hashedemail = hash('sha256', $email);
            $created_at = time();

            cleanupExpiredForget($conn);

            $stmt = $conn->prepare("
                INSERT INTO forget (email, token, emailhash, created_at) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    token = VALUES(token), 
                    created_at = VALUES(created_at)
            ");
            $stmt->bind_param("sssi", $email, $verificationToken, $hashedemail, $created_at);

            if (!$stmt->execute()) {
                returnResp("failed", "Internal Server Error");
            }

            $conn->commit();

            $verificationLink = "https://growthworld.net/growthworld-premium-tools/reset-password?token={$verificationToken}.{$hmachash}&id={$hashedemail}";

            require_once 'phpmailer/src/PHPMailer.php';
            require_once 'phpmailer/src/SMTP.php';
            require_once 'phpmailer/src/Exception.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.hostinger.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'no-reply@growthworld.net';
            $mail->Password   = 'xT?8=v@nz^';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('no-reply@growthworld.net', 'GrowthWorld Productivity Tools HUB');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';
            $mail->Body    = "Click the following link to reset your account password:</br><a href='{$verificationLink}'>{$verificationLink}</a>";

            $mail->send();
            returnResp("success", "Reset link sent. Please check your inbox or spam!");
        } catch (Exception $e) {
            returnRandom5xxError();
        }
    } else {
        methodNotAllowed();
    }
}
elseif ($url_path === "/reset-password") {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        try {
            $token     = $_GET['token'] ?? '';
            $emailhash = $_GET['id'] ?? '';
            $token_ok  = false;

            if (verifyDataToken($emailhash, $token)) {
                $verificationToken = verifyTokenIntegrity($token, $secretKey);
                if ($verificationToken && verify_reset($verificationToken, $emailhash, $conn, "")) {
                    $token_ok = true;
                }
            }

            $base_html = str_replace("{{custom_block_sideheading}}", 'Reset Password', $base_html);
            $base_html = str_replace("{{custom_block_seo_meta}}", file_get_contents("custom_blocks/reset_password_meta.html"), $base_html);
            $base_html = str_replace("{{custom_block_credit_link}}", '<div><a href="/growthworld-premium-tools/" class="btn-x12yz">Premium Tools</a></div></br>', $base_html);

            if ($token_ok) {
                $block_html = file_get_contents("custom_blocks/reset_password.html");
                $base_html = str_replace("{{custom_block_page}}", $block_html, $base_html);
                $base_html = str_replace("{{custom_block_token_reset}}", $token, $base_html);
                $base_html = str_replace("{{custom_block_token_id}}", $emailhash, $base_html);
            } else {
                $base_html = str_replace("{{custom_block_page}}", $paragraph, $base_html);
                $base_html = str_replace("{{custom_block_para_text}}", "Reset link expired or invalid, please try again!</br><div style='text-align:center'><a style='text-decoration:none' href='/growthworld-premium-tools/login'>Back to Login!</a></div>", $base_html);
                $base_html = str_replace("{{custom_block_para_color}}", "color: red;", $base_html);
            }

            returnHTML($base_html);
        } catch (Exception $e) {
            returnRandom5xxError();
        }
    }

    elseif ($method === 'POST') {
        $login_data = getPostJson();
        $password        = $login_data['password'] ?? '';
        $confirmPassword = $login_data['confirmPassword'] ?? '';
        $token           = $login_data['token'] ?? '';
        $emailhash       = $login_data['tokenId'] ?? '';

        if (!(verifyDataReset($password, $confirmPassword) && $emailhash && $token)) {
            returnResp("failed", "Invalid password!");
        }

        if (verifyDataToken($emailhash, $token)) {
            $verificationToken = verifyTokenIntegrity($token, $secretKey);
            if ($verificationToken && verify_reset($verificationToken, $emailhash, $conn, $password)) {
                returnResp("success", "Password reset successfully!");
            }
        }

        returnResp("failed", "Reset link expired!");
    }

    else {
        methodNotAllowed();
    }
}
elseif (in_array($url_path, [
    "", "/dashboard", "/subscription-tools", "/credit-tools",
    "/about-us", "/contact", "/privacy-policy", "/terms-of-service"
])) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            // Handle access control for dashboard
            if (!$refreshed_session_id && $url_path === "/dashboard") {
                redirectToOther('login');
            }

            // Access now block for unauthenticated users
            if (!$refreshed_session_id) {
                $base_html = str_replace("{{custom_block_accessnow}}", '<a href="/growthworld-premium-tools/login" class="btn-x12yz">Get Access Now</a></br></br>', $base_html);
            } elseif ($url_path !== "" && $url_path !== "/dashboard") {
                $base_html = str_replace("{{custom_block_accessnow}}", '<a href="/growthworld-premium-tools/" class="btn-x12yz">Home</a></br></br>', $base_html);
            }
            if ($refreshed_session_id && $url_path===""){
                redirectToOther('dashboard');
            }
            // Landing or Dashboard
            if ($url_path === "" || $url_path === "/dashboard") {
                $block_html = file_get_contents("custom_blocks/dashboard.html");
                $meta_file  = $refreshed_session_id ? "dashboard_meta.html" : "landing_meta.html";
                $sideheading = $refreshed_session_id ? "Dashboard" : "Growth Premium Tools";

                $base_html = str_replace("{{custom_block_page}}", $block_html, $base_html);
                $base_html = str_replace("{{custom_block_seo_meta}}", file_get_contents("custom_blocks/{$meta_file}"), $base_html);
                $base_html = str_replace("{{custom_block_sideheading}}", $sideheading, $base_html);
            }

            // Tool Pages
            elseif (in_array($url_path, ["/subscription-tools", "/credit-tools"])) {
                $tool_type = $url_path === "/subscription-tools" ? "subscription" : "credit";
                $sideheading = $tool_type === "subscription" ? "Subscription Tools" : "Credit Tools";
                $meta_file = $tool_type . "_meta.html";

                $base_html = str_replace("{{custom_block_credit_link}}", '<div><a href="/growthworld-premium-tools/dashboard" class="btn-x12yz">Dashboard</a></div></br>', $base_html);
                $base_html = str_replace("{{custom_block_sideheading}}", $sideheading, $base_html);
                $base_html = str_replace("{{custom_block_seo_meta}}", file_get_contents("custom_blocks/{$meta_file}"), $base_html);

                $block_html = file_get_contents("custom_blocks/tool_list.html");
                $base_html = str_replace("{{custom_block_page}}", $block_html, $base_html);

                $tools_list = [];
                $baseDir = "tools/{$tool_type}";

                if (is_dir($baseDir)) {
                    foreach (scandir($baseDir) as $folder) {
                        $folderPath = $baseDir . "/" . $folder;

                        if ($folder === "." || $folder === ".." || !is_dir($folderPath)) continue;

                        $blockFile = $folderPath . "/block.html";
                        $metaFile = $folderPath . "/block_meta.html";

                        if (file_exists($blockFile) && file_exists($metaFile) && filesize($blockFile) > 0 && filesize($metaFile) > 0) {
                            $metaContent = file_get_contents($metaFile);

                            preg_match('/<title>(.*?)<\/title>/is', $metaContent, $titleMatch);
                            preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $metaContent, $metaMatch);
                            preg_match('/<meta\s+property=["\']og:url["\']\s+content=["\'](.*?)["\']/is', $metaContent, $urlMatch);

                            if (!empty($titleMatch[1]) && !empty($metaMatch[1]) && !empty($urlMatch[1])) {
                                $tools_list[] = [
                                    trim($titleMatch[1]),
                                    trim($metaMatch[1]),
                                    trim($urlMatch[1])
                                ];
                            }
                        }
                    }
                }

                $tools_list_html = "";
                foreach ($tools_list as $i => $item) {
                    list($title, $desc, $url) = $item;
                    $tools_list_html .= "</br><h2 style=\"color:#007bff\"><a href=\"{$url}\">" . ($i + 1) . ". {$title}</a></h2><p>{$desc}</br><a href=\"{$url}\">Learn More</a></p></br>";
                }

                if (!$tools_list_html) {
                    $tools_list_html = "<p>Coming soon...</p>";
                }

                $base_html = str_replace("{{custom_block_tool_list}}", $tools_list_html, $base_html);
                $base_html = str_replace("{{custom_block_tool_type}}", ucwords($tool_type), $base_html);
            }

            // Informational pages
            else {
                $page_map = [
                    "/about-us"       => ["About Us",       "about-us",       "about-us_meta"],
                    "/contact"        => ["Contact Us",     "contact-us",     "contact-us_meta"],
                    "/privacy-policy" => ["Privacy Policy", "privacy",        "privacy_meta"],
                    "/terms-of-service" => ["Terms of Services", "terms",     "terms_meta"]
                ];

                if (isset($page_map[$url_path])) {
                    list($heading, $html_file, $meta_file) = $page_map[$url_path];

                    $base_html = str_replace("{{custom_block_sideheading}}", $heading, $base_html);
                    $base_html = str_replace("{{custom_block_page}}", file_get_contents("custom_blocks/{$html_file}.html"), $base_html);
                    $base_html = str_replace("{{custom_block_seo_meta}}", file_get_contents("custom_blocks/{$meta_file}.html"), $base_html);
                }
            }

            returnHTML($base_html);
        } catch (Exception $e) {
            returnRandom5xxError();
        }
    } else {
        methodNotAllowed();
    }
}
elseif ($url_path === "/subscription-success") {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $block_html = file_get_contents("custom_blocks/subscription_success.html");
        $meta_html  = file_get_contents("custom_blocks/subscription_success_meta.html");

        $base_html = str_replace("{{custom_block_page}}", $block_html, $base_html);
        $base_html = str_replace("{{custom_block_seo_meta}}", $meta_html, $base_html);
        $base_html = str_replace("{{custom_block_sideheading}}", 'Subscription Success', $base_html);

        returnHTML($base_html);
    } else {
        methodNotAllowed();
    }
}

elseif ($url_path === "/subscribe") {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!$refreshed_session_id) {
            redirectToOther('login');
        }
       
        $block_meta = file_get_contents("custom_blocks/subscribe_meta.html");
        $base_html  = str_replace("{{custom_block_seo_meta}}", $block_meta, $base_html);
        $base_html  = str_replace("{{custom_block_sideheading}}", 'Subscribe', $base_html);
        

        // Handle inactive or expired subscription
        if (strpos($subscription_text, "Expired") !== false || strpos($subscription_text, "Not subscribed") !== false) {
            if (!isWebhookActive()) {
                returnRandom5xxError();
            }

            // Retrieve subscription attempt
            $stmt = $conn->prepare("SELECT subscription_attempt FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $last_attempt = $result->num_rows > 0 ? $result->fetch_assoc()["subscription_attempt"] : "";
            
            $remaining = $last_attempt ? ($last_attempt + 10 * 60) - time() : 0;

            if ($remaining > 0) {
                $block_html = file_get_contents("custom_blocks/subscription_attempt.html");
                $block_html = str_replace("{{custom_block_remaining_seconds}}", strval($remaining), $block_html);
            } else {
                $block_html = file_get_contents("custom_blocks/subscribe.html");
                $base_html  = str_replace("{{custom_block_page}}", $block_html, $base_html);
                $base_html  = str_replace("{{custom_block_plan_id}}", $PLAN_ID, $base_html);
                $base_html  = str_replace("{{custom_block_client_id}}", $CLIENT_ID, $base_html);
                $base_html  = str_replace("{{custom_block_custom_id}}", $user_id, $base_html);
                // Update subscription attempt
                $now = time();
                $stmt = $conn->prepare("UPDATE users SET subscription_attempt = ? WHERE email = ?");
                $stmt->bind_param("is", $now, $email);
                //$stmt->execute();
                //$conn->commit();
            }

            $base_html = str_replace("{{custom_block_page}}", $block_html, $base_html);
            returnHTML($base_html);
        }

        // Handle active subscription
        elseif (strpos($subscription_text, "Active") !== false) {
            $base_html = str_replace("{{custom_block_credit_link}}", '<div><a href="/growthworld-premium-tools/" class="btn-x12yz">Premium Tools</a></div></br>', $base_html);
            $base_html = str_replace("{{custom_block_page}}", $paragraph, $base_html);
            $base_html = str_replace("{{custom_block_para_text}}", "You already have an active subscription!</br><div style='text-align:center'><a style='text-decoration:none' href='/growthworld-premium-tools/dashboard'>Back to Dashboard!</a></div>", $base_html);
            $base_html = str_replace("{{custom_block_para_color}}", "color: red;", $base_html);

            returnHTML($base_html);
        }
    } else {
        methodNotAllowed();
    }
}
else {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        methodNotAllowed();
    }

    $dir_name = trim($url_path, "/");
    if (!$dir_name) {
        returnRandom5xxError();
    }

    $base_file = "base.html";
    $base_html = file_exists($base_file) ? file_get_contents($base_file) : returnRandom5xxError();

    // Attempt subscription tools path
    $tool_type = "subscription";
    $index_file       = "tools/$tool_type/$dir_name/index.html";
    $block_file       = "tools/$tool_type/$dir_name/block.html";
    $block_meta_file  = "tools/$tool_type/$dir_name/block_meta.html";

    // If subscription tool doesn't exist, check credit tools
    if (!(file_exists($block_file) && file_exists($block_meta_file))) {
        $tool_type = "credit";
        $index_file       = "tools/$tool_type/$dir_name/index.html";
        $block_file       = "tools/$tool_type/$dir_name/block.html";
        $block_meta_file  = "tools/$tool_type/$dir_name/block_meta.html";

        if (!(file_exists($block_file) && file_exists($block_meta_file))) {
            returnRandom5xxError();
        }
    }

    // Set access button based on login status
    $access_link = $refreshed_session_id
        ? '<a href="/growthworld-premium-tools/" class="btn-x12yz">Home</a></br></br>'
        : '<a href="/growthworld-premium-tools/login" class="btn-x12yz">Get Access Now</a></br></br>';
    $base_html = str_replace("{{custom_block_accessnow}}", $access_link, $base_html);

    // Load block and metadata
    $block_html = file_get_contents($block_file);
    $block_meta = file_get_contents($block_meta_file);

    $base_html = str_replace("{{custom_block_sideheading}}", 'Tool Page', $base_html);
    $base_html = str_replace("{{custom_block_page}}", $block_html, $base_html);
    $base_html = str_replace("{{custom_block_seo_meta}}", $block_meta, $base_html);

    // Optional: Write the full rendered page to cache for speed
    file_put_contents($index_file, $base_html);

    returnHTML($base_html);
}


?>
