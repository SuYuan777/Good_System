<?php
require_once 'config.php';
$user = checkPermission('super_admin');
$backup_file = 'exports/backup_' . date('Ymd_His') . '.sql';
$command = "mysqldump -h {$db_host} -u {$db_user} -p{$db_pass} {$db_name} > {$backup_file}";
system($command, $output);
if (file_exists($backup_file)) {
    logOperation("数据库备份: {$backup_file}");
    echo "<script>alert('备份成功，文件: {$backup_file}'); window.location.href='data_manage.php';</script>";
} else {
    echo "<script>alert('备份失败，请检查 mysqldump 路径或权限'); window.location.href='data_manage.php';</script>";
}
?>