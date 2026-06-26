<?php
require_once 'config.php';
$user = checkPermission('unit_admin');
// 监察员与系统管理员共享“可见所有单位”的范围
$is_super = in_array($user['role'], ['super_admin','inspector']);
$unit_id = $user['unit_id'];
$message = '';

// 获取当前用户管理的单位ID列表
if ($is_super) {
    $allowed_unit_ids = $pdo->query("SELECT id FROM unit")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $allowed_unit_ids = getSubUnitIds($unit_id, true);
}
$allowed_placeholders = implode(',', array_fill(0, count($allowed_unit_ids), '?'));

// 新增用户
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $real_name = trim($_POST['real_name']);
    $password = hash('sha256', $_POST['password']);
    $unit_id = intval($_POST['unit_id']);
    $position = trim($_POST['position']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $check = $pdo->prepare("SELECT id FROM user WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        $message = "用户名已存在";
    } else {
        $stmt = $pdo->prepare("INSERT INTO user (username, password, real_name, unit_id, position, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password, $real_name, $unit_id, $position, $role, $status]);
        logOperation("新增用户: $username ($real_name) 角色: $role");
        $message = "用户添加成功";
    }
}

// 编辑用户
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $id = intval($_POST['id']);
    $unit_id = intval($_POST['unit_id']);
    $position = trim($_POST['position']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $uinfo = $pdo->prepare("SELECT username, real_name FROM user WHERE id=?");
    $uinfo->execute([$id]);
    $uinfo = $uinfo->fetch();
    $stmt = $pdo->prepare("UPDATE user SET unit_id=?, position=?, role=?, status=? WHERE id=?");
    $stmt->execute([$unit_id, $position, $role, $status, $id]);
    logOperation("编辑用户: {$uinfo['username']} ({$uinfo['real_name']}) 角色: $role 状态: $status");
    $message = "用户更新成功";
}

// 删除用户
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $uinfo = $pdo->prepare("SELECT username, real_name FROM user WHERE id=?");
    $uinfo->execute([$id]);
    $uinfo = $uinfo->fetch();
    $stmt = $pdo->prepare("DELETE FROM user WHERE id=?");
    $stmt->execute([$id]);
    logOperation("删除用户: {$uinfo['username']} ({$uinfo['real_name']})");
    header("Location: personnel.php");
    exit;
}

// 启用/停用
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $pdo->prepare("SELECT status, username, real_name FROM user WHERE id=?");
    $stmt->execute([$id]);
    $urow = $stmt->fetch();
    $cur = $urow['status'];
    $new = ($cur == '启用') ? '停用' : '启用';
    $stmt = $pdo->prepare("UPDATE user SET status=? WHERE id=?");
    $stmt->execute([$new, $id]);
    logOperation("切换用户状态: {$urow['username']} ({$urow['real_name']}) $cur → $new");
    header("Location: personnel.php");
    exit;
}

// 搜索筛选
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$search_username = isset($_GET['search_username']) ? trim($_GET['search_username']) : '';
$search_unit = isset($_GET['search_unit']) ? intval($_GET['search_unit']) : 0;
$sql = "SELECT u.*, un.name as unit_name FROM user u LEFT JOIN unit un ON u.unit_id = un.id WHERE u.unit_id IN ($allowed_placeholders)";
$params = $allowed_unit_ids;
if ($search_name) {
    $sql .= " AND u.real_name LIKE ?";
    $params[] = "%$search_name%";
}
if ($search_username) {
    $sql .= " AND u.username LIKE ?";
    $params[] = "%$search_username%";
}
if ($search_unit && in_array($search_unit, $allowed_unit_ids)) {
    $sql .= " AND u.unit_id = ?";
    $params[] = $search_unit;
}
$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// 角色英文 → 中文映射
$role_labels = [
    'super_admin' => '系统管理员',
    'inspector'   => '监察员',
    'unit_admin'  => '单位管理员',
    'operator'    => '普通操作员',
];

// 单位列表
if ($is_super) {
    $all_units = $pdo->query("SELECT id, name FROM unit")->fetchAll();
} else {
    $all_units = $pdo->prepare("SELECT id, name FROM unit WHERE id IN ($allowed_placeholders)");
    $all_units->execute($allowed_unit_ids);
    $all_units = $all_units->fetchAll();
}

include 'includes/header.php';
?>

<h2>人员管理</h2>
<?php if($message) echo "<div class='alert alert-info'>$message</div>"; ?>

