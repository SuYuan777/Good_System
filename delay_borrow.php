<?php
require_once 'config.php';
$user = checkPermission('operator');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $new_due = $_POST['new_due'];
    $stmt = $pdo->prepare("UPDATE borrow_record SET due_time=? WHERE id=?");
    $stmt->execute([$new_due, $id]);
    logOperation("延期借出记录 ID: $id 至 $new_due");
    header("Location: borrowed_list.php?msg=延期成功");
    exit;
}
$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM borrow_record WHERE id=?");
$stmt->execute([$id]);
$record = $stmt->fetch();
if (!$record) die("记录不存在");
include 'includes/header.php';
?>
<h3>延期借出</h3>
<form method="post">
    <input type="hidden" name="id" value="<?=$id?>">
    <div class="mb-3"><label>新的归还时限</label><input type="date" name="new_due" class="form-control" value="<?=$record['due_time']?>" required></div>
    <button type="submit" class="btn btn-primary">确认延期</button>
    <a href="borrowed_list.php" class="btn btn-secondary">取消</a>
</form>
<?php include 'includes/footer.php'; ?>