<?php
// client/confirmar_envio.php
// El cliente confirma que recibió un envío entregado

require_once __DIR__ . '/../config/db.php';
require_role('client');

$pdo = db();

// 1) Validar request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: envios.php');
    exit;
}

$shipmentId = isset($_POST['shipment_id']) ? (int)$_POST['shipment_id'] : 0;
if ($shipmentId <= 0) {
    header('Location: envios.php?msg=' . urlencode('Envío no válido.'));
    exit;
}

// 2) Obtener user_id actual y client_id asociado
$userId = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);
if (!$userId) {
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: envios.php?msg=' . urlencode('No se encontró el cliente.'));
    exit;
}

$clientId = (int)$client['id'];

try {
    $pdo->beginTransaction();

    // 3) Verificar que el envío es del cliente, está entregado y no confirmado
    $stmt = $pdo->prepare("
        SELECT id, status, client_confirmed
        FROM shipments
        WHERE id = ? AND client_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$shipmentId, $clientId]);
    $shipment = $stmt->fetch();

    if (!$shipment) {
        throw new Exception('Envío no encontrado.');
    }

    if ($shipment['status'] !== 'delivered') {
        throw new Exception('Solo se pueden confirmar envíos entregados.');
    }

    if (!empty($shipment['client_confirmed'])) {
        // Ya estaba confirmado, no pasa nada
        $pdo->commit();
        header('Location: envios.php?msg=' . urlencode('Este envío ya estaba confirmado.'));
        exit;
    }

    // 4) Marcar como confirmado por el cliente
    $stmt = $pdo->prepare("
        UPDATE shipments
        SET client_confirmed = 1, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$shipmentId]);

    // 5) Registrar evento en historial
    $stmt = $pdo->prepare("
        INSERT INTO shipment_events (shipment_id, status, note)
        VALUES (?, 'delivered', 'Confirmado por el cliente')
    ");
    $stmt->execute([$shipmentId]);

    $pdo->commit();

    header('Location: envios.php?msg=' . urlencode('¡Gracias! Has confirmado la recepción del envío.'));
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Para el usuario no mostramos el error técnico
    header('Location: envios.php?msg=' . urlencode('No se pudo confirmar el envío.'));
    exit;
}