<form method="get" class="row g-3 mb-3">
    <div class="col-md-3"><input type="text" name="search_name" class="form-control" placeholder="姓名" value="<?=htmlspecialchars($search_name)?>"></div>
    <div class="col-md-3"><input type="text" name="search_username" class="form-control" placeholder="用户名" value="<?=htmlspecialchars($search_username)?>"></div>
    <div class="col-md-3">
        <select name="search_unit" class="form-select">
            <option value="">全部单位</option>
            <?php foreach($all_units as $unit): ?>
                <option value="<?=$unit['id']?>" <?=$search_unit==$unit['id']?'selected':''?>><?=htmlspecialchars($unit['name'])?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <button type="submit" class="btn btn-primary">搜索</button>
        <a href="personnel.php" class="btn btn-secondary">重置</a>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">+ 新增用户</button>
        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importModal">批量导入</button>
        <a href="personnel_template.csv" class="btn btn-outline-secondary">下载模板</a>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
            <tr><th>用户名</th><th>姓名</th><th>单位</th><th>职务</th><th>权限</th><th>状态</th><th>创建时间</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach($users as $u): ?>
            <tr>
                <td><?=htmlspecialchars($u['username'])?></td>
                <td><?=htmlspecialchars($u['real_name'])?></td>
                <td><?=htmlspecialchars($u['unit_name'])?></td>
                <td><?=htmlspecialchars($u['position'])?></td>
                <td><?=htmlspecialchars($role_labels[$u['role']] ?? $u['role'])?></td>
                <td><span class="badge bg-<?=($u['status']=='启用')?'success':'secondary'?>"><?=$u['status']?></span></td>
                <td><?=$u['created_at']?></td>
                <td>
                    <button class="btn btn-sm btn-warning edit-btn" data-id="<?=$u['id']?>" data-unit="<?=$u['unit_id']?>" data-position="<?=htmlspecialchars($u['position'])?>" data-role="<?=$u['role']?>" data-status="<?=$u['status']?>">编辑</button>
                    <a href="?del=<?=$u['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('确定删除？')">删除</a>
                    <a href="?toggle=<?=$u['id']?>" class="btn btn-sm btn-secondary"><?=($u['status']=='启用')?'停用':'启用'?></a>
                </td>
             </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 新增用户模态框 -->
<div class="modal fade" id="addUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>新增用户</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post"><div class="modal-body">
        <div class="mb-3"><label>用户名*</label><input type="text" name="username" class="form-control" required></div>
        <div class="mb-3"><label>姓名*</label><input type="text" name="real_name" class="form-control" required></div>
        <div class="mb-3"><label>密码*</label><input type="password" name="password" class="form-control" required></div>
        <div class="mb-3"><label>所在单位*</label><select name="unit_id" class="form-select" required>
            <?php foreach($all_units as $unit): ?>
                <option value="<?=$unit['id']?>"><?=htmlspecialchars($unit['name'])?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="mb-3"><label>职务</label><input type="text" name="position" class="form-control"></div>
        <div class="mb-3"><label>权限*</label><select name="role" class="form-select" required>
            <?php if($is_super): ?><option value="super_admin">系统管理员</option><?php endif; ?>
            <?php if($is_super): ?><option value="inspector">监察员</option><?php endif; ?>
            <option value="unit_admin">单位管理员</option>
            <option value="operator">普通操作员</option>
        </select></div>
        <div class="mb-3"><label>状态</label><select name="status" class="form-select"><option value="启用">启用</option><option value="停用">停用</option></select></div>
    </div><div class="modal-footer"><button type="submit" name="add_user" class="btn btn-primary">保存</button></div></form>
</div></div></div>

<!-- 编辑用户模态框 -->
<div class="modal fade" id="editUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>编辑用户</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post"><div class="modal-body">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3"><label>所在单位</label><select name="unit_id" id="edit_unit" class="form-select">
            <?php foreach($all_units as $unit): ?>
                <option value="<?=$unit['id']?>"><?=htmlspecialchars($unit['name'])?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="mb-3"><label>职务</label><input type="text" name="position" id="edit_position" class="form-control"></div>
        <div class="mb-3"><label>权限</label><select name="role" id="edit_role" class="form-select">
            <?php if($is_super): ?><option value="super_admin">系统管理员</option><?php endif; ?>
            <?php if($is_super): ?><option value="inspector">监察员</option><?php endif; ?>
            <option value="unit_admin">单位管理员</option>
            <option value="operator">普通操作员</option>
        </select></div>
        <div class="mb-3"><label>状态</label><select name="status" id="edit_status" class="form-select"><option value="启用">启用</option><option value="停用">停用</option></select></div>
    </div><div class="modal-footer"><button type="submit" name="edit_user" class="btn btn-primary">更新</button></div></form>
</div></div></div>

<!-- 批量导入模态框 -->
<div class="modal fade" id="importModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>批量导入用户（CSV文件）</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" enctype="multipart/form-data" action="import_users.php"><div class="modal-body">
        <p>请上传CSV文件，列顺序：用户名,姓名,密码,单位名称,职务,权限,状态</p>
        <input type="file" name="userfile" class="form-control" accept=".csv" required>
    </div><div class="modal-footer"><button type="submit" class="btn btn-primary">导入</button></div></form>
</div></div></div>

<script>
$(function(){
    $('.edit-btn').click(function(){
        $('#edit_id').val($(this).data('id'));
        $('#edit_unit').val($(this).data('unit'));
        $('#edit_position').val($(this).data('position'));
        $('#edit_role').val($(this).data('role'));
        $('#edit_status').val($(this).data('status'));
        $('#editUserModal').modal('show');
    });
});
</script>

<style>
    /* 强制表格文字颜色为浅色，提高对比度 */
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
    /* 确保表格内的badge文字也可见 */
    .badge {
        color: #fff;
    }
</style>

<?php include 'includes/footer.php'; ?>