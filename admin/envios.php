<?php
// admin/envios.php
// Gestión de envíos + cambio de estado vía AJAX + botón cancelar

require_once __DIR__ . '/../config/db.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Proteger página (solo admin)
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

// Obtener PDO
$pdo = db();

/**
 * Estados que el admin puede seleccionar en el <select>
 * (no incluimos "cancelled" para que no lo pongan desde ahí)
 */
$statusSelectOptions = [
    'verifying'          => 'Verificando',
    'pending_collection' => 'Pendiente de recolecta',
    'collected'          => 'Recolectado',
    'in_transit'         => 'En tránsito',
    'delivered'          => 'Entregado',
];

/**
 * Estados para mostrar en la etiqueta (badge)
 * Aquí sí agregamos "cancelled" para verlo bonito.
 */
$statusLabelMap = $statusSelectOptions + [
    'cancelled' => 'Cancelado',
];

// Traer envíos con datos del cliente
$sql = "
    SELECT
        s.*,
        u.name  AS client_name,
        u.email AS client_email
    FROM shipments s
    JOIN clients c ON c.id = s.client_id
    JOIN users   u ON u.id = c.user_id
    ORDER BY s.created_at DESC, s.id DESC
";
$shipments = $pdo->query($sql)->fetchAll();

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Intraesa | Envíos</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <!-- Bootstrap 5 + jQuery -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

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
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-3">
    <div class="container-fluid">
        <span class="navbar-brand mb-0 h1">Intraesa - Panel admin</span>
        <div class="d-flex align-items-center gap-2">
            <span class="text-light small">
                <?php
                $userName = $_SESSION['user']['name'] ?? ($_SESSION['userName'] ?? 'Admin');
                echo htmlspecialchars($userName);
                ?>
            </span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Cerrar sesión</a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <aside class="col-12 col-md-3 col-lg-2 sidebar mb-3">
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>">
                    Dashboard
                </a>
                <a href="planes.php" class="list-group-item list-group-item-action<?= basename($_SERVER['PHP_SELF']) === 'planes.php' ? ' active' : '' ?>">
                    Planes
                </a>
                <a href="clientes.php" class="list-group-item list-group-item-action<?= basename($_SERVER['PHP_SELF']) === 'clientes.php' ? ' active' : '' ?>">
                    Clientes
                </a>
                <a href="recargas.php" class="list-group-item list-group-item-action<?= basename($_SERVER['PHP_SELF']) === 'recargas.php' ? ' active' : '' ?>">
                    Recargas
                </a>
                <a href="envios.php" class="list-group-item list-group-item-action<?= basename($_SERVER['PHP_SELF']) === 'envios.php' ? ' active' : '' ?>">
                    Envíos
                </a>
            </div>
        </aside>

        <!-- Contenido -->
        <main class="col-12 col-md-9 col-lg-10 mb-5">
            <h1 class="h3 mb-4">Envíos</h1>

            <?php if (!empty($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_GET['msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-header">
                    <strong>Listado de envíos</strong>
                </div>
                <div class="card-body table-responsive">
                    <?php if (empty($shipments)): ?>
                        <p class="text-muted mb-0">Aún no hay envíos registrados.</p>
                    <?php else: ?>
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Correo</th>
                                <th>Receptor</th>
                                <th>Destino</th>
                                <th>Peso</th>
                                <th>Estado</th>
                                <th>Creado</th>
                                <th>Etiqueta</th>
                                <th style="min-width: 220px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($shipments as $s): ?>
                                <?php
                                $statusKey   = $s['status'];
                                $statusLabel = $statusLabelMap[$statusKey] ?? $statusKey;
                                $isPriority  = (int)($s['priority'] ?? 0) === 1;

                                // Envío "cerrado" = no se puede tocar (ni cancelar ni cambiar estado)
                                $isClosed = in_array($statusKey, ['delivered', 'cancelled'], true);
                                ?>
                                <tr data-shipment-id="<?= (int)$s['id'] ?>">
                                    <td><?= (int)$s['id'] ?></td>
                                    <td><?= htmlspecialchars($s['client_name']) ?></td>
                                    <td><?= htmlspecialchars($s['client_email']) ?></td>
                                    <td><?= htmlspecialchars($s['receiver_name']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($s['depto']) ?>,
                                        <?= htmlspecialchars($s['municipio']) ?>
                                        <?php if (!empty($s['address'])): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($s['address']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($s['weight_kg']) ?> kg</td>
                                    <td>
                                        <span class="badge mb-1 envio-status-label
                                            <?= $statusKey === 'delivered' ? 'bg-success'
                                                : ($statusKey === 'cancelled' ? 'bg-dark'
                                                : 'bg-secondary') ?>">
                                            <?= htmlspecialchars($statusLabel) ?>
                                        </span>
                                        <?php if ($isPriority): ?>
                                            <span class="badge bg-danger">Prioritario</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($s['created_at']) ?></td>
                                    <td>
                                        <?php if (!empty($s['label_code'])): ?>
                                            <code><?= htmlspecialchars($s['label_code']) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Select de estado (solo estados vivos) -->
                                        <select
                                            class="form-select form-select-sm envio-status-select mb-1"
                                            data-id="<?= (int)$s['id'] ?>"
                                            <?= $isClosed ? 'disabled' : '' ?>
                                        >
                                            <?php foreach ($statusSelectOptions as $key => $label): ?>
                                                <option
                                                    value="<?= htmlspecialchars($key) ?>"
                                                    <?= $key === $statusKey ? 'selected' : '' ?>
                                                >
                                                    <?= htmlspecialchars($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <!-- Botón cancelar: solo si NO está entregado ni cancelado -->
                                        <?php if (!$isClosed): ?>
                                            <a
                                                href="envios_cancel.php?id=<?= (int)$s['id'] ?>"
                                                class="btn btn-sm btn-outline-danger w-100 mb-1"
                                                onclick="return confirm('¿Cancelar este envío y devolver 1 crédito al cliente?');"
                                            >
                                                Cancelar
                                            </a>
                                        <?php endif; ?>

                                        <small class="text-muted envio-status-help d-block"></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(function () {
    $('.envio-status-select').on('change', function () {
        const $select    = $(this);
        const shipmentId = $select.data('id');
        const newStatus  = $select.val();
        const $row       = $select.closest('tr');
        const $label     = $row.find('.envio-status-label');
        const $help      = $row.find('.envio-status-help');

        $select.prop('disabled', true);
        $help.text('Actualizando...');

        $.post('envios_update_status.php', {
            id: shipmentId,
            status: newStatus
        }, function (resp) {
            if (resp && resp.ok) {
                if (resp.status_label) {
                    $label.text(resp.status_label);
                }
                $label.removeClass('bg-secondary bg-warning bg-info bg-success bg-dark');

                switch (newStatus) {
                    case 'verifying':
                        $label.addClass('bg-secondary'); break;
                    case 'pending_collection':
                        $label.addClass('bg-warning'); break;
                    case 'collected':
                    case 'in_transit':
                        $label.addClass('bg-info'); break;
                    case 'delivered':
                        $label.addClass('bg-success'); break;
                    // Por si el backend algún día devuelve "cancelled"
                    case 'cancelled':
                        $label.addClass('bg-dark'); break;
                    default:
                        $label.addClass('bg-secondary');
                }

                $help.text('Estado actualizado.');
            } else {
                $help.text(resp && resp.error ? resp.error : 'Error al actualizar.');
            }
        }, 'json').fail(function () {
            $help.text('Error de comunicación con el servidor.');
        }).always(function () {
            $select.prop('disabled', false);
            setTimeout(function () { $help.text(''); }, 2000);
        });
    });
});
</script>
</body>
</html>
