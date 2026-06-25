<?php
require_once 'config.php';
$unit_id = intval($_GET['unit_id']);
$stmt = $pdo->prepare("SELECT id, name FROM storehouse WHERE unit_id = ?");
$stmt->execute([$unit_id]);
echo json_encode($stmt->fetchAll());
?>