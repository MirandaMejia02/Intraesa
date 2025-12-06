<?php
// client/envios.php
// Panel de envíos del CLIENTE

require_once __DIR__ . '/../config/db.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Aseguramos que sea cliente logueado
if (function_exists('require_role')) {
    require_role('client');
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user']) && empty($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit;
    }
}

// Obtener PDO
$pdo = db();

// ---------- Obtener info del usuario / cliente ----------
$userId = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);
if (!$userId) {
    die('No se pudo identificar el usuario en sesión.');
}

// Buscar el cliente ligado a este user (incluye is_active)
$stmt = $pdo->prepare("SELECT id, company, is_active FROM clients WHERE user_id = ?");
$stmt->execute([$userId]);
$client = $stmt->fetch();

if (!$client) {
    die('Tu usuario no tiene un cliente asociado. Contacta al administrador.');
}

$clientId = (int)$client['id'];
$isActive = (int)($client['is_active'] ?? 1) === 1;

// Saldo actual
$stmt = $pdo->prepare("SELECT credits_balance FROM wallets WHERE client_id = ?");
$stmt->execute([$clientId]);
$creditsBalance = (int)$stmt->fetchColumn();

// Mensajes flash
$ok  = null;
$err = null;

if (!empty($_SESSION['flash_ok'])) {
    $ok = $_SESSION['flash_ok'];
    unset($_SESSION['flash_ok']);
}
if (!empty($_SESSION['flash_err'])) {
    $err = $_SESSION['flash_err'];
    unset($_SESSION['flash_err']);
}

// ---------- Función para generar código de etiqueta ----------
function generarLabelCode(PDO $pdo): string {
    while (true) {
        $code = 'PKG' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipments WHERE label_code = ?");
        $stmt->execute([$code]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $code;
        }
    }
}

// ---------- Manejar creación de nuevo envío ----------
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_shipment') {

            // Bloqueo: cliente inactivo no puede crear envíos
            if (!$isActive) {
                throw new Exception('Tu cuenta está inactiva. Contacta al administrador para seguir enviando paquetes.');
            }

            $receiverName = trim($_POST['receiver_name'] ?? '');
            $phone        = trim($_POST['phone'] ?? '');
            $address      = trim($_POST['address'] ?? '');
            $depto        = trim($_POST['depto'] ?? '');
            $municipio    = trim($_POST['municipio'] ?? '');
            $weightStr    = trim($_POST['weight_kg'] ?? '');
            $description  = trim($_POST['description'] ?? '');
            $priority     = isset($_POST['priority']) ? 1 : 0;

            if ($receiverName === '' || $phone === '' || $address === '' ||
                $depto === '' || $municipio === '' || $weightStr === '') {
                throw new Exception('Todos los campos son obligatorios excepto la descripción.');
            }

            if (!is_numeric($weightStr)) {
                throw new Exception('El peso debe ser un número.');
            }

            $weight = (float)$weightStr;
            if ($weight <= 0 || $weight > 2.0) {
                throw new Exception('El peso debe ser mayor que 0 y máximo 2.00 kg.');
            }

            $pdo->beginTransaction();

            // Bloqueo de wallet
            $stmt = $pdo->prepare("SELECT credits_balance FROM wallets WHERE client_id = ? FOR UPDATE");
            $stmt->execute([$clientId]);
            $currentBalance = (int)$stmt->fetchColumn();

            if ($currentBalance <= 0) {
                throw new Exception('No tienes créditos suficientes para generar un nuevo envío.');
            }

            // Descontar 1 crédito
            $stmt = $pdo->prepare("UPDATE wallets SET credits_balance = credits_balance - 1 WHERE client_id = ?");
            $stmt->execute([$clientId]);

            // Insertar envío
            $status    = 'verifying';
            $labelCode = generarLabelCode($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO shipments (
                    client_id,
                    receiver_name, phone, address,
                    depto, municipio,
                    weight_kg, description,
                    status, priority, label_code
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $clientId,
                $receiverName, $phone, $address,
                $depto, $municipio,
                $weight, $description !== '' ? $description : null,
                $status, $priority, $labelCode
            ]);

            $shipmentId = (int)$pdo->lastInsertId();

            // Evento inicial
            $stmt = $pdo->prepare("
                INSERT INTO shipment_events (shipment_id, status, note)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$shipmentId, $status, 'Envío creado por el cliente.']);

            $pdo->commit();

            $_SESSION['flash_ok'] = "Envío creado correctamente. Se ha descontado 1 crédito.";
            header('Location: envios.php');
            exit;
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_err'] = $e->getMessage();
    header('Location: envios.php');
    exit;
}

// ---------- Stats del dashboard del cliente ----------

// Envíos creados hoy
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM shipments
    WHERE client_id = ?
      AND DATE(created_at) = CURDATE()
");
$stmt->execute([$clientId]);
$sentToday = (int)$stmt->fetchColumn();

// Envíos pendientes (no entregados ni cancelados)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM shipments
    WHERE client_id = ?
      AND status NOT IN ('delivered', 'cancelled')
");

$stmt->execute([$clientId]);
$pendingCount = (int)$stmt->fetchColumn();

// Envíos entregados hoy (usando created_at)
// Envíos entregados hoy (con regla de la tarde → día siguiente)
$cutoff = '18:00:00';

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM shipments
    WHERE client_id = :client_id
      AND status = 'delivered'
      AND (
        CASE
          WHEN TIME(created_at) < :cutoff
            THEN DATE(created_at)
          ELSE DATE(DATE_ADD(created_at, INTERVAL 1 DAY))
        END
      ) = CURDATE()
");

$stmt->execute([
    ':client_id' => $clientId,
    ':cutoff'    => $cutoff,
]);

