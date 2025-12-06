<?php
// client/etiqueta.php
// Muestra la etiqueta imprimible de un envío del cliente

require_once __DIR__ . '/../config/db.php';
require_role('client');

$userId = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);
if (!$userId) {
    die('Usuario no identificado.');
}

$shipmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($shipmentId <= 0) {
    die('Envío no válido.');
}

$pdo = db(); 

// Buscar el cliente asociado a este usuario
$stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ?");
$stmt->execute([$userId]);
$client = $stmt->fetch();
if (!$client) {
    die('No se encontró cliente asociado.');
}
$clientId = (int)$client['id'];

// Traer el envío, asegurando que sea de este cliente
$stmt = $pdo->prepare("
    SELECT s.*,
           u.name   AS client_name,
           u.email  AS client_email,
           c.company
    FROM shipments s
    JOIN clients c ON c.id = s.client_id
    JOIN users u   ON u.id = c.user_id
    WHERE s.id = ? AND s.client_id = ?
    LIMIT 1
");
$stmt->execute([$shipmentId, $clientId]);
$shipment = $stmt->fetch();

if (!$shipment) {
    die('Envío no encontrado.');
}

$labelCode = $shipment['label_code'] ?: ('PKG-' . $shipment['id']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiqueta envío <?= htmlspecialchars($labelCode) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#f5f5f5;
            margin:0;
            padding:20px;
        }
        .label-wrapper {
            max-width: 480px;
            margin:0 auto;
            background:#fff;
            border:1px solid #ccc;
            border-radius:8px;
            padding:16px 20px;
        }
        .label-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:12px;
        }
        .label-header h1 {
            font-size:1.1rem;
            margin:0;
        }
        .code {
            font-size:1.3rem;
            font-weight:700;
            letter-spacing:2px;
        }
        .section {
            margin-bottom:10px;
        }
        .section h2 {
            font-size:0.9rem;
            text-transform:uppercase;
            color:#666;
            margin:0 0 4px 0;
        }
        .section p {
            margin:0;
            font-size:0.95rem;
        }
        .meta {
            font-size:0.8rem;
            color:#777;
            margin-top:10px;
        }
        .actions {
            margin-top:15px;
            text-align:center;
        }
        .btn-print {
            padding:8px 16px;
            border-radius:6px;
            border:none;
            background:#0d6efd;
            color:#fff;
            font-weight:600;
            cursor:pointer;
        }

        @media print {
            body {
                background:#fff;
                padding:0;
            }
            .actions {
                display:none;
            }
            .label-wrapper {
                border:none;
                box-shadow:none;
                margin:0;
                border-radius:0;
            }
        }
    </style>
</head>
<body>

<div class="label-wrapper">
    <div class="label-header">
        <div>
            <h1>Intraesa - Etiqueta de envío</h1>
            <div class="meta">
                Cliente: <?= htmlspecialchars($shipment['client_name']) ?>
                <?php if ($shipment['company']): ?>
                    (<?= htmlspecialchars($shipment['company']) ?>)
                <?php endif; ?>
            </div>
        </div>
        <div class="code">
            <?= htmlspecialchars($labelCode) ?>
        </div>
    </div>

    <div class="section">
        <h2>Destinatario</h2>
        <p><strong><?= htmlspecialchars($shipment['receiver_name']) ?></strong></p>
        <p><?= htmlspecialchars($shipment['address']) ?></p>
        <p><?= htmlspecialchars($shipment['depto']) ?>, <?= htmlspecialchars($shipment['municipio']) ?></p>
        <p>Tel: <?= htmlspecialchars($shipment['phone']) ?></p>
    </div>

    <div class="section">
        <h2>Detalle del paquete</h2>
        <p>Peso: <?= htmlspecialchars($shipment['weight_kg']) ?> kg</p>
        <?php if (!empty($shipment['description'])): ?>
            <p>Descripción: <?= htmlspecialchars($shipment['description']) ?></p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Información</h2>
        <p>Fecha de creación: <?= htmlspecialchars($shipment['created_at']) ?></p>
        <p>Prioridad: <?= $shipment['priority'] ? 'Sí' : 'No' ?></p>
        <p>Estado actual: <?= htmlspecialchars($shipment['status']) ?></p>
    </div>

    <div class="actions">
        <button class="btn-print" onclick="window.print()">Imprimir</button>
    </div>
</div>

</body>
</html>
