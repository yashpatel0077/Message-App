<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$errors = [];
$success = '';

function esc($v)
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function hasColumn(mysqli $conn, string $table, string $col): bool
{
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st) {
        return false;
    }
    $st->bind_param("ss", $table, $col);
    $st->execute();
    $res = $st->get_result();
    $ok = ($res && $res->num_rows > 0);
    $st->close();
    return $ok;
}

function ensureColumn(mysqli $conn, string $table, string $col, string $definition): void
{
    if (!hasColumn($conn, $table, $col)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
    }
}

ensureColumn($conn, 'users', 'profile_name', 'VARCHAR(100) NULL');
ensureColumn($conn, 'users', 'username', 'VARCHAR(100) NULL');
ensureColumn($conn, 'users', 'about', 'TEXT NULL');
ensureColumn($conn, 'users', 'avatar', 'VARCHAR(500) NULL');
ensureColumn($conn, 'users', 'profile_completed', 'TINYINT(1) NOT NULL DEFAULT 0');

$stmt = $conn->prepare("SELECT id, email, display_name, profile_name, username, about, avatar, profile_completed FROM users WHERE id = ? LIMIT 1");
if (!$stmt) {
    die("Database error. Could not load settings.");
}
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profileName = trim($_POST['profile_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $about = trim($_POST['about'] ?? '');
    $avatarPath = (string) ($user['avatar'] ?? '');

    if ($profileName === '' || mb_strlen($profileName) < 2) {
        $errors[] = "Name must be at least 2 characters.";
    }

    if ($username !== '' && !preg_match('/^[a-zA-Z0-9._]{3,30}$/', $username)) {
        $errors[] = "Username must be 3-30 characters using letters, numbers, dot, or underscore.";
    }

    if (mb_strlen($about) > 220) {
        $errors[] = "About must be 220 characters or less.";
    }

    if (!$errors && $username !== '') {
        $st = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        if ($st) {
            $st->bind_param("si", $username, $currentUserId);
            $st->execute();
            $exists = $st->get_result()->fetch_assoc();
            $st->close();
            if ($exists) {
                $errors[] = "This username is already taken.";
            }
        }
    }

    if (!$errors && !empty($_FILES['avatar']['name'])) {
        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Avatar upload failed. Please try again.";
        } else {
            $tmp = $_FILES['avatar']['tmp_name'];
            $size = (int) ($_FILES['avatar']['size'] ?? 0);

            if ($size > 2 * 1024 * 1024) {
                $errors[] = "Avatar must be under 2MB.";
            } else {
                $info = @getimagesize($tmp);
                if (!$info) {
                    $errors[] = "Avatar must be a JPG, PNG, GIF, or WEBP image.";
                } else {
                    $mime = $info['mime'] ?? '';
                    $ext = '';
                    if ($mime === 'image/jpeg') {
                        $ext = 'jpg';
                    } elseif ($mime === 'image/png') {
                        $ext = 'png';
                    } elseif ($mime === 'image/gif') {
                        $ext = 'gif';
                    } elseif ($mime === 'image/webp') {
                        $ext = 'webp';
                    } else {
                        $errors[] = "Only JPG, PNG, GIF, or WEBP images are allowed.";
                    }

                    if (!$errors) {
                        $destDir = __DIR__ . '/uploads/avatars/';
                        if (!is_dir($destDir)) {
                            @mkdir($destDir, 0775, true);
                        }

                        $fileName = 'user_' . $currentUserId . '_' . time() . '.' . $ext;
                        $destPath = $destDir . $fileName;

                        if (!move_uploaded_file($tmp, $destPath)) {
                            $errors[] = "Could not save avatar. Check the uploads folder permissions.";
                        } else {
                            $avatarPath = 'uploads/avatars/' . $fileName;
                        }
                    }
                }
            }
        }
    }

    if (!$errors) {
        $completed = 1;
        $st = $conn->prepare("
            UPDATE users
            SET display_name = ?, profile_name = ?, username = ?, about = ?, avatar = ?, profile_completed = ?
            WHERE id = ?
        ");

        if (!$st) {
            $errors[] = "Database error. Could not save settings.";
        } else {
            $st->bind_param("sssssii", $profileName, $profileName, $username, $about, $avatarPath, $completed, $currentUserId);
            $st->execute();
            $st->close();

            $_SESSION['username'] = $username !== '' ? $username : strtok((string) $user['email'], '@');
            $success = "Settings saved.";

            $user['display_name'] = $profileName;
            $user['profile_name'] = $profileName;
            $user['username'] = $username;
            $user['about'] = $about;
            $user['avatar'] = $avatarPath;
            $user['profile_completed'] = $completed;
        }
    }
}

$nameValue = $_POST['profile_name'] ?? ($user['profile_name'] ?: $user['display_name'] ?: strtok((string) $user['email'], '@'));
$usernameValue = $_POST['username'] ?? ($user['username'] ?? '');
$aboutValue = $_POST['about'] ?? ($user['about'] ?? '');
$avatar = (string) ($user['avatar'] ?? '');
$initial = strtoupper(substr((string) ($nameValue ?: $user['email'] ?: 'U'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Messenger</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--bg:#eef3f7;--panel:#fff;--ink:#17212b;--muted:#62717e;--line:#dfe7ee;--accent:#00a884;--accent-dark:#087963;--soft:#f6f9fb;--danger:#c93b36;--danger-bg:#fff0ef;--success:#087963;--success-bg:#e8f7f3}
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;background:linear-gradient(125deg,rgba(0,168,132,.14),transparent 34%),linear-gradient(245deg,rgba(47,111,237,.12),transparent 42%),var(--bg);font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink)}
        .settings-app{min-height:100vh;display:grid;grid-template-columns:260px 1fr}
        .rail{background:rgba(255,255,255,.72);border-right:1px solid rgba(223,231,238,.78);padding:24px 18px;display:flex;flex-direction:column;justify-content:space-between;backdrop-filter:blur(14px)}
        .brand{display:flex;align-items:center;gap:12px;font-weight:900;font-size:18px;margin-bottom:28px}
        .brand-icon{width:42px;height:42px;border-radius:14px;background:var(--accent);color:#fff;display:grid;place-items:center}
        .nav{display:grid;gap:8px}
        .nav a{display:flex;align-items:center;gap:12px;padding:12px 13px;border-radius:14px;text-decoration:none;color:#34424f;font-weight:800;border:1px solid transparent}
        .nav a:hover,.nav a.active{background:#fff;border-color:var(--line);color:var(--accent-dark)}
        .logout{color:#c93b36!important}
        .content{padding:34px;display:flex;align-items:center;justify-content:center}
        .card{width:min(760px,100%);background:rgba(255,255,255,.9);border:1px solid rgba(255,255,255,.74);border-radius:24px;box-shadow:0 24px 70px rgba(23,33,43,.12);overflow:hidden}
        .card-head{padding:28px 30px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:18px}
        .avatar{width:76px;height:76px;border-radius:22px;background:#e0e7ee;color:var(--accent-dark);display:grid;place-items:center;font-size:28px;font-weight:900;overflow:hidden;flex:0 0 auto}
        .avatar img{width:100%;height:100%;object-fit:cover;display:block}
        h1{font-size:28px;line-height:1.15;letter-spacing:0;margin-bottom:6px}
        .subtitle{color:var(--muted);font-size:14px;line-height:1.5;word-break:break-all}
        .form{padding:28px 30px}
        .alert{padding:13px 14px;border-radius:14px;margin-bottom:16px;font-size:14px;line-height:1.45}
        .alert-danger{background:var(--danger-bg);border:1px solid #ffd1cf;color:var(--danger)}
        .alert-success{background:var(--success-bg);border:1px solid #c9eee3;color:var(--success)}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .field{margin-bottom:16px}
        label{display:block;font-size:13px;color:var(--muted);font-weight:800;margin-bottom:8px}
        input,textarea{width:100%;padding:14px;border:1px solid var(--line);border-radius:14px;background:var(--soft);font:inherit;color:var(--ink);outline:none;transition:border-color .2s,box-shadow .2s,background .2s}
        input:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(0,168,132,.13);background:#fff}
        input[readonly]{color:#7c8994;background:#eef3f7}
        textarea{min-height:112px;resize:vertical}
        .hint{font-size:12px;color:#7c8994;margin-top:7px}
        .actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px}
        .btn{border:0;border-radius:14px;padding:13px 16px;font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:10px}
        .btn-primary{background:var(--accent);color:#fff;min-width:170px}
        .btn-primary:hover{background:var(--accent-dark)}
        .btn-ghost{background:#f3f6f8;color:#34424f;border:1px solid var(--line)}
        .btn-ghost:hover{background:#eaf0f4}
        @media (max-width:820px){.settings-app{grid-template-columns:1fr}.rail{position:static;padding:16px}.nav{grid-template-columns:repeat(3,1fr)}.nav a{justify-content:center;font-size:13px}.content{padding:16px}.card-head,.form{padding:22px}.grid{grid-template-columns:1fr}.actions{flex-direction:column-reverse;align-items:stretch}.btn{width:100%}}
    </style>
</head>
<body>
<main class="settings-app">
    <aside class="rail">
        <div>
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-comment-dots"></i></div>
                <span>Messenger</span>
            </div>
            <nav class="nav" aria-label="Settings navigation">
                <a href="index.php"><i class="fa-solid fa-message"></i> Chats</a>
                <a href="settings.php" class="active"><i class="fa-solid fa-gear"></i> Settings</a>
                <a href="set_profile.php?edit=1"><i class="fa-solid fa-user"></i> Profile</a>
            </nav>
        </div>
        <nav class="nav" aria-label="Account">
            <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <section class="content">
        <div class="card">
            <header class="card-head">
                <div class="avatar">
                    <?php if ($avatar): ?>
                        <img src="<?php echo esc($avatar); ?>" alt="Profile avatar">
                    <?php else: ?>
                        <?php echo esc($initial); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h1>Settings</h1>
                    <p class="subtitle"><?php echo esc($user['email']); ?></p>
                </div>
            </header>

            <form class="form" method="post" enctype="multipart/form-data" autocomplete="off">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo esc($success); ?></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $err): ?>
                            <div><?php echo esc($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="grid">
                    <div class="field">
                        <label for="profile_name">Display name</label>
                        <input id="profile_name" name="profile_name" type="text" value="<?php echo esc($nameValue); ?>" required>
                    </div>
                    <div class="field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" value="<?php echo esc($usernameValue); ?>" placeholder="your.name">
                        <div class="hint">Letters, numbers, dot, underscore. 3-30 characters.</div>
                    </div>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" value="<?php echo esc($user['email']); ?>" readonly>
                </div>

                <div class="field">
                    <label for="about">About</label>
                    <textarea id="about" name="about" maxlength="220" placeholder="Write something about yourself."><?php echo esc($aboutValue); ?></textarea>
                    <div class="hint">Shown on your profile. Maximum 220 characters.</div>
                </div>

                <div class="field">
                    <label for="avatar">Profile photo</label>
                    <input id="avatar" name="avatar" type="file" accept="image/*">
                    <div class="hint">JPG, PNG, GIF, or WEBP. Max 2MB.</div>
                </div>

                <div class="actions">
                    <a class="btn btn-ghost" href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to chats</a>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save settings</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
