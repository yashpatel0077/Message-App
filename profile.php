<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['avatar']['tmp_name'];
        $origName = $_FILES['avatar']['name'];
        $fileSize = $_FILES['avatar']['size'];
        $fileInfo = pathinfo($origName);
        $ext = strtolower($fileInfo['extension'] ?? '');

        $allowed = ['jpg','jpeg','png','gif'];
        if (!in_array($ext, $allowed, true)) {
            $error = "Only JPG, PNG, GIF images are allowed.";
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $error = "Image must be less than 2 MB.";
        } else {
            $newName = 'user_' . $currentUserId . '_' . time() . '.' . $ext;
            $destDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0775, true);
            }
            $destPath = $destDir . $newName;
            if (move_uploaded_file($tmpName, $destPath)) {
                // Save relative path to DB
                $relativePath = 'uploads/avatars/' . $newName;

                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->bind_param("si", $relativePath, $currentUserId);
                $stmt->execute();
                $stmt->close();

                $success = "Profile photo updated.";
            } else {
                $error = "Failed to move uploaded file.";
            }
        }
    } else {
        $error = "Upload error code: " . (int)$_FILES['avatar']['error'];
    }
}

// Load user info
$stmt = $conn->prepare("SELECT phone, display_name, about, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>My Profile</title>
  <style>
    body {
      margin:0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
      background:#f0f2f5;
    }
    .wrapper {
      max-width:600px;
      margin:40px auto;
      background:#fff;
      border-radius:8px;
      box-shadow:0 1px 3px rgba(0,0,0,0.2);
      padding:20px 24px;
    }
    h1 { margin:0 0 16px 0; font-size:22px; }
    .profile-top {
      display:flex;
      gap:16px;
      align-items:center;
      margin-bottom:20px;
    }
    .avatar {
      width:80px;
      height:80px;
      border-radius:50%;
      background:#c4c4c4;
      display:flex;
      align-items:center;
      justify-content:center;
      color:#fff;
      font-size:32px;
      overflow:hidden;
      flex-shrink:0;
    }
    .avatar img {
      width:100%;
      height:100%;
      object-fit:cover;
    }
    .field-label {
      font-size:13px;
      color:#65676b;
    }
    .field-value {
      font-size:15px;
      margin-bottom:6px;
    }
    form {
      margin-top:12px;
    }
    input[type="file"] {
      display:block;
      margin:8px 0 12px 0;
    }
    button {
      padding:8px 14px;
      border:none;
      border-radius:4px;
      background:#1877f2;
      color:#fff;
      cursor:pointer;
      font-size:14px;
    }
    .msg {
      margin-bottom:10px;
      font-size:13px;
    }
    .msg.error { color:#d93025; }
    .msg.success { color:#137333; }
    a.back-link {
      display:inline-block;
      margin-top:16px;
      font-size:14px;
      text-decoration:none;
      color:#1877f2;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <h1>My Profile</h1>

    <?php if (!empty($error)): ?>
      <div class="msg error"><?php echo esc($error); ?></div>
    <?php elseif (!empty($success)): ?>
      <div class="msg success"><?php echo esc($success); ?></div>
    <?php endif; ?>

    <div class="profile-top">
      <div class="avatar">
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?php echo esc($user['avatar']); ?>" alt="Avatar">
        <?php else: ?>
          <?php
          $initial = strtoupper(substr($user['display_name'] ?: $user['phone'], 0, 1));
          echo esc($initial);
          ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="field-label">Name / Phone</div>
        <div class="field-value">
          <?php echo esc($user['display_name'] ?: $user['phone']); ?>
        </div>
        <div class="field-label">About</div>
        <div class="field-value"><?php echo esc($user['about'] ?: 'Hey there! I am using WeChats.'); ?></div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data">
      <div class="field-label">Change profile photo</div>
      <input type="file" name="avatar" accept="image/*" required>
      <button type="submit">Upload</button>
    </form>

    <a class="back-link" href="index.php">&larr; Back to chats</a>
  </div>
</body>
</html>
