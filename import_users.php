<?php
require_once 'config.php';
$user = checkPermission('unit_admin');
$is_super = in_array($user['role'], ['super_admin','inspector']);
$unit_id = $user['unit_id'];

if ($is_super) {
    $allowed_unit_ids = $pdo->query("SELECT id FROM unit")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $allowed_unit_ids = getSubUnitIds($unit_id, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['userfile'])) {
    $file = $_FILES['userfile'];
    if ($file['error'] != 0) {
        die("文件上传错误，错误码：" . $file['error']);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) != 'csv') {
        die("只支持 CSV 文件格式");
    }
    
    // 读取文件内容，自动处理编码
    $content = file_get_contents($file['tmp_name']);
    // 去除 UTF-8 BOM
    if (substr($content, 0, 3) == "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    // 检测编码，增加错误处理
    $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'ASCII'], true);
    if ($encoding === false) {
        // 无法检测时，假设为 GBK
        $encoding = 'GBK';
    }
    if ($encoding != 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    // 写入临时文件
    $tempFile = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($tempFile, $content);
    $handle = fopen($tempFile, 'r');
    if (!$handle) {
        die("无法打开临时文件");
    }
    
    $header = fgetcsv($handle); // 跳过标题行
    $success = 0;
    $fail = 0;
    $errors = [];
    
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 7 || empty(trim($row[0]))) continue;
        
        list($username, $real_name, $plain_pwd, $unit_name, $position, $role, $status) = $row;
        $username = trim($username);
        $real_name = trim($real_name);
        $plain_pwd = trim($plain_pwd);
        $unit_name = trim($unit_name);
        $position = trim($position);
        $role = trim($role);
        $status = trim($status);
        
        if (empty($username) || empty($real_name) || empty($plain_pwd) || empty($unit_name) || empty($role)) {
            $fail++;
            $errors[] = "用户名、姓名、密码、单位、权限不能为空：$username";
            continue;
        }
        
        $stmt = $pdo->prepare("SELECT id FROM unit WHERE name = ?");
        $stmt->execute([$unit_name]);
        $unit_id_db = $stmt->fetchColumn();
        if (!$unit_id_db) {
            $fail++;
            $errors[] = "单位不存在：$unit_name";
            continue;
        }
        if (!in_array($unit_id_db, $allowed_unit_ids)) {
            $fail++;
            $errors[] = "无权限导入到单位：$unit_name";
            continue;
        }
        
        $valid_roles = ['super_admin', 'inspector', 'unit_admin', 'operator'];
        if (!in_array($role, $valid_roles)) {
            $fail++;
            $errors[] = "无效的角色：$role，允许值：super_admin, inspector, unit_admin, operator";
            continue;
        }
        if (!$is_super && in_array($role, ['super_admin','inspector'])) {
            $fail++;
            $errors[] = "您没有权限创建管理员/监察员用户：$username";
            continue;
        }
        
        if (!in_array($status, ['启用', '停用'])) {
            $status = '启用';
        }
        
        $check = $pdo->prepare("SELECT id FROM user WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $fail++;
            $errors[] = "用户名已存在：$username";
            continue;
        }
        
        $password = hash('sha256', $plain_pwd);
        $stmt2 = $pdo->prepare("INSERT INTO user (username, password, real_name, unit_id, position, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt2->execute([$username, $password, $real_name, $unit_id_db, $position, $role, $status])) {
            $success++;
        } else {
            $fail++;
            $errors[] = "数据库插入失败：$username";
        }
    }
    fclose($handle);
    unlink($tempFile);
    logOperation("批量导入用户，成功 $success 条，失败 $fail 条");
    
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>导入结果</title><link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css'></head><body class='container mt-5'>";
    echo "<h2>批量导入结果</h2>";
    echo "<div class='alert alert-success'>成功导入：$success 条</div>";
    if ($fail > 0) {
        echo "<div class='alert alert-danger'>失败：$fail 条</div><ul>";
        foreach ($errors as $err) echo "<li>$err</li>";
        echo "</ul>";
    }
    echo "<a href='personnel.php' class='btn btn-primary'>返回人员管理</a></body></html>";
    exit;
} else {
    header("Location: personnel.php");
    exit;
}
?>