<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

session_start();
require __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$isEdit = isset($_GET['edit']) && $_GET['edit'] === '1';
$returnToSettings = ($_GET['return'] ?? '') === 'settings';
$errors = [];

function esc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$stmt = $conn->prepare('SELECT id, email, display_name, username, profile_name, about, avatar, profile_completed FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    exit('Could not load your profile.');
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ((int) $user['profile_completed'] === 1 && !$isEdit) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profileName = trim((string) ($_POST['profile_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $about = trim((string) ($_POST['about'] ?? ''));
    $avatarPath = (string) ($user['avatar'] ?? '');

    if (mb_strlen($profileName) < 2 || mb_strlen($profileName) > 100) {
        $errors[] = 'Profile name must be between 2 and 100 characters.';
    }
    if (!preg_match('/^[a-zA-Z0-9._]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3–30 characters using letters, numbers, dots, or underscores.';
    }
    if (mb_strlen($about) > 220) {
        $errors[] = 'About must be 220 characters or less.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        if (!$stmt) {
            $errors[] = 'Could not validate the username. Please try again.';
        } else {
            $stmt->bind_param('si', $username, $userId);
            $stmt->execute();
            $taken = (bool) $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($taken) $errors[] = 'That username is already in use.';
        }
    }

    if (!$errors && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Avatar upload failed. Please try again.';
        } elseif ((int) $file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Avatar must be 2 MB or smaller.';
        } else {
            $image = @getimagesize($file['tmp_name']);
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            $mime = $image['mime'] ?? '';
            if (!$image || !isset($extensions[$mime])) {
                $errors[] = 'Choose a JPG, PNG, WEBP, or GIF image.';
            } else {
                $avatarDir = __DIR__ . '/uploads/avatars';
                if (!is_dir($avatarDir) && !mkdir($avatarDir, 0775, true) && !is_dir($avatarDir)) {
                    $errors[] = 'Could not create the avatar upload folder.';
                } else {
                    $fileName = 'user_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                    if (!move_uploaded_file($file['tmp_name'], $avatarDir . '/' . $fileName)) {
                        $errors[] = 'Could not save your avatar. Please check upload permissions.';
                    } else {
                        $avatarPath = 'uploads/avatars/' . $fileName;
                    }
                }
            }
        }
    }

    if (!$errors) {
        $completed = 1;
        $stmt = $conn->prepare('UPDATE users SET display_name = ?, profile_name = ?, username = ?, about = ?, avatar = ?, profile_completed = ? WHERE id = ?');
        if (!$stmt) {
            $errors[] = 'Could not save your profile. Please try again.';
        } else {
            $stmt->bind_param('sssssii', $profileName, $profileName, $username, $about, $avatarPath, $completed, $userId);
            if ($stmt->execute()) {
                $stmt->close();
                $_SESSION['username'] = $username;
                header('Location: ' . ($returnToSettings ? 'settings.php?profile_saved=1' : 'index.php'));
                exit;
            }
            $stmt->close();
            $errors[] = 'Could not save your profile. Please try again.';
        }
    }
}

$profileNameValue = (string) ($_POST['profile_name'] ?? ($user['profile_name'] ?: $user['display_name'] ?: strtok((string) $user['email'], '@')));
$profileUsernameValue = (string) ($_POST['username'] ?? ($user['username'] ?? ''));
$profileAboutValue = (string) ($_POST['about'] ?? ($user['about'] ?? ''));
$profileAvatar = (string) ($user['avatar'] ?? '');
$profileInitial = strtoupper(mb_substr($profileNameValue ?: $user['email'] ?: 'U', 0, 1));
$profileErrors = $errors;
$profileEditorTitle = $isEdit ? 'Edit your profile' : 'Complete your profile';
$profileEditorDescription = $isEdit ? 'Update your profile details anytime.' : 'Complete your profile once to start chatting.';
$profileEditorSaveLabel = $isEdit ? 'Save changes' : 'Save profile';
$profileFormAction = 'set_profile.php?edit=1' . ($returnToSettings ? '&return=settings' : '');
$profileFormShowBack = !$returnToSettings;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $isEdit ? 'Profile - WeChats' : 'Complete profile - WeChats'; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="components/profile_editor.css">
  <style>
    :root{--page:#eef3f7;--ink:#17212b;--muted:#62717e;--line:#dfe7ee;--accent:#00a884;--accent-dark:#087963}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:linear-gradient(125deg,rgba(0,168,132,.14),transparent 34%),linear-gradient(245deg,rgba(47,111,237,.12),transparent 42%),var(--page);font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--ink)}
    .profile-app{min-height:calc(100vh - 20px);display:grid;grid-template-columns:260px minmax(0,1fr);border-radius:14px;overflow:hidden}
    .rail{padding:24px 18px;background:rgba(255,255,255,.72);border-right:1px solid rgba(223,231,238,.78);display:flex;flex-direction:column;justify-content:space-between;backdrop-filter:blur(14px)}
    .brand{display:flex;align-items:center;gap:12px;margin:0 0 28px;font-size:18px;font-weight:900}.brand-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:14px;background:var(--accent);color:#fff}
    .nav{display:grid;gap:8px}.nav a{display:flex;align-items:center;gap:12px;padding:12px 13px;border:1px solid transparent;border-radius:14px;color:#34424f;text-decoration:none;font-weight:800}.nav a:hover,.nav a.active{background:#fff;border-color:var(--line);color:var(--accent-dark)}.nav .logout{color:#c93b36}
    .content{min-width:0;padding:0;display:flex;align-items:center;justify-content:center}.profile-editor-card{max-width:760px}
    @media(max-width:820px){body{padding:8px}.profile-app{min-height:calc(100vh - 16px);grid-template-columns:1fr}.rail{padding:16px}.nav{grid-template-columns:repeat(3,1fr)}.nav a{justify-content:center;font-size:13px}.content{padding:0}}
    @media(max-width:560px){.nav a{gap:6px;padding:10px 6px;font-size:12px}}
  </style>
</head>
<body>
<main class="profile-app">
  <aside class="rail">
    <div>
      <div class="brand"><div class="brand-icon"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i></div><span>WeChats</span></div>
      <nav class="nav" aria-label="Main navigation">
        <a href="index.php"><i class="fa-solid fa-message" aria-hidden="true"></i> Chats</a>
        <a href="settings.php"><i class="fa-solid fa-gear" aria-hidden="true"></i> Settings</a>
        <a class="active" href="set_profile.php?edit=1" aria-current="page"><i class="fa-solid fa-user" aria-hidden="true"></i> Profile</a>
      </nav>
    </div>
    <nav class="nav" aria-label="Account"><a class="logout" href="logout.php"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Logout</a></nav>
  </aside>
  <section class="content">
    <?php require __DIR__ . '/components/profile_editor.php'; ?>
  </section>
</main>
</body>
</html>
