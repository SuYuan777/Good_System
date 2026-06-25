<?php
require_once 'config.php';
$user = checkPermission('super_admin'); // 仅系统管理员可管理品类
$message = '';

// 处理新增
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    if (empty($name)) {
        $message = "品类名称不能为空";
    } else {
        $stmt = $pdo->prepare("INSERT INTO category (name, status) VALUES (?, '启用')");
        if ($stmt->execute([$name])) {
            logOperation("新增物资品类: $name");
            $message = "品类添加成功";
        } else {
            $message = "添加失败，可能名称已存在";
        }
    }
}

// 处理编辑
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $status = $_POST['status'];
    if (empty($name)) {
        $message = "品类名称不能为空";
    } else {
        $stmt = $pdo->prepare("UPDATE category SET name=?, status=? WHERE id=?");
        if ($stmt->execute([$name, $status, $id])) {
            logOperation("编辑物资品类: 【{$name}】状态: {$status}");
            $message = "品类更新成功";
        } else {
            $message = "更新失败，可能名称重复";
        }
    }
}

// 处理删除
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $catname = $pdo->prepare("SELECT name FROM category WHERE id=?");
    $catname->execute([$id]);
    $catname = $catname->fetchColumn() ?: "ID:$id";
    $check = $pdo->prepare("SELECT COUNT(*) FROM material WHERE category = (SELECT name FROM category WHERE id=?)");
    $check->execute([$id]);
    $count = $check->fetchColumn();
    if ($count > 0) {
        $message = "无法删除，有 $count 个物资正在使用此品类";
    } else {
        $stmt = $pdo->prepare("DELETE FROM category WHERE id=?");
        $stmt->execute([$id]);
        logOperation("删除物资品类: 【{$catname}】");
        $message = "品类删除成功";
        header("Location: category_manage.php");
        exit;
    }
}

// 获取所有品类
$categories = $pdo->query("SELECT * FROM category ORDER BY id")->fetchAll();

include 'includes/header.php';
?>

<h2>物资品类管理</h2>
<?php if($message) echo "<div class='alert alert-info'>$message</div>"; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">新增品类</div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3"><label>品类名称</label><input type="text" name="name" class="form-control" required></div>
                    <button type="submit" name="add_category" class="btn btn-primary">添加</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">现有品类列表</div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr><th>ID</th><th>品类名称</th><th>状态</th><th>创建时间</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach($categories as $c): ?>
                    <tr>
                        <td><?=$c['id']?></td>
                        <td><?=htmlspecialchars($c['name'])?></td>
                        <td><span class="badge bg-<?=($c['status']=='启用')?'success':'secondary'?>"><?=$c['status']?></span></td>
                        <td><?=$c['created_at']?></td>
                        <td>
                            <button class="btn btn-sm btn-warning edit-category" data-id="<?=$c['id']?>" data-name="<?=htmlspecialchars($c['name'])?>" data-status="<?=$c['status']?>">编辑</button>
                            <a href="?del=<?=$c['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除？如有物资使用此品类将无法删除')">删除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 编辑品类模态框 -->
<div class="modal fade" id="editCategoryModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>编辑品类</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post"><div class="modal-body">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3"><label>品类名称</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
        <div class="mb-3"><label>状态</label><select name="status" id="edit_status" class="form-select"><option value="启用">启用</option><option value="停用">停用</option></select></div>
    </div><div class="modal-footer"><button type="submit" name="edit_category" class="btn btn-primary">保存</button></div></form>
</div></div></div>

<script>
$(function(){
    $('.edit-category').click(function(){
        $('#edit_id').val($(this).data('id'));
        $('#edit_name').val($(this).data('name'));
        $('#edit_status').val($(this).data('status'));
        $('#editCategoryModal').modal('show');
    });
});
</script>

<style>
    .table, .table tbody, .table tr, .table td, .table th {
        color: #f0f0f0 !important;
    }
    .table-striped > tbody > tr:nth-of-type(odd) > * {
        background-color: rgba(0, 80, 120, 0.2);
    }
    .badge { color: #fff; }
</style>

<?php include 'includes/footer.php'; ?>