$deliveredToday = (int)$stmt->fetchColumn();


// Listado de envíos del cliente (OJO: sin client_confirmed)
$stmt = $pdo->prepare("
    SELECT id, receiver_name, depto, municipio,
           status, priority, label_code, created_at
    FROM shipments
    WHERE client_id = ?
    ORDER BY created_at DESC, id DESC
");
$stmt->execute([$clientId]);
$shipments = $stmt->fetchAll();

$statusLabels = [
    'verifying'          => 'Verificando',
    'pending_collection' => 'Pendiente de recolecta',
    'collected'          => 'Recolectado',
    'in_transit'         => 'En tránsito',
    'delivered'          => 'Entregado',
    'cancelled'          => 'Cancelado',
];

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Intraesa | Mis envíos</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background-color: #f5f5f5; }
        .sidebar {
            min-height: 100vh;
            border-right: 1px solid #ddd;
            background: #fff;
        }
        .sidebar .list-group-item.active {
            background:#0d6efd;
            border-color:#0d6efd;
        }
        .stat-card { border-radius: 12px; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-3">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">Intraesa - Panel cliente</span>
        <div class="d-flex align-items-center gap-3">
            <span class="text-light small">
                <?php
                $userName = $_SESSION['user']['name'] ?? ($_SESSION['userName'] ?? 'Cliente');
                echo htmlspecialchars($userName);
                ?>
            </span>
            <span class="badge bg-success">
                Saldo: <?= $creditsBalance ?> cr
            </span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Cerrar sesión</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar simple -->
        <aside class="col-12 col-md-3 col-lg-2 sidebar mb-3">
            <div class="list-group list-group-flush">
                <a href="envios.php" class="list-group-item list-group-item-action active">
                    Mis envíos
                </a>
            </div>
        </aside>

        <!-- Contenido -->
        <main class="col-12 col-md-9 col-lg-10 mb-5">
            <h1 class="h3 mb-4">Mis envíos</h1>

            <?php if ($ok): ?>
                <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
            <?php endif; ?>

            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <!-- Stats del cliente -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Envíos creados hoy</h6>
                            <h3 class="mb-0"><?= $sentToday ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Pendientes de entrega</h6>
                            <h3 class="mb-0"><?= $pendingCount ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Entregados hoy</h6>
                            <h3 class="mb-0"><?= $deliveredToday ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mensaje si el cliente está inactivo -->
            <?php if (!$isActive): ?>
                <div class="alert alert-warning">
                    Tu cuenta está inactiva. Contacta al administrador para seguir enviando paquetes.
                </div>
            <?php else: ?>

                <!-- Formulario nuevo envío -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Nuevo envío</span>
                        <span class="small text-muted">
                            Cada envío descuenta <strong>1 crédito</strong> (saldo actual: <?= $creditsBalance ?>).
                        </span>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="action" value="create_shipment">

                            <div class="col-md-6">
                                <label class="form-label">Nombre del receptor</label>
                                <input type="text" name="receiver_name" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="phone" class="form-control" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Dirección</label>
                                <textarea name="address" class="form-control" rows="2" required></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Departamento</label>
                                <input type="text" name="depto" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Municipio</label>
                                <input type="text" name="municipio" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Peso (kg, máx 2.00)</label>
                                <input type="number" name="weight_kg" step="0.01" min="0.01" max="2.00" class="form-control" required>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Descripción (opcional)</label>
                                <input type="text" name="description" class="form-control">
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="priority" id="priority">
                                    <label class="form-check-label" for="priority">
                                        Envío prioritario
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    <?= $creditsBalance <= 0 ? 'disabled' : '' ?>
                                >
                                    Crear envío
                                </button>
                                <?php if ($creditsBalance <= 0): ?>
                                    <span class="text-danger ms-2 small">
                                        No tienes créditos suficientes. Contacta a la empresa para recargar.
                                    </span>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Tabla de envíos -->
            <div class="card">
                <div class="card-header">
                    Historial de envíos
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Receptor</th>
                            <th>Destino</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($shipments)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    Aún no has generado envíos.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($shipments as $s): ?>
                                <?php
                                $statusKey   = $s['status'];
                                $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
                                $isPriority  = (int)$s['priority'] === 1;
                                ?>
                                <tr>
                                    <td><?= $s['id'] ?></td>
                                    <td>
                                        <?php if ($s['label_code']): ?>
                                            <code><?= htmlspecialchars($s['label_code']) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">Sin código</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($s['receiver_name']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($s['depto']) ?>,
                                        <?= htmlspecialchars($s['municipio']) ?>
                                    </td>
                                    <?php
                                        $statusKey   = $s['status'];
                                        $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
                                        $isPriority  = (int)$s['priority'] === 1;

                                        $badgeClass = 'bg-secondary';
                                        if ($statusKey === 'delivered') {
                                            $badgeClass = 'bg-success';
                                        } elseif ($statusKey === 'cancelled') {
                                            $badgeClass = 'bg-dark';
                                        }
                                        ?>
                                        <td>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars($statusLabel) ?>
                                            </span>
                                            <?php if ($isPriority): ?>
                                                <span class="badge bg-danger">Prioritario</span>
                                            <?php endif; ?>
                                        </td>
                                    <td><?= $s['created_at'] ?></td>
                                    <td class="d-flex gap-1">
                                        <?php if ($s['label_code']): ?>
                                            <a
                                                href="etiqueta.php?id=<?= $s['id'] ?>"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-secondary"
                                            >
                                                Vista previa
                                            </a>
                                            <a
                                                href="etiqueta_pdf.php?id=<?= $s['id'] ?>"
                                                target="_blank"
                                                class="btn btn-sm btn-primary"
                                            >
                                                PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
