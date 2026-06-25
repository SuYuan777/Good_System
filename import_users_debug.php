<?php
require_once 'config.php';
$user = checkPermission('unit_admin');
$is_super = ($user['role'] == 'super_admin');
$unit_id = $user['unit_id'];

if ($is_super) {
    $allowed_unit_ids = $pdo->query("SELECT id FROM unit")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $allowed_unit_ids = getSubUnitIds($unit_id, true);
}

echo "<pre>";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['userfile'])) {
    $file = $_FILES['userfile'];
    echo "文件名: {$file['name']}\n";
    echo "文件大小: {$file['size']} 字节\n";
    echo "临时路径: {$file['tmp_name']}\n\n";

    // 读取原始内容前100字节
    $raw = file_get_contents($file['tmp_name']);
    echo "原始文件头字节（十六进制）: " . bin2hex(substr($raw, 0, 50)) . "\n\n";

    // 去除 BOM
    if (substr($raw, 0, 3) == "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
        echo "检测到 UTF-8 BOM，已去除。\n";
    }

    // 检测编码
    $encoding = mb_detect_encoding($raw, ['UTF-8', 'GBK', 'GB2312', 'ASCII'], true);
    if ($encoding === false) $encoding = 'GBK';
    echo "检测到编码: $encoding\n";

    if ($encoding != 'UTF-8') {
        $raw = mb_convert_encoding($raw, 'UTF-8', $encoding);
        echo "已转换为 UTF-8\n";
    }

    // 保存到临时文件
    $temp = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($temp, $raw);
    $handle = fopen($temp, 'r');
    if (!$handle) die("无法打开临时文件");

    $lineNum = 0;
    $success = 0;
    $fail = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $lineNum++;
        echo "\n--- 第 $lineNum 行 ---\n";
        echo "原始字段数: " . count($row) . "\n";
        echo "字段内容: ";
        print_r($row);

        if ($lineNum == 1) {
            echo "跳过标题行\n";
            continue;
        }

        if (count($row) < 7) {
            echo "跳过：字段数不足7个\n";
            $fail++;
            continue;
        }

        list($username, $real_name, $plain_pwd, $unit_name, $position, $role, $status) = $row;
        $username = trim($username);
        $real_name = trim($real_name);
        $plain_pwd = trim($plain_pwd);
        $unit_name = trim($unit_name);
        $position = trim($position);
        $role = trim($role);
        $status = trim($status);

        if (empty($username) || empty($real_name) || empty($plain_pwd) || empty($unit_name) || empty($role)) {
            echo "跳过：必填字段为空 (用户名={$username}, 单位={$unit_name}, 权限={$role})\n";
            $fail++;
            continue;
        }

        // 查找单位
        $stmt = $pdo->prepare("SELECT id FROM unit WHERE name = ?");
        $stmt->execute([$unit_name]);
        $unit_id_db = $stmt->fetchColumn();
        if (!$unit_id_db) {
            echo "跳过：单位不存在 -> $unit_name\n";
            $fail++;
            continue;
        }
        if (!in_array($unit_id_db, $allowed_unit_ids)) {
            echo "跳过：无权限导入到单位 $unit_name (ID={$unit_id_db})\n";
            $fail++;
            continue;
        }

        // 验证角色
        $valid_roles = ['super_admin', 'unit_admin', 'operator'];
        if (!in_array($role, $valid_roles)) {
            echo "跳过：无效角色 $role\n";
            $fail++;
            continue;
        }
        if (!$is_super && $role == 'super_admin') {
            echo "跳过：无权创建超级管理员 $username\n";
            $fail++;
            continue;
        }

        if (!in_array($status, ['启用', '停用'])) $status = '启用';

        $check = $pdo->prepare("SELECT id FROM user WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            echo "跳过：用户名已存在 $username\n";
            $fail++;
            continue;
        }

        $password = hash('sha256', $plain_pwd);
        $stmt2 = $pdo->prepare("INSERT INTO user (username, password, real_name, unit_id, position, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt2->execute([$username, $password, $real_name, $unit_id_db, $position, $role, $status])) {
            echo "成功插入用户: $username\n";
            $success++;
        } else {
            echo "插入失败: $username\n";
            $fail++;
        }
    }
    fclose($handle);
    unlink($temp);
    echo "\n========== 汇总 ==========\n";
    echo "成功导入: $success 条\n";
    echo "失败: $fail 条\n";
} else {
    echo "请通过表单上传 CSV 文件。\n";
}
echo "</pre>";
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>调试导入</title></head>
<body>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="userfile" accept=".csv" required>
    <button type="submit">上传并调试</button>
</form>
</body>
</html>