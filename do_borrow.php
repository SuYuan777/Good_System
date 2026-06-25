<?php
require_once 'config.php';
$user = checkPermission('operator');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $material_id = intval($_POST['material_id']);
    $borrow_quantity = intval($_POST['borrow_quantity']);
    $borrower_unit = trim($_POST['borrower_unit']);
    $due_time = $_POST['due_time'];
    // 检查库存
    $stmt = $pdo->prepare("SELECT quantity FROM material WHERE id = ?");
    $stmt->execute([$material_id]);
    $qty = $stmt->fetchColumn();
    $stmt2 = $pdo->prepare("SELECT SUM(borrow_quantity) FROM borrow_record WHERE material_id=? AND status='借出中'");
    $stmt2->execute([$material_id]);
    $borrowed = $stmt2->fetchColumn() ?: 0;
    $available = $qty - $borrowed;
    if ($borrow_quantity > $available) die("库存不足");
    $stmt3 = $pdo->prepare("INSERT INTO borrow_record (material_id, borrow_quantity, borrower_unit, due_time, status) VALUES (?, ?, ?, ?, '借出中')");
    $stmt3->execute([$material_id, $borrow_quantity, $borrower_unit, $due_time]);
    logOperation("借出物资 ID: $material_id 数量 $borrow_quantity 给 $borrower_unit");
    header("Location: material.php?msg=借出成功");
}
?>