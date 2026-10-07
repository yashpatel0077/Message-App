<?php
session_start();
require 'config.php';

$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

if ($phone == '') {
    die("Phone is required");
}

// generate 6-digit OTP
$otp = rand(100000, 999999);
$expiresAt = date('Y-m-d H:i:s', time() + 5 * 60); // 5 minutes

// upsert user by phone
$sql = "INSERT INTO users (phone, otp_code, otp_expires_at)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
          otp_code = VALUES(otp_code),
          otp_expires_at = VALUES(otp_expires_at)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $phone, $otp, $expiresAt);
$stmt->execute();
$stmt->close();

// TODO: here you will call your SMS API later, for now we only show OTP on screen
// sendSms($phone, "Your login OTP is: " . $otp);

$_SESSION['pending_phone'] = $phone;
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Enter OTP</title>
  <style>
    body {
      margin:0;
      font-family: Arial, sans-serif;
      background:#f0f2f5;
      display:flex;
      justify-content:center;
      align-items:center;
      height:100vh;
    }
    .otp-box {
      background:#fff;
      padding:24px 28px;
      border-radius:8px;
      box-shadow:0 2px 6px rgba(0,0,0,.2);
      min-width:300px;
    }
    .otp-box h2 {
      margin:0 0 10px 0;
    }
    .otp-box p {
      margin:0 0 14px 0;
      font-size:14px;
      color:#555;
    }
    .otp-box input[type="text"] {
      width:100%;
      padding:9px 10px;
      margin-bottom:10px;
      border-radius:4px;
      border:1px solid #ccc;
      font-size:14px;
    }
    .otp-box button {
      width:100%;
      padding:9px 10px;
      border:none;
      border-radius:4px;
      background:#128C7E;
      color:#fff;
      font-size:15px;
      cursor:pointer;
    }
    .testing {
      font-size:13px;
      color:#b00020;
      margin-bottom:8px;
    }
  </style>
</head>
<body>
  <div class="otp-box">
    <h2>Enter OTP</h2>
    <p>We sent an OTP to <strong><?php echo htmlspecialchars($phone); ?></strong>.</p>

    <!-- Testing helper: remove this block when real SMS sending is ready -->
    <p class="testing">
      Testing only: Your OTP is <strong><?php echo $otp; ?></strong>
    </p>

    <form method="post" action="verify_otp.php">
      <input type="text" name="otp" placeholder="Enter 6-digit OTP" required>
      <button type="submit">Verify &amp; Login</button>
    </form>
  </div>
</body>
</html>
