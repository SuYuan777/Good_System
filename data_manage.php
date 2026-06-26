<?php
require_once 'config.php';
$user = checkPermission('operator');
$unit_id = $user['unit_id'];
$allowed_unit_ids = getSubUnitIds($unit_id, true);
$allowed_placeholders = implode(',', array_fill(0, count($allowed_unit_ids), '?'));

$message = '';

// 导出处理（按单位+库房）
if (isset($_GET['export_storehouse_action'])) {
    $export_unit = intval($_GET['export_unit']);
    $export_storehouse = intval($_GET['export_storehouse']);
    if (!in_array($export_unit, $allowed_unit_ids)) die("无权限导出该单位数据");

    // 获取单位名称
    $unit_stmt = $pdo->prepare("SELECT name FROM unit WHERE id = ?");
    $unit_stmt->execute([$export_unit]);
    $unit_name = $unit_stmt->fetchColumn() ?: "未知单位";

    // 获取库房名称
    $storehouse_name = "全部库房";
    if ($export_storehouse > 0) {
        $sh_stmt = $pdo->prepare("SELECT name FROM storehouse WHERE id = ?");
        $sh_stmt->execute([$export_storehouse]);
        $storehouse_name = $sh_stmt->fetchColumn() ?: "未知库房";
    }

    $sql = "SELECT m.code, m.name, m.category, u.name as unit_name, s.name as storehouse_name, m.shelf, m.location, m.quantity, m.status
            FROM material m
            LEFT JOIN unit u ON m.unit_id = u.id
            LEFT JOIN storehouse s ON m.storehouse_id = s.id
            WHERE m.unit_id = ?";
    $params = [$export_unit];
    if ($export_storehouse > 0) {
        $sql .= " AND m.storehouse_id = ?";
        $params[] = $export_storehouse;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['物资编码', '物资名称', '品类', '所属单位', '库房', '货架', '货架位置', '数量', '状态'];
    exportToExcel("物资明细_{$unit_name}_{$storehouse_name}", $headers, $data);
    exit;
}

// 导出处理（按单位+品类）
if (isset($_GET['export_category_action'])) {
    $export_unit = intval($_GET['export_unit']);
    $export_category = $_GET['export_category'];
    if (!in_array($export_unit, $allowed_unit_ids)) die("无权限导出该单位数据");
    $valid_cats = ['供应', '营房', '运输', '通信', '器材'];
    if (!in_array($export_category, $valid_cats)) die("无效的品类");

    // 获取单位名称
    $unit_stmt = $pdo->prepare("SELECT name FROM unit WHERE id = ?");
    $unit_stmt->execute([$export_unit]);
    $unit_name = $unit_stmt->fetchColumn() ?: "未知单位";

    $sql = "SELECT m.code, m.name, m.category, u.name as unit_name, s.name as storehouse_name, m.shelf, m.location, m.quantity, m.status
            FROM material m
            LEFT JOIN unit u ON m.unit_id = u.id
            LEFT JOIN storehouse s ON m.storehouse_id = s.id
            WHERE m.unit_id = ? AND m.category = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$export_unit, $export_category]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $headers = ['物资编码', '物资名称', '品类', '所属单位', '库房', '货架', '货架位置', '数量', '状态'];
    exportToExcel("物资明细_{$unit_name}_品类{$export_category}", $headers, $data);
    exit;
}

include 'includes/header.php';
?>

<h2>数据导出</h2>
<?php if($message) echo "<div class='alert alert-info'>$message</div>"; ?>

<div class="row">
    <!-- 按单位+库房导出 -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">按单位 + 库房导出物资明细</div>
            <div class="card-body">
                <form method="get" action="" id="formStorehouse">
                    <input type="hidden" name="export_storehouse_action" value="1">
                    <div class="mb-3">
                        <label class="form-label">选择单位</label>
                        <select name="export_unit" id="export_unit_storehouse" class="form-select" required>
                            <option value="">请选择单位</option>
                            <?php
                            $units = $pdo->prepare("SELECT id, name FROM unit WHERE id IN ($allowed_placeholders)");
                            $units->execute($allowed_unit_ids);
                            foreach ($units->fetchAll() as $unit): ?>
                                <option value="<?=$unit['id']?>"><?=htmlspecialchars($unit['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">选择库房</label>
                        <select name="export_storehouse" id="export_storehouse_select" class="form-select">
                            <option value="0">全部库房</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success">导出 Excel (CSV)</button>
                </form>
            </div>
        </div>
    </div>

    <!-- 按单位+品类导出 -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">按单位 + 品类导出物资明细</div>
            <div class="card-body">
                <form method="get" action="">
                    <input type="hidden" name="export_category_action" value="1">
                    <div class="mb-3">
                        <label class="form-label">选择单位</label>
                        <select name="export_unit" id="export_unit_category" class="form-select" required>
                            <option value="">请选择单位</option>
                            <?php
                            $units->execute($allowed_unit_ids);
                            foreach ($units->fetchAll() as $unit): ?>
                                <option value="<?=$unit['id']?>"><?=htmlspecialchars($unit['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">选择品类</label>
                        <select name="export_category" class="form-select" required>
                            <option value="">请选择品类</option>
                            <option value="供应">供应</option>
                            <option value="营房">营房</option>
                            <option value="运输">运输</option>
                            <option value="通信">通信</option>
                            <option value="器材">器材</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success">导出 Excel (CSV)</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 数据库备份功能（仅超级管理员 / 监查员） -->
<?php if (in_array($user['role'], ['super_admin','inspector'])): ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">数据库备份</div>
            <div class="card-body">
                <form method="post" action="backup.php">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('确认备份数据库吗？')">立即备份 (SQL)</button>
                </form>
                <small class="text-muted">备份文件将保存在 exports/ 目录下</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    // 单位变化时动态加载库房（用于第一个表单）
    $('#export_unit_storehouse').change(function(){
        var unitId = $(this).val();
        var $storehouseSelect = $('#export_storehouse_select');
        $storehouseSelect.html('<option value="0">加载中...</option>');
        if (unitId === '') {
            $storehouseSelect.html('<option value="0">全部库房</option>');
            return;
        }
        $.getJSON('get_storehouses.php?unit_id=' + unitId, function(data){
            var options = '<option value="0">全部库房</option>';
            $.each(data, function(i, sh){
                options += '<option value="' + sh.id + '">' + sh.name + '</option>';
            });
            $storehouseSelect.html(options);
        }).fail(function(){
            $storehouseSelect.html('<option value="0">加载失败，请重试</option>');
        });
    });
});
</script>

<style>
    /* 确保任何表格文字颜色清晰 */
    .table, .table tbody, .table tr, .table td, .table th {
        color: #f0f0f0 !important;
        background-color: transparent;
    }
    .table-striped > tbody > tr:nth-of-type(odd) > * {
        background-color: rgba(0, 80, 120, 0.2);
    }
    .table a, .table .btn {
        color: #bbddff;
    }
    .table a:hover, .table .btn:hover {
        color: #ffffff;
    }
    .badge, .alert, .card-header {
        color: #fff;
    }
</style>

<?php include 'includes/footer.php'; ?>