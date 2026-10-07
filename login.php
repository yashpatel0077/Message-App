<?php
session_start();
require 'config.php';

if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

function esc($v)
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function sendRealEmail($to, $otp)
{
    $smtpHost = 'smtp.gmail.com';
    $smtpEmail = 'yash.laners@gmail.com';
    $smtpPass = 'hvhwwrqdtklqiofd';

    if (!file_exists(__DIR__ . '/PHPMailer/src/PHPMailer.php')) {
        return false;
    }

    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpEmail;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom($smtpEmail, 'Messenger');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Your Messenger Login Code';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;line-height:1.6'>
                <h2>Your login code</h2>
                <p style='font-size:16px'>Use this OTP to login:</p>
                <div style='font-size:32px;font-weight:700;letter-spacing:6px'>$otp</div>
                <p>Valid for <b>10 minutes</b>.</p>
                <p>If you did not request this, ignore this email.</p>
            </div>
        ";

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}

$email = $_SESSION['login_email'] ?? $_SESSION['otp_email'] ?? '';
$mode = $email ? 'verify' : 'login';
$error = '';
$success = '';

if (isset($_GET['restart']) && $_GET['restart'] == '1') {
    unset($_SESSION['login_email'], $_SESSION['otp_email'], $_SESSION['otp_fallback']);
    header("Location: login.php");
    exit;
}

if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    $email = $_SESSION['login_email'] ?? $_SESSION['otp_email'] ?? '';

    if (!$email) {
        header("Location: login.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        $error = "Database error. Please try again.";
        $mode = 'verify';
    } else {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            unset($_SESSION['login_email'], $_SESSION['otp_email'], $_SESSION['otp_fallback']);
            $error = "Account not found. Enter your email again.";
            $mode = 'login';
        } else {
            $otp = sprintf("%06d", random_int(0, 999999));
            $expiresAt = date('Y-m-d H:i:s', time() + 600);
            $userId = (int) $row['id'];
            $up = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");

            if (!$up) {
                $error = "Database error. Please try again.";
                $mode = 'verify';
            } else {
                $up->bind_param("ssi", $otp, $expiresAt, $userId);
                $up->execute();
                $up->close();

                $_SESSION['login_email'] = $email;
                $_SESSION['otp_email'] = $email;

                if (sendRealEmail($email, $otp)) {
                    unset($_SESSION['otp_fallback']);
                    $success = "A fresh OTP was sent to your email.";
                } else {
                    $_SESSION['otp_fallback'] = $otp;
                    $success = "Email failed. Testing OTP: $otp";
                }
                $mode = 'verify';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_otp') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
        $mode = 'login';
    } else {
        $_SESSION['login_email'] = $email;

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            $error = "Database error. Please try again.";
            $mode = 'login';
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $displayName = strtok($email, '@');
                $ins = $conn->prepare("INSERT INTO users (email, display_name) VALUES (?, ?)");
                if (!$ins) {
                    $error = "Database error. Please try again.";
                    $mode = 'login';
                } else {
                    $ins->bind_param("ss", $email, $displayName);
                    $ins->execute();
                    $userId = (int) $ins->insert_id;
                    $ins->close();
                }
            } else {
                $userId = (int) $row['id'];
            }

            if (!$error) {
                $otp = sprintf("%06d", random_int(0, 999999));
                $expiresAt = date('Y-m-d H:i:s', time() + 600);
                $up = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");

                if (!$up) {
                    $error = "Database error. Please try again.";
                    $mode = 'login';
                } else {
                    $up->bind_param("ssi", $otp, $expiresAt, $userId);
                    $up->execute();
                    $up->close();

                    $_SESSION['otp_email'] = $email;
                    if (sendRealEmail($email, $otp)) {
                        $success = "OTP sent to your email. Check inbox or spam.";
                    } else {
                        $_SESSION['otp_fallback'] = $otp;
                        $success = "Email failed. Testing OTP: $otp";
                    }
                    $mode = 'verify';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_otp') {
    $email = $_SESSION['login_email'] ?? $_SESSION['otp_email'] ?? '';
    if (!$email) {
        header("Location: login.php");
        exit;
    }

    $otp = trim($_POST['otp'] ?? '');
    $stmt = $conn->prepare("SELECT id, otp_code, otp_expires_at FROM users WHERE email = ? LIMIT 1");

    if (!$stmt) {
        $error = "Database error. Please try again.";
        $mode = 'verify';
    } else {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = "Account not found.";
            $mode = 'login';
        } else {
            $dbOtp = $user['otp_code'] ?? '';
            $exp = $user['otp_expires_at'] ?? null;

            if (empty($otp) || $otp !== $dbOtp || strtotime((string) $exp) < time()) {
                $error = "Invalid or expired OTP. Please request a new code.";
                $mode = 'verify';
            } else {
                $userId = (int) $user['id'];
                $username = strtok($email, '@');
                $avatar = "https://ui-avatars.com/api/?name=" . urlencode($username) . "&size=128&background=4285f4&color=fff";

                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $avatar, $userId);
                    $stmt->execute();
                    $stmt->close();
                }

                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;

                $up = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
                if ($up) {
                    $up->bind_param("i", $userId);
                    $up->execute();
                    $up->close();
                }

                unset($_SESSION['login_email'], $_SESSION['otp_email'], $_SESSION['otp_fallback']);
                header("Location: index.php");
                exit;
            }
        }
    }
}

