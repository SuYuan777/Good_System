<?php
require_once 'config.php';
$user = checkPermission('operator');
$unit_id = $user['unit_id'];
$allowed_unit_ids = getSubUnitIds($unit_id, true);
$allowed_placeholders = implode(',', array_fill(0, count($allowed_unit_ids), '?'));

$message = '';

// 处理新增物资
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_material'])) {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $unit_id = intval($_POST['unit_id']);
    $storehouse_id = intval($_POST['storehouse_id']) ?: null;
    $shelf = trim($_POST['shelf']);
    $location = trim($_POST['location']);
    $quantity = intval($_POST['quantity']);
    $status = $_POST['status'];
    $stmt = $pdo->prepare("INSERT INTO material (code, name, category, unit_id, storehouse_id, shelf, location, quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code, $name, $category, $unit_id, $storehouse_id, $shelf, $location, $quantity, $status]);
    logOperation("新增物资: $name");
    $message = "物资添加成功";
}

// 处理编辑物资
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_material'])) {
    $id = intval($_POST['id']);
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $unit_id = intval($_POST['unit_id']);
    $storehouse_id = intval($_POST['storehouse_id']) ?: null;
    $shelf = trim($_POST['shelf']);
    $location = trim($_POST['location']);
    $quantity = intval($_POST['quantity']);
    $status = $_POST['status'];
    // 检查权限
    $check = $pdo->prepare("SELECT unit_id FROM material WHERE id=?");
    $check->execute([$id]);
    $mat_unit = $check->fetchColumn();
    if (!$mat_unit || !in_array($mat_unit, $allowed_unit_ids)) {
        die("无权限编辑此物资");
    }
    $stmt = $pdo->prepare("UPDATE material SET code=?, name=?, category=?, unit_id=?, storehouse_id=?, shelf=?, location=?, quantity=?, status=? WHERE id=?");
    $stmt->execute([$code, $name, $category, $unit_id, $storehouse_id, $shelf, $location, $quantity, $status, $id]);
    logOperation("编辑物资 ID: $id");
    $message = "物资编辑成功";
}

// 处理删除物资
if (isset($_GET['del_material'])) {
    $id = intval($_GET['del_material']);
    $stmt = $pdo->prepare("DELETE FROM material WHERE id=? AND unit_id IN ($allowed_placeholders)");
    $stmt->execute(array_merge([$id], $allowed_unit_ids));
    logOperation("删除物资 ID: $id");
    header("Location: material.php");
    exit;
}

// 获取筛选参数
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$unit_filter = isset($_GET['unit']) ? intval($_GET['unit']) : 0;
$storehouse_filter = isset($_GET['storehouse']) ? intval($_GET['storehouse']) : 0;

// 构建查询
$sql = "SELECT m.*, u.name as unit_name, s.name as storehouse_name FROM material m 
        LEFT JOIN unit u ON m.unit_id = u.id 
        LEFT JOIN storehouse s ON m.storehouse_id = s.id 
        WHERE m.unit_id IN ($allowed_placeholders)";
$params = $allowed_unit_ids;

if ($search_name) {
    $sql .= " AND m.name LIKE ?";
    $params[] = "%$search_name%";
}
if ($category_filter) {
    $sql .= " AND m.category = ?";
    $params[] = $category_filter;
}
if ($unit_filter && in_array($unit_filter, $allowed_unit_ids)) {
    $sql .= " AND m.unit_id = ?";
    $params[] = $unit_filter;
}
if ($storehouse_filter) {
    $sql .= " AND m.storehouse_id = ?";
    $params[] = $storehouse_filter;
}
$sql .= " ORDER BY m.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

// 获取所有可用的库房（用于筛选和新增/编辑表单）
$storehouse_list_all = $pdo->prepare("SELECT id, name, unit_id FROM storehouse WHERE unit_id IN ($allowed_placeholders) ORDER BY unit_id, name");
$storehouse_list_all->execute($allowed_unit_ids);
$storehouse_list_all = $storehouse_list_all->fetchAll();

