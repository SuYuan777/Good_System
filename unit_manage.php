<?php
require_once 'config.php';
$user = checkPermission('operator');
$is_super = ($user['role'] == 'super_admin');
$unit_id = $user['unit_id'];
$message = '';

// 处理新增/编辑单位
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$is_super) die('权限不足');
    if (isset($_POST['add_unit'])) {
        $name = trim($_POST['name']);
        $parent_id = $_POST['parent_id'] ?: null;
        $stmt = $pdo->prepare("INSERT INTO unit (name, parent_id) VALUES (?, ?)");
        $stmt->execute([$name, $parent_id]);
        logOperation("新增单位：{$name}");
        $message = "单位添加成功";
        header("Location: unit_manage.php");
        exit;
    } elseif (isset($_POST['edit_unit'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $parent_id = $_POST['parent_id'] ?: null;
        $stmt = $pdo->prepare("UPDATE unit SET name=?, parent_id=? WHERE id=?");
        $stmt->execute([$name, $parent_id, $id]);
        logOperation("编辑单位: 【{$name}】(ID:{$id})");
        $message = "单位更新成功";
        header("Location: unit_manage.php");
        exit;
    }
}

// 处理删除单位（GET 请求）
if (isset($_GET['del'])) {
    if (!$is_super) die('权限不足');
    $id = intval($_GET['del']);
    $dname = $pdo->prepare("SELECT name FROM unit WHERE id=?");
    $dname->execute([$id]);
    $dname = $dname->fetchColumn() ?: "ID:$id";
    $stmt = $pdo->prepare("DELETE FROM unit WHERE id=?");
    $stmt->execute([$id]);
    logOperation("删除单位: 【{$dname}】");
    header("Location: unit_manage.php");
    exit;
}

// 处理库房操作
if (isset($_POST['add_storehouse'])) {
    if (!$is_super && $user['role'] != 'unit_admin') die('权限不足');
    $name = trim($_POST['name']);
    $unit_id = intval($_POST['unit_id']);
    if (!$is_super) {
        $allowed = getSubUnitIds($user['unit_id'], true);
        if (!in_array($unit_id, $allowed)) die('无权限操作此单位库房');
    }
    $stmt = $pdo->prepare("INSERT INTO storehouse (name, unit_id) VALUES (?, ?)");
    $stmt->execute([$name, $unit_id]);
    logOperation("新增库房: {$name} (单位ID {$unit_id})");
    header("Location: unit_manage.php");
    exit;
}

if (isset($_POST['edit_storehouse'])) {
    if (!$is_super && $user['role'] != 'unit_admin') die('权限不足');
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $stmt = $pdo->prepare("UPDATE storehouse SET name=? WHERE id=?");
    $stmt->execute([$name, $id]);
    logOperation("编辑库房: 【{$name}】(ID:{$id})");
    header("Location: unit_manage.php");
    exit;
}

if (isset($_GET['del_storehouse'])) {
    if (!$is_super && $user['role'] != 'unit_admin') die('权限不足');
    $id = intval($_GET['del_storehouse']);
    $dname = $pdo->prepare("SELECT name FROM storehouse WHERE id=?");
    $dname->execute([$id]);
    $dname = $dname->fetchColumn() ?: "ID:$id";
    $stmt = $pdo->prepare("DELETE FROM storehouse WHERE id=?");
    $stmt->execute([$id]);
    logOperation("删除库房: 【{$dname}】");
    header("Location: unit_manage.php");
    exit;
}

// 获取所有单位
$units = $pdo->query("SELECT * FROM unit ORDER BY id")->fetchAll();

// 构建树形结构函数
function buildTree($units, $parent=0, $level=0) {
    $html = '';
    foreach($units as $u) {
        if($u['parent_id'] == $parent) {
            $indent = str_repeat('&nbsp;&nbsp;', $level);
            $html .= '<li>';
            $html .= '<div class="unit-item">';
            $html .= $indent . htmlspecialchars($u['name']);
            if ($GLOBALS['is_super']) {
                $html .= ' <button type="button" class="btn btn-sm btn-warning edit-unit-btn" data-id="'.$u['id'].'" data-name="'.htmlspecialchars($u['name']).'" data-parent="'.$u['parent_id'].'">编辑</button>';
                $html .= ' <a href="?del='.$u['id'].'" class="btn btn-sm btn-danger" onclick="return confirm(\'确定删除此单位？\')">删除</a>';
            }
            $html .= ' <button type="button" class="btn btn-sm btn-info view-storehouse-btn" data-unit-id="'.$u['id'].'" data-unit-name="'.htmlspecialchars($u['name']).'">管理库房</button>';
            $html .= '</div>';
            $child = buildTree($units, $u['id'], $level+1);
            if($child) $html .= '<ul>'.$child.'</ul>';
            $html .= '</li>';
        }
    }
    return $html;
}
$treeHtml = '<ul class="unit-tree">'.buildTree($units).'</ul>';

include 'includes/header.php';
?>

<h2>单位管理</h2>
<?php if($message) echo "<div class='alert alert-success'>$message</div>"; ?>
<div class="row">
    <!-- 左侧单位表格 -->
    <div class="col-md-6">
        <?php if($is_super): ?>
        <button type="button" class="btn btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#addUnitModal">+ 新增单位</button>
        <?php endif; ?>
        <div style="max-height:500px; overflow:auto; background: rgba(10, 25, 55, 0.4); border-radius: 20px; padding: 5px;">
            <table class="table table-sm table-bordered" style="margin-bottom:0;">
                <thead>
                    <tr><th>名称</th><th>上级单位</th><th>创建时间</th><?php if($is_super) echo '<th>操作</th>'; ?></td>
                </thead>
                <tbody>
                <?php foreach($units as $u):
                    $parentName = $u['parent_id'] ? ($pdo->query("SELECT name FROM unit WHERE id={$u['parent_id']}")->fetchColumn() ?: '无') : '无';
                ?>
                    <tr>
                        <td><?=htmlspecialchars($u['name'])?></td>
                        <td><?=$parentName?></td>
                        <td><?=$u['created_at']?></td>
                        <?php if($is_super): ?>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning edit-unit-btn" data-id="<?=$u['id']?>" data-name="<?=htmlspecialchars($u['name'])?>" data-parent="<?=$u['parent_id']?>">编辑</button>
                            <a href="?del=<?=$u['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除？')">删除</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 右侧树形图 -->
    <div class="col-md-6">
        <h4>单位隶属关系树形图</h4>
        <div style="border:1px solid rgba(0, 255, 255, 0.3); padding:15px; background: rgba(10, 25, 55, 0.6); border-radius: 20px; min-height:500px; overflow:auto;">
            <?= $treeHtml ?>
        </div>
    </div>
</div>

<!-- 新增单位模态框 -->
<div class="modal fade" id="addUnitModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5>新增单位</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post"><div class="modal-body">
            <div class="mb-3"><label>单位名称</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label>上级单位</label><select name="parent_id" class="form-select"><option value="">无</option>
                <?php foreach($units as $u): ?><option value="<?=$u['id']?>"><?=htmlspecialchars($u['name'])?></option><?php endforeach; ?>
            </select></div>
        </div><div class="modal-footer"><button type="submit" name="add_unit" class="btn btn-primary">保存</button></div></form>
    </div></div>
</div>

<!-- 编辑单位模态框 -->
<div class="modal fade" id="editUnitModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5>编辑单位</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post"><div class="modal-body">
            <input type="hidden" name="id" id="edit_id">
            <div class="mb-3"><label>单位名称</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
            <div class="mb-3"><label>上级单位</label><select name="parent_id" id="edit_parent" class="form-select"><option value="">无</option>
                <?php foreach($units as $u): ?><option value="<?=$u['id']?>"><?=htmlspecialchars($u['name'])?></option><?php endforeach; ?>
            </select></div>
        </div><div class="modal-footer"><button type="submit" name="edit_unit" class="btn btn-primary">更新</button></div></form>
    </div></div>
</div>

<!-- 库房管理模态框 -->
<div class="modal fade" id="storehouseModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5>库房管理 - <span id="currentUnitName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" id="currentUnitId">
            <div class="mb-2">
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addStorehouseModal">+ 新增库房</button>
            </div>
            <div id="storehouseList"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button></div>
    </div></div>
</div>

<!-- 新增库房模态框 -->
<div class="modal fade" id="addStorehouseModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5>新增库房</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post"><div class="modal-body">
            <input type="hidden" name="unit_id" id="storehouse_unit_id">
            <div class="mb-3"><label>库房名称</label><input type="text" name="name" class="form-control" required></div>
        </div><div class="modal-footer"><button type="submit" name="add_storehouse" class="btn btn-primary">保存</button></div></form>
    </div></div>
</div>

<!-- 编辑库房模态框 -->
<div class="modal fade" id="editStorehouseModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5>编辑库房</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post"><div class="modal-body">
            <input type="hidden" name="id" id="edit_storehouse_id">
            <div class="mb-3"><label>库房名称</label><input type="text" name="name" id="edit_storehouse_name" class="form-control" required></div>
        </div><div class="modal-footer"><button type="submit" name="edit_storehouse" class="btn btn-primary">更新</button></div></form>
    </div></div>
</div>

<script>
$(function() {
    // 使用事件委托，确保动态生成的按钮也能响应
    $(document).on('click', '.edit-unit-btn', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        var parent = $(this).data('parent');
        $('#edit_id').val(id);
        $('#edit_name').val(name);
        $('#edit_parent').val(parent);
        $('#editUnitModal').modal('show');
    });

    $(document).on('click', '.view-storehouse-btn', function() {
        var unitId = $(this).data('unit-id');
        var unitName = $(this).data('unit-name');
        $('#currentUnitId').val(unitId);
        $('#currentUnitName').text(unitName);
        $('#storehouse_unit_id').val(unitId);
        loadStorehouses(unitId);
        $('#storehouseModal').modal('show');
    });

    $(document).on('click', '.edit-storehouse', function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        $('#edit_storehouse_id').val(id);
        $('#edit_storehouse_name').val(name);
        $('#editStorehouseModal').modal('show');
    });

    function loadStorehouses(unitId) {
        $.getJSON('get_storehouses.php?unit_id=' + unitId, function(data) {
            var html = '<table class="table table-sm table-bordered"><thead><tr><th>ID</th><th>库房名称</th><th>操作</th></tr></thead><tbody>';
            if (data.length === 0) {
                html += '<tr><td colspan="3" class="text-center">暂无库房</td></tr>';
            } else {
                $.each(data, function(i, sh) {
                    html += '<tr>' +
                        '<td>' + sh.id + '</td>' +
                        '<td>' + sh.name + '</td>' +
                        '<td>' +
                        '<button class="btn btn-sm btn-warning edit-storehouse" data-id="' + sh.id + '" data-name="' + sh.name + '">编辑</button> ' +
                        '<a href="?del_storehouse=' + sh.id + '" class="btn btn-sm btn-danger" onclick="return confirm(\'确定删除此库房？\')">删除</a>' +
                        '</td>' +
                        '</tr>';
                });
            }
            html += '</tbody></table>';
            $('#storehouseList').html(html);
        }).fail(function() {
            $('#storehouseList').html('<div class="alert alert-danger">加载库房失败</div>');
        });
    }
});
</script>

<style>
.unit-tree {
    list-style-type: none;
    padding-left: 20px;
    color: #e0e0e0;
}
.unit-tree ul {
    margin-left: 20px;
    list-style-type: none;
}
.unit-tree li {
    margin: 8px 0;
}
.unit-item {
    padding: 6px 10px;
    background: rgba(0, 30, 60, 0.5);
    border-radius: 12px;
    border-left: 3px solid #0af;
    transition: all 0.2s;
}
.unit-item:hover {
    background: rgba(0, 170, 255, 0.2);
    transform: translateX(5px);
}
</style>
<?php include 'includes/footer.php'; ?>