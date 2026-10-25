<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = isset($_GET['profile_saved']) ? 'Profile updated.' : '';

function esc($value) { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function hasColumn(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $found = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $found;
}

if (!hasColumn($conn, 'users', 'show_online_status')) {
    $conn->query('ALTER TABLE users ADD COLUMN show_online_status TINYINT(1) NOT NULL DEFAULT 1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visible = isset($_POST['show_online_status']) ? 1 : 0;
    $stmt = $conn->prepare('UPDATE users SET show_online_status=? WHERE id=?');
    if ($stmt) {
        $stmt->bind_param('ii', $visible, $userId);
        if ($stmt->execute()) $success = 'Privacy settings saved.';
        else $errors[] = 'Could not save your settings.';
        $stmt->close();
    } else {
        $errors[] = 'Could not save your settings.';
    }
}

$stmt = $conn->prepare('SELECT email, show_online_status FROM users WHERE id=? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Settings - WeChats</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :root{--bg:#eef3f7;--ink:#17212b;--muted:#62717e;--line:#dfe7ee;--accent:#00a884;--accent-dark:#087963;--soft:#f6f9fb}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(ellipse at 8% 8%,rgba(0,168,132,.12),transparent 34%),radial-gradient(ellipse at 92% 90%,rgba(47,111,237,.09),transparent 38%),var(--bg);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink)}
    .settings-app{min-height:100vh;display:grid;grid-template-columns:260px 1fr}.rail{background:rgba(255,255,255,.76);border-right:1px solid rgba(223,231,238,.8);padding:24px 18px;display:flex;flex-direction:column;justify-content:space-between;backdrop-filter:blur(14px)}
    .brand{display:flex;align-items:center;gap:12px;font-weight:900;font-size:18px;margin-bottom:28px}.brand-icon{width:42px;height:42px;border-radius:14px;background:var(--accent);color:#fff;display:grid;place-items:center}.nav{display:grid;gap:8px}.nav a{display:flex;align-items:center;gap:12px;padding:12px 13px;border-radius:14px;text-decoration:none;color:#34424f;font-weight:800;border:1px solid transparent}.nav a:hover,.nav a.active{background:#fff;border-color:var(--line);color:var(--accent-dark)}.logout{color:#c93b36!important}
    .content{padding:34px;display:flex;align-items:center;justify-content:center}.card{width:min(760px,100%);background:rgba(255,255,255,.94);border:1px solid #fff;border-radius:24px;box-shadow:0 24px 70px rgba(23,33,43,.12);overflow:hidden}.card-head{padding:28px 30px;border-bottom:1px solid var(--line)}h1{font-size:28px;margin:0 0 6px}.subtitle{margin:0;color:var(--muted);font-size:14px}.section{padding:26px 30px}.section h2{font-size:16px;margin:0 0 5px}.section-desc{margin:0 0 18px;color:var(--muted);font-size:14px;line-height:1.5}.setting-row{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px;border:1px solid var(--line);border-radius:16px;background:var(--soft)}.setting-title{font-size:14px;font-weight:800}.setting-help{font-size:13px;color:var(--muted);line-height:1.45;margin-top:5px}.switch{position:relative;width:48px;height:28px;flex:0 0 auto}.switch input{position:absolute;opacity:0;width:1px;height:1px}.switch-track{position:absolute;inset:0;border-radius:99px;background:#bac5cc;cursor:pointer;transition:.2s}.switch-track:after{content:"";position:absolute;width:22px;height:22px;top:3px;left:3px;border-radius:50%;background:#fff;box-shadow:0 1px 4px #0002;transition:.2s}.switch input:checked+.switch-track{background:var(--accent)}.switch input:checked+.switch-track:after{transform:translateX(20px)}.switch input:focus-visible+.switch-track{outline:3px solid #00a88444;outline-offset:2px}
    .actions{padding:0 30px 28px;display:flex;align-items:center;justify-content:space-between;gap:12px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:12px 16px;border-radius:13px;text-decoration:none;font:inherit;font-size:14px;font-weight:800;cursor:pointer}.btn-back{color:#34424f;background:#f3f6f8;border:1px solid var(--line)}.btn-save{border:0;background:var(--accent);color:#fff}.btn-save:hover{background:var(--accent-dark)}.notice{margin:18px 30px 0;padding:12px 14px;border-radius:12px;font-size:14px}.notice-success{color:#087963;background:#e8f7f3}.notice-error{color:#a72f2c;background:#fff0ef}
    @media(max-width:820px){.settings-app{grid-template-columns:1fr}.rail{padding:16px}.nav{grid-template-columns:repeat(3,1fr)}.nav a{justify-content:center;font-size:13px}.content{padding:16px}.card-head,.section{padding:22px}.actions{padding:0 22px 22px}}
    @media(max-width:520px){.nav a{gap:6px;padding:10px 6px;font-size:12px}.actions{align-items:stretch;flex-direction:column-reverse}.btn{width:100%}.setting-row{padding:14px}}
  </style>
</head>
<body>
<main class="settings-app">
  <aside class="rail">
    <div>
      <div class="brand"><div class="brand-icon"><i class="fa-solid fa-comment-dots"></i></div><span>WeChats</span></div>
      <nav class="nav" aria-label="Settings navigation">
        <a href="index.php"><i class="fa-solid fa-message"></i> Chats</a>
        <a href="settings.php" class="active"><i class="fa-solid fa-gear"></i> Settings</a>
        <a href="set_profile.php?edit=1"><i class="fa-solid fa-user"></i> Profile</a>
      </nav>
    </div>
    <nav class="nav" aria-label="Account"><a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></nav>
  </aside>
  <section class="content">
    <form class="card" method="post">
      <header class="card-head"><h1>Settings</h1><p class="subtitle"><?php echo esc($user['email']); ?></p></header>
      <?php if ($success): ?><div class="notice notice-success"><?php echo esc($success); ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="notice notice-error"><?php echo esc($error); ?></div><?php endforeach; ?>
      <section class="section">
        <h2>Privacy</h2>
        <p class="section-desc">Choose what other people can see while you use WeChats.</p>
        <div class="setting-row">
          <div><div class="setting-title">Show my online status</div><div class="setting-help">When turned off, other people will see you as offline.</div></div>
          <label class="switch" aria-label="Show my online status"><input type="checkbox" name="show_online_status" value="1" <?php echo !empty($user['show_online_status']) ? 'checked' : ''; ?>><span class="switch-track"></span></label>
        </div>
      </section>
      <div class="actions"><a class="btn btn-back" href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to chats</a><button class="btn btn-save" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save settings</button></div>
    </form>
  </section>
</main>
</body>
</html>
