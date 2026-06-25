<?php
require_once 'config.php';
checkPermission('super_admin');

try {
    $pdo->exec("ALTER TABLE material MODIFY COLUMN category VARCHAR(50) NOT NULL DEFAULT ''");
    echo "迁移成功：category 字段已从 ENUM 改为 VARCHAR(50)，现在可以使用任意品类名称。";
} catch (PDOException $e) {
    echo "迁移失败：" . $e->getMessage();
}
?>
