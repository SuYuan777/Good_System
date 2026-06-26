<?php
require_once 'config.php';
$user = checkPermission('operator');
$user_unit_id = $user['unit_id'];
$allowed_unit_ids = getSubUnitIds($user_unit_id, true);
$allowed_placeholders = implode(',', array_fill(0, count($allowed_unit_ids), '?'));

$search_name    = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter  = isset($_GET['status']) ? $_GET['status'] : '';
$selected_unit  = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : $user_unit_id;

if (!in_array($selected_unit, $allowed_unit_ids)) $selected_unit = $user_unit_id;

$query_unit_ids    = getSubUnitIds($selected_unit, true);
$query_placeholders = implode(',', array_fill(0, count($query_unit_ids), '?'));

$sql = "SELECT m.*, u.name as unit_name, s.name as storehouse_name
        FROM material m
        LEFT JOIN unit u ON m.unit_id = u.id
        LEFT JOIN storehouse s ON m.storehouse_id = s.id
        WHERE m.unit_id IN ($query_placeholders)";
$params = $query_unit_ids;

if ($search_name)    { $sql .= " AND m.name LIKE ?";     $params[] = "%$search_name%"; }
if ($category_filter){ $sql .= " AND m.category = ?";    $params[] = $category_filter; }
if ($status_filter)  { $sql .= " AND m.status = ?";      $params[] = $status_filter; }
$sql .= " ORDER BY u.id, m.id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$units_stmt = $pdo->prepare("SELECT id, name FROM unit WHERE id IN ($allowed_placeholders)");
$units_stmt->execute($allowed_unit_ids);
$units_list = $units_stmt->fetchAll();

$category_list = $pdo->query("SELECT name FROM category WHERE status='启用' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

// 汇总统计
$total_qty = array_sum(array_column($materials, 'quantity'));

include 'includes/header.php';
?>

<h2>物资查询</h2>

<form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-auto">
        <select name="unit_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($units_list as $u): ?>
                <option value="<?=$u['id']?>" <?=$selected_unit==$u['id']?'selected':''?>><?=htmlspecialchars($u['name'])?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><input type="text" name="search_name" class="form-control" placeholder="物资名称" value="<?=htmlspecialchars($search_name)?>"></div>
    <div class="col-auto">
        <select name="category" class="form-select">
            <option value="">全部品类</option>
            <?php foreach ($category_list as $cat): ?>
                <option value="<?=$cat?>" <?=$category_filter==$cat?'selected':''?>><?=$cat?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="status" class="form-select">
            <option value="">全部状态</option>
            <?php foreach (['新品','良好','堪用','损坏','报废','消耗'] as $s): ?>
                <option value="<?=$s?>" <?=$status_filter==$s?'selected':''?>><?=$s?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">查询</button>
        <a href="material_query.php" class="btn btn-secondary">重置</a>
    </div>
    <div class="col-auto">
        <span class="text-info" style="font-size:0.9rem;">共 <?=count($materials)?> 条，总数量：<?=$total_qty?></span>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
            <tr><th>物资编码</th><th>物资名称</th><th>品类</th><th>所属单位</th><th>库房</th><th>货架</th><th>货架位置</th><th>数量</th><th>状态</th></tr>
        </thead>
        <tbody>
        <?php foreach ($materials as $m): ?>
            <tr>
                <td><?=htmlspecialchars($m['code'])?></td>
                <td><?=htmlspecialchars($m['name'])?></td>
                <td><?=$m['category']?></td>
                <td><?=htmlspecialchars($m['unit_name'])?></td>
                <td><?=htmlspecialchars($m['storehouse_name'])?></td>
                <td><?=htmlspecialchars($m['shelf'])?></td>
                <td><?=htmlspecialchars($m['location'])?></td>
                <td><?=$m['quantity']?></td>
                <td><?=$m['status']?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
    .table, .table tbody, .table tr, .table td, .table th {
        color: #f0f0f0 !important;
        background-color: transparent;
    }
    .table-striped > tbody > tr:nth-of-type(odd) > * {
        background-color: rgba(0, 80, 120, 0.2);
    }
</style>

<?php include 'includes/footer.php'; ?>