// 获取本单位及下级单位（用于筛选下拉和新增/编辑表单）
$units_list = $pdo->prepare("SELECT id, name FROM unit WHERE id IN ($allowed_placeholders)");
$units_list->execute($allowed_unit_ids);
$units_list = $units_list->fetchAll();

// 获取启用的品类列表（用于动态下拉）
$category_list = $pdo->query("SELECT name FROM category WHERE status='启用' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<h2>物资管理</h2>
<?php if($message) echo "<div class='alert alert-success'>$message</div>"; ?>

<!-- 筛选表单 -->
<div class="row mb-3">
    <div class="col-md-12">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto"><input type="text" name="search_name" class="form-control" placeholder="物资名称" value="<?=htmlspecialchars($search_name)?>"></div>
            <div class="col-auto">
                <select name="category" class="form-select">
                    <option value="">全部分类</option>
                    <?php foreach($category_list as $cat): ?>
                        <option value="<?=$cat?>" <?=$category_filter==$cat?'selected':''?>><?=$cat?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="unit" id="filter_unit" class="form-select">
                    <option value="">全部单位</option>
                    <?php foreach($units_list as $unit): ?>
                        <option value="<?=$unit['id']?>" <?=$unit_filter==$unit['id']?'selected':''?>><?=htmlspecialchars($unit['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="storehouse" id="filter_storehouse" class="form-select">
                    <option value="">全部库房</option>
                    <?php foreach($storehouse_list_all as $sh): ?>
                        <option value="<?=$sh['id']?>" data-unit="<?=$sh['unit_id']?>" <?=$storehouse_filter==$sh['id']?'selected':''?>><?=htmlspecialchars($sh['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">筛选</button><a href="material.php" class="btn btn-secondary">重置</a></div>
            <div class="col-auto"><button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addMaterialModal">+ 新增物资</button></div>
            <div class="col-auto"><button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importMaterialModal">批量导入</button></div>
            <div class="col-auto"><a href="borrowed_list.php" class="btn btn-warning">借出物资查询</a></div>
        </form>
    </div>
</div>

<!-- 物资列表 -->
<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
            <tr><th>物资编码</th><th>物资名称</th><th>品类</th><th>所属单位</th><th>库房</th><th>货架</th><th>货架位置</th><th>数量</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach($materials as $m):
            $stmt2 = $pdo->prepare("SELECT SUM(borrow_quantity) FROM borrow_record WHERE material_id=? AND status='借出中'");
            $stmt2->execute([$m['id']]);
            $borrowed_qty = $stmt2->fetchColumn() ?: 0;
            $display_qty = $m['quantity'] . ($borrowed_qty > 0 ? " <span class='text-danger'>(借出 $borrowed_qty)</span>" : "");
        ?>
            <tr>
                <td><?=htmlspecialchars($m['code'])?></td>
                <td><?=htmlspecialchars($m['name'])?></td>
                <td><?=$m['category']?></td>
                <td><?=htmlspecialchars($m['unit_name'])?></td>
                <td><?=htmlspecialchars($m['storehouse_name'])?></td>
                <td><?=htmlspecialchars($m['shelf'])?></td>
                <td><?=htmlspecialchars($m['location'])?></td>
                <td><?=$display_qty?></td>
                <td><?=$m['status']?></td>
                <td>
                    <button class="btn btn-sm btn-primary borrow-btn" data-id="<?=$m['id']?>" data-name="<?=htmlspecialchars($m['name'])?>" data-max="<?=$m['quantity'] - $borrowed_qty?>">借出</button>
                    <button class="btn btn-sm btn-info edit-material-btn" data-id="<?=$m['id']?>" data-code="<?=htmlspecialchars($m['code'])?>" data-name="<?=htmlspecialchars($m['name'])?>" data-category="<?=$m['category']?>" data-unit="<?=$m['unit_id']?>" data-storehouse="<?=$m['storehouse_id']?>" data-shelf="<?=htmlspecialchars($m['shelf'])?>" data-location="<?=htmlspecialchars($m['location'])?>" data-quantity="<?=$m['quantity']?>" data-status="<?=$m['status']?>">编辑</button>
                    <a href="?del_material=<?=$m['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除？')">删除</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 新增物资模态框（品类下拉动态） -->
<div class="modal fade" id="addMaterialModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>新增物资</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post"><div class="modal-body">
        <div class="mb-3"><label>物资编码*</label><input type="text" name="code" class="form-control" required></div>
        <div class="mb-3"><label>物资名称*</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-3"><label>品类*</label>
            <select name="category" class="form-select" required>
                <?php foreach($category_list as $cat): ?>
                    <option value="<?=$cat?>"><?=$cat?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label>所属单位*</label>
            <select name="unit_id" class="form-select" required id="unit_select">
                <?php foreach($units_list as $unit): ?>
                    <option value="<?=$unit['id']?>"><?=htmlspecialchars($unit['name'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>库房</label>
            <div class="input-group">
                <select name="storehouse_id" class="form-select" id="storehouse_select">
                    <option value="">无</option>
                    <?php foreach($storehouse_list_all as $sh): ?>
                        <option value="<?=$sh['id']?>" data-unit="<?=$sh['unit_id']?>"><?=htmlspecialchars($sh['name'])?></option>
                    <?php endforeach; ?>
                </select>
                <a href="unit_manage.php" class="btn btn-outline-secondary" target="_blank">管理库房</a>
            </div>
        </div>
        <div class="mb-3"><label>货架</label><input type="text" name="shelf" class="form-control" placeholder="例如：A区"></div>
        <div class="mb-3"><label>货架位置</label><input type="text" name="location" class="form-control" placeholder="例如：A-01"></div>
        <div class="mb-3"><label>数量*</label><input type="number" name="quantity" class="form-control" required min="1"></div>
        <div class="mb-3"><label>状态</label><select name="status" class="form-select">
            <option value="新品">新品</option><option value="良好">良好</option><option value="堪用">堪用</option>
            <option value="损坏">损坏</option><option value="报废">报废</option><option value="消耗">消耗</option>
        </select></div>
    </div><div class="modal-footer"><button type="submit" name="add_material" class="btn btn-primary">保存</button></div></form>
</div></div></div>

<!-- 编辑物资模态框（品类下拉动态） -->
<div class="modal fade" id="editMaterialModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>编辑物资</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post"><div class="modal-body">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3"><label>物资编码*</label><input type="text" name="code" id="edit_code" class="form-control" required></div>
        <div class="mb-3"><label>物资名称*</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
        <div class="mb-3"><label>品类*</label>
            <select name="category" id="edit_category" class="form-select" required>
                <?php foreach($category_list as $cat): ?>
                    <option value="<?=$cat?>"><?=$cat?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label>所属单位*</label>
            <select name="unit_id" id="edit_unit_id" class="form-select" required>
                <?php foreach($units_list as $unit): ?>
                    <option value="<?=$unit['id']?>"><?=htmlspecialchars($unit['name'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>库房</label>
            <select name="storehouse_id" id="edit_storehouse_id" class="form-select">
                <option value="">无</option>
                <?php foreach($storehouse_list_all as $sh): ?>
                    <option value="<?=$sh['id']?>" data-unit="<?=$sh['unit_id']?>"><?=htmlspecialchars($sh['name'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label>货架</label><input type="text" name="shelf" id="edit_shelf" class="form-control"></div>
        <div class="mb-3"><label>货架位置</label><input type="text" name="location" id="edit_location" class="form-control"></div>
        <div class="mb-3"><label>数量*</label><input type="number" name="quantity" id="edit_quantity" class="form-control" required min="1"></div>
        <div class="mb-3"><label>状态</label><select name="status" id="edit_status" class="form-select">
            <option value="新品">新品</option><option value="良好">良好</option><option value="堪用">堪用</option>
            <option value="损坏">损坏</option><option value="报废">报废</option><option value="消耗">消耗</option>
        </select></div>
    </div><div class="modal-footer"><button type="submit" name="edit_material" class="btn btn-primary">更新</button></div></form>
</div></div></div>

<!-- 批量导入模态框 -->
<div class="modal fade" id="importMaterialModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>批量导入物资</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data" action="import_material.php"><div class="modal-body">
        <p>请上传CSV文件，列顺序：物资编码,物资名称,品类,所属单位名称,库房名称,货架,货架位置,数量,状态</p>
        <p>品类可选：<?=implode(', ', $category_list)?>（必须与系统中启用的品类一致）</p>
        <input type="file" name="material_file" class="form-control" accept=".csv" required>
        <small class="text-muted">可下载模板：<a href="material_template.csv" target="_blank">material_template.csv</a></small>
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">开始导入</button></div></form>
</div></div></div>

<!-- 借出模态框 -->
<div class="modal fade" id="borrowModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>物资借出</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="do_borrow.php"><div class="modal-body">
        <input type="hidden" name="material_id" id="borrow_material_id">
        <div class="mb-3"><label>物资名称</label><input type="text" id="borrow_material_name" class="form-control" readonly></div>
        <div class="mb-3"><label>可借数量</label><span id="borrow_max_qty" class="form-control-plaintext"></span></div>
        <div class="mb-3"><label>借用数量*</label><input type="number" name="borrow_quantity" class="form-control" required min="1"></div>
        <div class="mb-3"><label>借用单位/个人</label><input type="text" name="borrower_unit" class="form-control" required></div>
        <div class="mb-3"><label>归还时限*</label><input type="date" name="due_time" class="form-control" required></div>
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">确认借出</button></div></form>
</div></div></div>

<script>
$(function(){
    $('.borrow-btn').click(function(){
        $('#borrow_material_id').val($(this).data('id'));
        $('#borrow_material_name').val($(this).data('name'));
        $('#borrow_max_qty').text($(this).data('max'));
        $('#borrowModal').modal('show');
    });

    // 编辑按钮填充数据
    $('.edit-material-btn').click(function(){
        $('#edit_id').val($(this).data('id'));
        $('#edit_code').val($(this).data('code'));
        $('#edit_name').val($(this).data('name'));
        $('#edit_category').val($(this).data('category'));
        $('#edit_unit_id').val($(this).data('unit'));
        $('#edit_storehouse_id').val($(this).data('storehouse'));
        $('#edit_shelf').val($(this).data('shelf'));
        $('#edit_location').val($(this).data('location'));
        $('#edit_quantity').val($(this).data('quantity'));
        $('#edit_status').val($(this).data('status'));
        filterStorehouseByUnit($('#edit_unit_id').val(), '#edit_storehouse_id');
        $('#editMaterialModal').modal('show');
    });

    function filterStorehouseByUnit(unitId, targetSelect) {
        $(targetSelect + ' option').each(function(){
            var optUnit = $(this).data('unit');
            if(optUnit == unitId || $(this).val() === '') {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        if ($(targetSelect + ' option:selected').data('unit') != unitId && $(targetSelect).val() !== '') {
            $(targetSelect).val('');
        }
    }

    $('#unit_select').change(function(){
        filterStorehouseByUnit($(this).val(), '#storehouse_select');
    }).trigger('change');

    $('#edit_unit_id').change(function(){
        filterStorehouseByUnit($(this).val(), '#edit_storehouse_id');
    });

    $('#filter_unit').change(function(){
        var unitId = $(this).val();
        $('#filter_storehouse option').each(function(){
            var optUnit = $(this).data('unit');
            if(optUnit == unitId || unitId === '' || $(this).val() === '') {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        $('#filter_storehouse').val('');
    }).trigger('change');
});
</script>

<style>
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
    .badge {
        color: #fff;
    }
    .text-danger {
        color: #ff9f9f !important;
    }
</style>

<?php include 'includes/footer.php'; ?>