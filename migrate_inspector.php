<?php
require_once 'config.php';
checkPermission('super_admin');

try {
    $pdo->exec("ALTER TABLE `user` MODIFY `role` ENUM('super_admin','inspector','unit_admin','operator') NOT NULL");
    echo "迁移成功：user.role 字段已新增 'inspector'（监查员）枚举值。";
} catch (PDOException $e) {
    echo "迁移失败：" . $e->getMessage();
}
?>
