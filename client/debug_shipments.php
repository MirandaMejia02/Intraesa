<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();

$stmt = $pdo->query("SELECT DATABASE() AS db");
$dbName = $stmt->fetchColumn();
echo "BD actual desde PHP: " . htmlspecialchars($dbName) . "<br><br>";

$stmt = $pdo->query("SHOW COLUMNS FROM shipments");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Columnas en shipments vistas por PHP:<br><pre>";
var_dump($cols);
echo "</pre>";
