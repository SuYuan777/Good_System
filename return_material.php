<?php
require_once 'config.php';
$user = checkPermission('operator');
$id = intval($_GET['id']);
$stmt = $pdo->prepare("UPDATE borrow_record SET status='已归还', actual_return_time=NOW() WHERE id=? AND status='借出中'");
$stmt->execute([$id]);
logOperation("归还借出记录 ID: $id");
header("Location: borrowed_list.php?msg=归还成功");
?>