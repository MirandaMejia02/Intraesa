<?php
// admin/envios_update_status.php
// Endpoint AJAX para actualizar estado de un envío

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// Proteger ruta: solo admin
if (function_exists('require_role')) {
    require_role('admin');
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user']) && empty($_SESSION['user_id'])) {
        echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
        exit;
    }
}

$pdo = db();

// Estados permitidos desde el select del admin
$allowedStatuses = [
    'verifying'          => 'Verificando',
    'pending_collection' => 'Pendiente de recolecta',
    'collected'          => 'Recolectado',
    'in_transit'         => 'En tránsito',
    'delivered'          => 'Entregado',
];

// Leer datos
$id        = isset($_POST['id'])     ? (int)$_POST['id']     : 0;
$newStatus = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($id <= 0 || $newStatus === '') {
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
    exit;
}

if (!isset($allowedStatuses[$newStatus])) {
    echo json_encode(['ok' => false, 'error' => 'Estado no permitido.']);
    exit;
}

// No permitir usar este endpoint para cancelar
if ($newStatus === 'cancelled') {
    echo json_encode(['ok' => false, 'error' => 'La cancelación se hace por otro proceso.']);
    exit;
}

try {
    // Buscar envío
    $stmt = $pdo->prepare("
        SELECT id, client_id, status
        FROM shipments
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $shipment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shipment) {
        echo json_encode(['ok' => false, 'error' => 'Envío no encontrado.']);
        exit;
    }

    // Si ya está entregado o cancelado, ya no se toca
    if (in_array($shipment['status'], ['delivered', 'cancelled'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Este envío ya está cerrado.']);
        exit;
    }

    // Actualizar estado
    $stmt = $pdo->prepare("
        UPDATE shipments
        SET status = ?
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $id]);

    // Registrar evento simple en la bitácora (si tienes tabla shipment_events)
    $stmt = $pdo->prepare("
        INSERT INTO shipment_events (shipment_id, status, note)
        VALUES (?, ?, ?)
    ");
    $note = 'Estado cambiado por admin a ' . $allowedStatuses[$newStatus];
    $stmt->execute([$id, $newStatus, $note]);

    echo json_encode([
        'ok'           => true,
        'status'       => $newStatus,
        'status_label' => $allowedStatuses[$newStatus],
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Error en servidor.']);
}
