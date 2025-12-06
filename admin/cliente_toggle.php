<?php
// admin/cliente_toggle.php
require_once __DIR__ . '/../config/db.php';

if (function_exists('require_role')) {
    require_role('admin'); // solo admin puede tocar esto
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user']) && empty($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }
}

$pdo = db();

$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($clientId <= 0) {
    header('Location: clientes.php');
    exit;
}

// Leer estado actual
$stmt = $pdo->prepare("SELECT is_active FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: clientes.php');
    exit;
}

$currentStatus = (int)$client['is_active'];
$newStatus     = ($currentStatus === 1) ? 0 : 1;

$stmt = $pdo->prepare("UPDATE clients SET is_active = ? WHERE id = ?");
$stmt->execute([$newStatus, $clientId]);

// si quieres mensaje visual:
$msg = $newStatus === 1 ? 'Cliente activado.' : 'Cliente desactivado.';
header('Location: clientes.php?msg=' . urlencode($msg));
exit;
