<?php
require_once 'config.php';
$user = checkPermission('operator');
$unit_id = $user['unit_id'];
$allowed_unit_ids = getSubUnitIds($unit_id, true);
$allowed_placeholders = implode(',', array_fill(0, count($allowed_unit_ids), '?'));

$message = '';

// 处理归还操作
if (isset($_GET['return_id'])) {
    $record_id = intval($_GET['return_id']);
    $ri = $pdo->prepare("SELECT br.borrow_quantity, br.borrower_unit, m.name FROM borrow_record br JOIN material m ON br.material_id=m.id WHERE br.id=?");
    $ri->execute([$record_id]);
    $ri = $ri->fetch();
    $stmt = $pdo->prepare("UPDATE borrow_record SET status='已归还', actual_return_time=NOW() WHERE id=? AND status='借出中'");
    $stmt->execute([$record_id]);
    logOperation("归还物资: 【{$ri['name']}】数量: {$ri['borrow_quantity']} 归还自: {$ri['borrower_unit']}");
    $message = "归还成功";
    header("Location: borrowed_list.php?msg=归还成功");
    exit;
}

// 处理延期操作
if (isset($_POST['extend'])) {
    $record_id = intval($_POST['record_id']);
    $new_due = $_POST['new_due_time'];
    $ei = $pdo->prepare("SELECT br.borrow_quantity, br.borrower_unit, m.name FROM borrow_record br JOIN material m ON br.material_id=m.id WHERE br.id=?");
    $ei->execute([$record_id]);
    $ei = $ei->fetch();
    $stmt = $pdo->prepare("UPDATE borrow_record SET due_time=? WHERE id=?");
    $stmt->execute([$new_due, $record_id]);
    logOperation("延期物资: 【{$ei['name']}】数量: {$ei['borrow_quantity']} 借用单位: {$ei['borrower_unit']} 新归还日期: $new_due");
    $message = "延期成功";
    header("Location: borrowed_list.php?msg=延期成功");
    exit;
}

// 搜索筛选条件
$search_material = isset($_GET['search_material']) ? trim($_GET['search_material']) : '';
$search_borrower = isset($_GET['search_borrower']) ? trim($_GET['search_borrower']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$sql = "SELECT br.*, m.name as material_name, m.code, u.name as unit_name 
        FROM borrow_record br 
        JOIN material m ON br.material_id = m.id 
        JOIN unit u ON m.unit_id = u.id 
        WHERE m.unit_id IN ($allowed_placeholders)";
$params = $allowed_unit_ids;
if ($search_material) {
    $sql .= " AND m.name LIKE ?";
    $params[] = "%$search_material%";
}
if ($search_borrower) {
    $sql .= " AND br.borrower_unit LIKE ?";
    $params[] = "%$search_borrower%";
}
if ($status_filter && in_array($status_filter, ['借出中','已归还','逾期'])) {
    $sql .= " AND br.status = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY br.borrow_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

include 'includes/header.php';
?>

<h2>借出物资查询</h2>
<?php if(isset($_GET['msg'])) echo "<div class='alert alert-success'>".htmlspecialchars($_GET['msg'])."</div>"; ?>

<!-- 筛选表单 -->
<form method="get" class="row g-3 mb-3">
    <div class="col-md-3"><input type="text" name="search_material" class="form-control" placeholder="物资名称" value="<?=htmlspecialchars($search_material)?>"></div>
    <div class="col-md-3"><input type="text" name="search_borrower" class="form-control" placeholder="借用单位/个人" value="<?=htmlspecialchars($search_borrower)?>"></div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">全部状态</option>
            <option value="借出中" <?=$status_filter=='借出中'?'selected':''?>>借出中</option>
            <option value="已归还" <?=$status_filter=='已归还'?'selected':''?>>已归还</option>
            <option value="逾期" <?=$status_filter=='逾期'?'selected':''?>>逾期</option>
        </select>
    </div>
    <div class="col-md-3"><button type="submit" class="btn btn-primary">筛选</button> <a href="borrowed_list.php" class="btn btn-secondary">重置</a></div>
</form>

<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
            <tr><th>物资编码</th><th>物资名称</th><th>所属单位</th><th>借出数量</th><th>借用单位</th><th>借出时间</th><th>应还日期</th><th>实际归还时间</th><th>状态</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php foreach($records as $r): 
            $is_overdue = ($r['status']=='借出中' && $r['due_time'] < date('Y-m-d'));
            $status_class = '';
            if($is_overdue) $status_class = 'bg-danger text-white';
            elseif($r['status']=='借出中') $status_class = 'bg-warning';
            elseif($r['status']=='已归还') $status_class = 'bg-success text-white';
        ?>
            <tr class="<?=$status_class?>">
                <td><?=htmlspecialchars($r['code'])?></td>
                <td><?=htmlspecialchars($r['material_name'])?></td>
                <td><?=htmlspecialchars($r['unit_name'])?></td>
                <td><?=$r['borrow_quantity']?></td>
                <td><?=htmlspecialchars($r['borrower_unit'])?></td>
                <td><?=$r['borrow_time']?></td>
                <td><?=$r['due_time']?></td>
                <td><?=$r['actual_return_time'] ?: '-'?></td>
                <td><?=$r['status']?></td>
                <td>
                    <?php if($r['status'] == '借出中'): ?>
                        <a href="?return_id=<?=$r['id']?>" class="btn btn-sm btn-success" onclick="return confirm('确认归还？')">归还</a>
                        <button class="btn btn-sm btn-info extend-btn" data-id="<?=$r['id']?>" data-due="<?=$r['due_time']?>">延期</button>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 延期模态框 -->
<div class="modal fade" id="extendModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>延期归还</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post"><div class="modal-body">
        <input type="hidden" name="record_id" id="extend_record_id">
        <div class="mb-3"><label>新的归还日期</label><input type="date" name="new_due_time" id="new_due_time" class="form-control" required></div>
    </div><div class="modal-footer"><button type="submit" name="extend" class="btn btn-primary">确认延期</button></div></form>
</div></div></div>

<script>
$(function(){
    $('.extend-btn').click(function(){
        $('#extend_record_id').val($(this).data('id'));
        var oldDue = $(this).data('due');
        $('#new_due_time').val(oldDue);
        $('#extendModal').modal('show');
    });
});
</script>

<?php include 'includes/footer.php'; ?>