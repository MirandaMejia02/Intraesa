<?php
// admin/dashboard.php
// Dashboard sencillo del administrador

require_once __DIR__ . '/../config/db.php';

// Evitar cache del navegador (por el tema de logout, etc.)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Proteger página
if (function_exists('require_login')) {
    require_login();
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
if (function_exists('db')) {
    $pdo = db();
} else {
    if (!isset($pdo)) {
        die('No hay conexión PDO. Revisa config/db.php');
    }
}

/* =========================================
   STATS SIMPLES
   ========================================= */

// total de clientes
$totalClients = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

// total de planes activos
$totalActivePlans = (int)$pdo->query("SELECT COUNT(*) FROM plans WHERE is_active = 1")->fetchColumn();

// créditos totales en wallets
$totalCredits = (int)$pdo->query("SELECT COALESCE(SUM(credits_balance), 0) FROM wallets")->fetchColumn();

/* =========================================
   ENVÍOS POR ESTADO (para el dashboard)
   ========================================= */

$shipmentsByStatus = [];

$stmt = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM shipments
    GROUP BY status
");
while ($row = $stmt->fetch()) {
    $shipmentsByStatus[$row['status']] = (int)$row['total'];
}

/* =========================================
   DATOS PARA GRÁFICA: CRÉDITOS POR CLIENTE
   ========================================= */

$clientLabels  = [];
$clientCredits = [];

$sqlClientsChart = "
    SELECT
        u.name AS client_name,
        COALESCE(w.credits_balance, 0) AS credits_balance
    FROM clients c
    JOIN users u ON u.id = c.user_id
    LEFT JOIN wallets w ON w.client_id = c.id
    ORDER BY credits_balance DESC, client_name ASC
";
$rows = $pdo->query($sqlClientsChart)->fetchAll();

foreach ($rows as $row) {
    $clientLabels[]  = $row['client_name'];
    $clientCredits[] = (int)$row['credits_balance'];
}

// Pasar a JSON para JS
$clientLabelsJson  = json_encode($clientLabels, JSON_UNESCAPED_UNICODE);
$clientCreditsJson = json_encode($clientCredits, JSON_NUMERIC_CHECK);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Intraesa | Dashboard</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js para la gráfica -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            color:#fff;
        }
        .stat-card {
            border-radius: 12px;
        }
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
                <a href="dashboard.php" class="list-group-item list-group-item-action active">
                    Dashboard
                </a>
                <a href="planes.php" class="list-group-item list-group-item-action">
                    Planes
                </a>
                <a href="clientes.php" class="list-group-item list-group-item-action">
                    Clientes
                </a>
                <a href="recargas.php" class="list-group-item list-group-item-action">
                    Recargas
                </a>
                <a href="envios.php" class="list-group-item list-group-item-action">
                    Envíos
                </a>
            </div>
        </aside>

        <!-- Contenido principal -->
        <main class="col-12 col-md-9 col-lg-10 mb-5">
            <h1 class="h3 mb-4">Dashboard</h1>

            <!-- Tarjetas de stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Clientes</h6>
                            <h3 class="mb-0"><?= $totalClients ?></h3>
                            <small class="text-muted">Total de clientes registrados</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Planes activos</h6>
                            <h3 class="mb-0"><?= $totalActivePlans ?></h3>
                            <small class="text-muted">Planes disponibles para recargar</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Créditos en circulación</h6>
                            <h3 class="mb-0"><?= $totalCredits ?></h3>
                            <small class="text-muted">Suma de créditos en todas las wallets</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Envíos por estado -->
            <div class="card mb-4">
                <div class="card-header">Envíos por estado</div>
                <div class="card-body">
                    <?php if (empty($shipmentsByStatus)): ?>
                        <p class="text-muted mb-0">
                            Aún no hay envíos registrados. Cuando implementemos el módulo de envíos,
                            verás aquí un resumen por estado (En verificación, Pendiente de recolección, etc.).
                        </p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($shipmentsByStatus as $status => $total): ?>
                                <div class="col-md-3">
                                    <div class="border rounded p-2 text-center">
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars(shipment_status_label($status)) ?>
                                        </div>
                                        <div class="fs-4"><?= $total ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gráfica: Créditos por cliente -->
            <div class="card">
                <div class="card-header">
                    Créditos por cliente
                </div>
                <div class="card-body">
                    <?php if (empty($clientLabels)): ?>
                        <p class="text-muted mb-0">
                            Aún no hay clientes con wallet registrada.
                        </p>
                    <?php else: ?>
                        <div style="height:260px;">
                            <canvas id="clientsCreditsChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (!empty($clientLabels)): ?>
<script>
// Datos desde PHP
const clientLabels  = <?= $clientLabelsJson ?>;
const clientCredits = <?= $clientCreditsJson ?>;

const ctx = document.getElementById('clientsCreditsChart').getContext('2d');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: clientLabels,
        datasets: [{
            label: 'Créditos',
            data: clientCredits
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 }
            }
        }
    }
});
</script>
<?php endif; ?>
</body>
</html>
