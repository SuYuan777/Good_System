<?php
require_once 'config.php';
$user = checkPermission('operator');
$allowed_unit_ids = getSubUnitIds($user['unit_id'], true);

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>导入调试</title></head><body>";
echo "<h2>物资导入调试信息</h2>";

echo "<p><strong>当前用户：</strong> " . htmlspecialchars($user['username']) . " (角色: {$user['role']})</p>";
echo "<p><strong>允许管理的单位ID：</strong> " . implode(', ', $allowed_unit_ids) . "</p>";

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_FILES['material_file'])) {
    echo "<p>请通过表单上传 CSV 文件。</p>";
    echo '<form method="post" enctype="multipart/form-data"><input type="file" name="material_file" accept=".csv" required><button type="submit">上传</button></form>';
    echo "</body></html>";
    exit;
}

$file = $_FILES['material_file'];
if ($file['error'] != 0) die("文件上传错误，错误码：" . $file['error']);
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (strtolower($ext) != 'csv') die("请上传 CSV 文件");

$content = file_get_contents($file['tmp_name']);
echo "<p>原始文件大小: " . strlen($content) . " 字节</p>";

if (substr($content, 0, 3) == "\xEF\xBB\xBF") {
    $content = substr($content, 3);
    echo "<p>已去除 UTF-8 BOM</p>";
}

$encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'ASCII'], true);
if ($encoding === false) $encoding = 'GBK';
echo "<p>检测到编码: $encoding</p>";
if ($encoding != 'UTF-8') {
    $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    echo "<p>已转换为 UTF-8</p>";
}

$temp = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($temp, $content);
$handle = fopen($temp, 'r');
if (!$handle) die("无法打开临时文件");

$lineNum = 0;
$header = fgetcsv($handle);
echo "<p>标题行: " . implode(' | ', $header) . "</p>";

$success = 0;
$fail = 0;
$errors = [];

while (($row = fgetcsv($handle)) !== false) {
    $lineNum++;
    echo "<hr><h3>第 {$lineNum} 行</h3>";
    echo "<p>原始字段数: " . count($row) . "</p>";
    echo "<p>字段内容: " . implode(' | ', array_map('htmlspecialchars', $row)) . "</p>";

    if (count($row) < 9) {
        $fail++;
        $errors[] = "第 {$lineNum} 行：字段数不足9列（实际" . count($row) . "列）";
        echo "<p style='color:red'>错误：字段数不足9列</p>";
        continue;
    }
    list($code, $name, $category, $unit_name, $storehouse_name, $shelf, $location, $quantity, $status) = $row;
    $code = trim($code);
    $name = trim($name);
    $category = trim($category);
    $unit_name = trim($unit_name);
    $storehouse_name = trim($storehouse_name);
    $shelf = trim($shelf);
    $location = trim($location);
    $quantity = intval($quantity);
    $status = trim($status);

    echo "<p>解析后: 编码={$code}, 名称={$name}, 品类={$category}, 单位={$unit_name}, 库房={$storehouse_name}, 货架={$shelf}, 位置={$location}, 数量={$quantity}, 状态={$status}</p>";

    if (empty($code) || empty($name) || empty($category) || empty($unit_name) || $quantity <= 0) {
        $fail++;
        $errors[] = "第 {$lineNum} 行：必填字段缺失";
        echo "<p style='color:red'>错误：必填字段缺失</p>";
        continue;
    }

    // 查询单位
    $stmt = $pdo->prepare("SELECT id FROM unit WHERE name = ?");
    $stmt->execute([$unit_name]);
    $unit_id_db = $stmt->fetchColumn();
    echo "<p>查询单位 '{$unit_name}' 结果: " . ($unit_id_db ? "ID={$unit_id_db}" : "不存在") . "</p>";
    
    if (!$unit_id_db) {
        $fail++;
        $errors[] = "第 {$lineNum} 行：单位不存在 - {$unit_name}";
        echo "<p style='color:red'>错误：单位不存在</p>";
        continue;
    }
    if (!in_array($unit_id_db, $allowed_unit_ids)) {
        $fail++;
        $errors[] = "第 {$lineNum} 行：无权限操作单位 {$unit_name} (ID={$unit_id_db})";
        echo "<p style='color:red'>错误：无权限操作该单位（您的允许ID列表: " . implode(',', $allowed_unit_ids) . "）</p>";
        continue;
    }

    // 查询库房（可选）—— 修复点：确保 $storehouse_id 为 null 或整数
    $storehouse_id = null;
    if (!empty($storehouse_name)) {
        $stmt = $pdo->prepare("SELECT id FROM storehouse WHERE name = ? AND unit_id = ?");
        $stmt->execute([$storehouse_name, $unit_id_db]);
        $result = $stmt->fetchColumn();
        if ($result !== false && $result !== null) {
            $storehouse_id = (int)$result;
        }
        echo "<p>查询库房 '{$storehouse_name}' (单位ID {$unit_id_db}) 结果: " . ($storehouse_id ? "ID={$storehouse_id}" : "不存在，将设为NULL") . "</p>";
    }

    // 验证品类和状态
    $valid_categories = [];
$catStmt = $pdo->query("SELECT name FROM category WHERE status='启用'");
foreach($catStmt->fetchAll() as $row) {
    $valid_categories[] = $row['name'];
}
// 后面验证逻辑不变
if (!in_array($category, $valid_categories)) $category = '器材'; // 或者设置为默认第一个
    $valid_status = ['新品', '良好', '堪用', '损坏', '报废', '消耗'];
    if (!in_array($category, $valid_categories)) {
        echo "<p>品类 '{$category}' 无效，已改为 '器材'</p>";
        $category = '器材';
    }
    if (!in_array($status, $valid_status)) {
        echo "<p>状态 '{$status}' 无效，已改为 '良好'</p>";
        $status = '良好';
    }

    // 插入（明确指定参数类型）
    $stmt = $pdo->prepare("INSERT INTO material (code, name, category, unit_id, storehouse_id, shelf, location, quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    // 将 storehouse_id 显式转换为整数或 null
    $storehouse_id_param = is_numeric($storehouse_id) ? (int)$storehouse_id : null;
    if ($stmt->execute([$code, $name, $category, $unit_id_db, $storehouse_id_param, $shelf, $location, $quantity, $status])) {
        $success++;
        echo "<p style='color:green'>成功插入物资: {$name}</p>";
    } else {
        $fail++;
        $errInfo = $stmt->errorInfo();
        $errors[] = "第 {$lineNum} 行：数据库插入失败 - " . $errInfo[2];
        echo "<p style='color:red'>数据库插入失败: " . htmlspecialchars($errInfo[2]) . "</p>";
    }
}
fclose($handle);
unlink($temp);
logOperation("批量导入物资，成功 $success 条，失败 $fail 条");

echo "<hr><h3>汇总</h3>";
echo "<p>成功导入: $success 条</p>";
echo "<p>失败: $fail 条</p>";
if (!empty($errors)) {
    echo "<ul>";
    foreach ($errors as $err) echo "<li>$err</li>";
    echo "</ul>";
}
echo '<a href="material.php" class="btn btn-primary">返回物资管理</a>';
echo "</body></html>";
?>