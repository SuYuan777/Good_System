<?php
session_start();
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '123456';      // 请修改为您的数据库密码
$db_name = 'good_system1';   // 已修改

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 获取当前用户
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 权限检查
// 角色说明：
//   super_admin —— 系统管理员，拥有全部权限
//   inspector   —— 监查员，与系统管理员可见菜单一致，但对【品类管理】【物资管理】只读
//   unit_admin  —— 单位管理员
//   operator    —— 普通操作员
function checkPermission($required_role = null) {
    $user = getCurrentUser();
    if (!$user || $user['status'] != '启用') {
        header("Location: login.php");
        exit;
    }
    if ($required_role) {
        $allowed = false;
        // 监查员视为与 super_admin 同级的“查看”权限，可进入任何受 super_admin 保护的页面
        if ($required_role == 'super_admin' && in_array($user['role'], ['super_admin','inspector'])) $allowed = true;
        if ($required_role == 'unit_admin' && in_array($user['role'], ['super_admin','inspector','unit_admin'])) $allowed = true;
        if ($required_role == 'operator' && in_array($user['role'], ['super_admin','inspector','unit_admin','operator'])) $allowed = true;
        if (!$allowed) {
            die("权限不足");
        }
    }
    return $user;
}

// 是否拥有对“品类管理 / 物资管理”页面的写操作权限
// 监查员仅可查看，不可新增/编辑/删除/导入
function canManageMaterial($user = null) {
    if ($user === null) $user = getCurrentUser();
    if (!$user) return false;
    return $user['role'] !== 'inspector';
}

// 获取单位及其所有下级单位ID
function getSubUnitIds($unit_id, $include_self = true) {
    global $pdo;
    $ids = $include_self ? [$unit_id] : [];
    $stmt = $pdo->prepare("SELECT id FROM unit WHERE parent_id = ?");
    $stmt->execute([$unit_id]);
    while ($row = $stmt->fetch()) {
        $ids = array_merge($ids, getSubUnitIds($row['id'], true));
    }
    return $ids;
}

// 记录操作日志
function logOperation($action) {
    $user = getCurrentUser();
    if (!$user) return;
    global $pdo;
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
    $ip = trim(explode(',', $ip)[0]);
    if ($ip === '::1') $ip = '127.0.0.1';
    $stmt = $pdo->prepare("INSERT INTO operation_log (user_id, username, action, ip, log_time) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user['id'], $user['username'], $action, $ip]);
}

// 导出Excel通用函数（使用CSV格式）
function exportToExcel($filename, $headers, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$filename}.csv");
    $output = fopen('php://output', 'w');
    // 添加 BOM 以支持 Excel 正确识别 UTF-8
    fprintf($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers);
    foreach ($data as $row) {
        // 只输出数值，不输出键名（避免重复）
        fputcsv($output, array_values($row));
    }
    fclose($output);
    exit;
}
?>