<?php
require_once 'config.php';
$user = checkPermission('operator');
$id = intval($_GET['id']);
$ri = $pdo->prepare("SELECT br.borrow_quantity, br.borrower_unit, m.name FROM borrow_record br JOIN material m ON br.material_id=m.id WHERE br.id=?");
$ri->execute([$id]);
$ri = $ri->fetch();
$stmt = $pdo->prepare("UPDATE borrow_record SET status='已归还', actual_return_time=NOW() WHERE id=? AND status='借出中'");
$stmt->execute([$id]);
logOperation("归还物资: 【{$ri['name']}】数量: {$ri['borrow_quantity']} 归还自: {$ri['borrower_unit']}");
header("Location: borrowed_list.php?msg=归还成功");
?>