$fallbackOtp = $_SESSION['otp_fallback'] ?? null;
$email = $_SESSION['login_email'] ?? $_SESSION['otp_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messenger Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--ink:#111b21;--muted:#667781;--line:#e3e5ea;--panel:#fff;--soft:#f6f7f9;--accent:#25d366;--accent-dark:#128c7e;--danger:#d93025;--danger-bg:#ffebee;--success:#167c45;--success-bg:#e8f8f5}
        *{box-sizing:border-box;margin:0;padding:0}
        html,body{height:100%}
        body{min-height:100vh;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;color:var(--ink);background:#f0f2f5;display:grid;place-items:center;padding:20px}
        .login-shell{width:min(440px,100%);background:var(--panel);border:1px solid var(--line);box-shadow:0 20px 44px rgba(0,0,0,.10);border-radius:18px;overflow:hidden}
        .login-visual{background:#f9fafb;border-bottom:1px solid var(--line);padding:24px 26px 20px;color:var(--ink)}
        .brand-mark{display:flex;align-items:center;gap:12px;font-weight:900;font-size:20px;color:#128C7E}
        .brand-icon{width:44px;height:44px;border-radius:16px;display:grid;place-items:center;background:#25d366;color:#fff;box-shadow:0 4px 12px rgba(37,211,102,.35)}
        .visual-copy{margin-top:22px}
        .visual-copy h2{font-size:22px;line-height:1.2;margin-bottom:6px;letter-spacing:0;color:var(--ink)}
        .visual-copy p{font-size:14px;line-height:1.45;color:var(--muted)}
        .message-stack{display:none}
        .login-panel{background:var(--panel);padding:28px 26px 26px}
        .form-card{width:100%}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;color:#167c45;background:#d1f5e3;border:0;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:800;margin-bottom:16px}
        .brand{font-size:26px;font-weight:900;margin:0 0 8px;color:var(--ink);letter-spacing:0}
        .sub{margin:0 0 24px;color:var(--muted);font-size:14px;line-height:1.5}
        .sub strong{color:var(--ink);word-break:break-all}
        .field{position:relative;margin-bottom:16px}
        .field i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#667781;font-size:13px}
        input{width:100%;padding:12px 14px 12px 36px;border:1px solid #dde1e7;border-radius:10px;font-size:14px;outline:0;background:var(--soft);transition:background .15s ease,border-color .15s ease,box-shadow .15s ease}
        .otp-input{padding-left:14px;text-align:center;letter-spacing:8px;font-size:22px;font-weight:800}
        input:focus{background:#fff;border-color:#25d366;box-shadow:0 0 0 3px rgba(37,211,102,.10)}
        button{width:100%;height:44px;padding:0 16px;border:0;border-radius:12px;background:#25d366;color:#fff;font-weight:800;font-size:14px;cursor:pointer;transition:background .15s ease,transform .15s ease;display:inline-flex;align-items:center;justify-content:center;gap:10px}
        button:hover{background:#1fb85a;transform:translateY(-1px)}
        .msg{margin:0 0 16px;padding:12px 14px;border-radius:12px;font-size:14px;line-height:1.45;text-align:left}
        .err{color:var(--danger);background:var(--danger-bg);border:1px solid #f2c7c7}
        .success{color:var(--success);background:var(--success-bg);border:1px solid #c6f6e8}
        .links-row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px}
        .link-btn{display:inline-flex;align-items:center;gap:8px;text-decoration:none;font-size:13px;font-weight:700;padding:9px 10px;border-radius:10px;color:#3b4a54;background:#f0f2f5;border:1px solid #e9edef;transition:background .15s ease,border-color .15s ease}
        .link-btn:hover{background:#e9edef;border-color:#dde3e7;color:#111b21}
        .link-btn--resend{color:#0b846d;background:#e8f8f5;border-color:#c6f6e8}
        .link-btn--back{background:transparent;border-color:transparent;padding-inline:4px}
        .login-foot{margin-top:20px;color:#667781;font-size:12px;line-height:1.45}
        @media (max-width:520px){body{padding:12px;place-items:start center}.login-shell{border-radius:14px}.login-visual,.login-panel{padding:22px 18px}.brand{font-size:24px}.visual-copy h2{font-size:20px}.links-row{align-items:stretch;flex-direction:column}.link-btn{justify-content:center}}
    </style>
</head>
<body>
<main class="login-shell">
    <section class="login-visual" aria-label="Messenger preview">
        <div class="brand-mark">
            <div class="brand-icon"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i></div>
            <span>Messenger</span>
        </div>
        <div class="visual-copy">
            <h2>Private chats, simple sign in.</h2>
            <p>Use your email code to get back to your messages without remembering another password.</p>
        </div>
        <div class="message-stack" aria-hidden="true">
            <div class="demo-bubble">Your secure code is ready<small>Just now</small></div>
            <div class="demo-bubble">Open chats in one tap<small>Messenger</small></div>
        </div>
    </section>

    <section class="login-panel">
        <div class="form-card">
            <?php if ($mode === 'verify' && $email): ?>
                <div class="eyebrow"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Verification</div>
                <h1 class="brand">Enter Code</h1>
                <p class="sub">We sent a 6-digit login code to <strong><?php echo esc($email); ?></strong>.</p>
            <?php else: ?>
                <div class="eyebrow"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Email OTP login</div>
                <h1 class="brand">Welcome back</h1>
                <p class="sub">Enter your email address and we will send a one-time code for your account.</p>
            <?php endif; ?>

            <?php if ($error): ?><div class="msg err"><?php echo esc($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="msg success"><?php echo esc($success); ?></div><?php endif; ?>
            <?php if ($fallbackOtp && $mode === 'verify'): ?><div class="msg success"><strong>Testing OTP:</strong> <?php echo esc($fallbackOtp); ?></div><?php endif; ?>

            <?php if ($mode === 'verify' && $email): ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="field">
                        <input type="text" name="otp" placeholder="000000" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus class="otp-input">
                    </div>
                    <button type="submit" class="btn"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i> Continue</button>
                </form>
                <div class="links-row">
                    <a href="login.php?resend=1" class="link-btn link-btn--resend"><i class="fa-solid fa-rotate-right"></i><span>Resend code</span></a>
                    <a href="login.php?restart=1" class="link-btn link-btn--back"><i class="fa-solid fa-arrow-left"></i><span>Back</span></a>
                </div>
            <?php else: ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="send_otp">
                    <div class="field">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <input type="email" name="email" placeholder="Enter your email" required class="text-input">
                    </div>
                    <button type="submit" class="btn"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send code</button>
                </form>
            <?php endif; ?>
            <div class="login-foot">Codes expire after 10 minutes. New users are created automatically after email verification.</div>
        </div>
    </section>
</main>
</body>
</html>
