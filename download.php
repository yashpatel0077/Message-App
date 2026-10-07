<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config.php';

function json_out($arr, int $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

function hasColumn(mysqli $conn, string $table, string $col): bool
{
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
    $st = $conn->prepare($sql);
    if (!$st)
        return false;
    $st->bind_param("ss", $table, $col);
    $st->execute();
    $res = $st->get_result();
    $ok = ($res && $res->num_rows > 0);
    $st->close();
    return $ok;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$user_id = (int) $_SESSION['user_id'];
$message_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$type = $_GET['type'] ?? 'dm'; // 'dm' or 'group'

if ($message_id <= 0) {
    http_response_code(400);
    die('Invalid request');
}

// Check if we're just getting file info (for preview)
$info_only = isset($_GET['info']) && $_GET['info'] === '1';

// Check which columns exist
$messages_has_attachment_path = hasColumn($conn, 'messages', 'attachment_path');
$messages_has_attachment_name = hasColumn($conn, 'messages', 'attachment_name');
$group_messages_has_attachment_path = hasColumn($conn, 'group_messages', 'attachment_path');

// Check permissions based on message type and get file info
$attachment_path = null;
$attachment_name = null;
$attachment_size = null;
$attachment_type = null;

if ($type === 'dm') {
    // Check if user is part of the DM conversation
    if ($messages_has_attachment_path) {
        $sql = "SELECT attachment_path, attachment_name, attachment_size, attachment_type, sender_id, receiver_id 
                FROM messages 
                WHERE id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))";
        $st = $conn->prepare($sql);
        if (!$st) {
            http_response_code(500);
            die('Database error');
        }
        $st->bind_param("iiiii", $message_id, $user_id, $user_id, $user_id, $user_id);
    } else {
        http_response_code(404);
        die('Attachment not found');
    }
} else {
    // Check if user is member of the group
    if ($group_messages_has_attachment_path) {
        $sql = "SELECT gm.attachment_path, gm.attachment_name, gm.attachment_size, gm.attachment_type 
                FROM group_messages gm
                JOIN group_members gmm ON gmm.group_id = gm.group_id
                WHERE gm.id = ? AND gmm.user_id = ?";
        $st = $conn->prepare($sql);
        if (!$st) {
            http_response_code(500);
            die('Database error');
        }
        $st->bind_param("ii", $message_id, $user_id);
    } else {
        http_response_code(404);
        die('Attachment not found');
    }
}

$st->execute();
$result = $st->get_result();
$row = $result->fetch_assoc();
$st->close();

if (!$row || empty($row['attachment_path'])) {
    http_response_code(404);
    die('File not found');
}

$attachment_path = $row['attachment_path'];
$attachment_name = $row['attachment_name'] ?? basename($attachment_path);
$attachment_size = $row['attachment_size'] ?? null;
$attachment_type = $row['attachment_type'] ?? null;

// If just requesting info (for preview), return JSON
if ($info_only) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'name' => $attachment_name,
        'size' => $attachment_size,
        'type' => $attachment_type,
        'path' => $attachment_path
    ]);
    exit;
}

$file_path = __DIR__ . '/' . $attachment_path;
if (!file_exists($file_path)) {
    http_response_code(404);
    die('File does not exist on server');
}

// Get file size if not in database
if (!$attachment_size) {
    $attachment_size = filesize($file_path);
}

// Determine MIME type
if ($attachment_type) {
    $mime_type = $attachment_type;
} else {
    // Try to detect MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);
}

// Get file extension for proper Content-Type
$extension = strtolower(pathinfo($attachment_name, PATHINFO_EXTENSION));

// Map extensions to proper MIME types for better browser handling
$mime_map = [
    // Images
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    
    // Documents
    'pdf' => 'application/pdf',
    'txt' => 'text/plain',
    'html' => 'text/html',
    'htm' => 'text/html',
    'css' => 'text/css',
    'js' => 'application/javascript',
    'json' => 'application/json',
    'xml' => 'application/xml',
    'md' => 'text/markdown',
    
    // Audio
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    'webm' => 'audio/webm',
    
    // Video
    'mp4' => 'video/mp4',
    'avi' => 'video/x-msvideo',
    'mov' => 'video/quicktime',
];

// Use mapped MIME type if available
if (isset($mime_map[$extension])) {
    $mime_type = $mime_map[$extension];
}

// Clear all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);

// For images, we can display inline in browser
$inline_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
if (in_array($mime_type, $inline_types) && !isset($_GET['download'])) {
    // Display inline (in browser)
    header('Content-Disposition: inline; filename="' . $attachment_name . '"');
} else {
    // Force download
    header('Content-Disposition: attachment; filename="' . $attachment_name . '"');
}

header('Content-Length: ' . $attachment_size);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Add security headers
header('X-Content-Type-Options: nosniff');

// Output file
readfile($file_path);
exit;
?>