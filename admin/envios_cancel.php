<?php
// admin/envios_cancel.php
require_once __DIR__ . '/../config/db.php';

if (function_exists('require_role')) {
    require_role('admin');
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user']) && empty($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: envios.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    header('Location: envios.php?msg=' . urlencode('ID de envío inválido.'));
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Bloqueamos el envío + wallet del cliente
    $stmt = $pdo->prepare("
        SELECT s.id, s.client_id, s.status, w.credits_balance
        FROM shipments s
        JOIN wallets w ON w.client_id = s.client_id
        WHERE s.id = ?
        FOR UPDATE
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new Exception('Envío no encontrado.');
    }

    // 🔒 Si ya está entregado o cancelado, no se puede devolver ni tocar
    if (in_array($row['status'], ['delivered', 'cancelled'], true)) {
        throw new Exception('Este envío ya está cerrado y no se puede cancelar.');
    }

    // Cambiar estado a cancelado
    $stmt = $pdo->prepare("UPDATE shipments SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$id]);

    // Devolver 1 crédito
    $stmt = $pdo->prepare("UPDATE wallets SET credits_balance = credits_balance + 1 WHERE client_id = ?");
    $stmt->execute([$row['client_id']]);


    $stmt = $pdo->prepare("
        INSERT INTO shipment_events (shipment_id, status, note)
        VALUES (?, 'cancelled', 'Envío cancelado por admin; se devolvió 1 crédito.')
    ");
    $stmt->execute([$id]);
    

    $pdo->commit();

    header('Location: envios.php?msg=' . urlencode('Envío cancelado y 1 crédito devuelto.'));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: envios.php?msg=' . urlencode('Error al cancelar: ' . $e->getMessage()));
}
exit;
