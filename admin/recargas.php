<?php
// admin/recargas.php
// Recargas de créditos (top_ups) usando planes y wallets

require_once __DIR__ . '/../config/db.php';
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

$ok  = null;
$err = null;


// leer mensaje flash si existe
if (!empty($_SESSION['flash_ok'])) {
    $ok = $_SESSION['flash_ok'];
    unset($_SESSION['flash_ok']);
}


// ---------------------------------
// Cargar lista de clientes (para el select)
// ---------------------------------
$sqlClients = "
    SELECT
        c.id AS client_id,
        u.name,
        u.email,
        COALESCE(w.credits_balance, 0) AS credits_balance
    FROM clients c
    JOIN users u ON u.id = c.user_id
    LEFT JOIN wallets w ON w.client_id = c.id
    ORDER BY u.name
";
$clients = $pdo->query($sqlClients)->fetchAll();

// ---------------------------------
// Cargar lista de planes activos
// ---------------------------------
$sqlPlans = "
    SELECT id, name, credits, price_usd
    FROM plans
    WHERE is_active = 1
    ORDER BY credits DESC, id
";
$plans = $pdo->query($sqlPlans)->fetchAll();

// ---------------------------------
// Manejo del formulario de recarga
// ---------------------------------
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'top_up') {
            $clientId = (int)($_POST['client_id'] ?? 0);
            $planId   = (int)($_POST['plan_id'] ?? 0);

            if ($clientId <= 0 || $planId <= 0) {
                throw new Exception('Debes seleccionar un cliente y un plan.');
            }

            // Verificar que el cliente exista
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
            $stmt->execute([$clientId]);
            if (!$stmt->fetchColumn()) {
                throw new Exception('El cliente seleccionado no existe.');
            }

            // Obtener créditos del plan
            $stmt = $pdo->prepare("SELECT credits FROM plans WHERE id = ? AND is_active = 1");
            $stmt->execute([$planId]);
            $credits = (int)$stmt->fetchColumn();
            if ($credits <= 0) {
                throw new Exception('El plan seleccionado no es válido o no está activo.');
            }

            $pdo->beginTransaction();

            // 1) Registrar top_up
            $stmt = $pdo->prepare("
                INSERT INTO top_ups (client_id, plan_id, credits_added)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$clientId, $planId, $credits]);

            // 2) Actualizar wallet (si no existe, la crea)
            $stmt = $pdo->prepare("
                INSERT INTO wallets (client_id, credits_balance)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    credits_balance = credits_balance + VALUES(credits_balance)
            ");
            $stmt->execute([$clientId, $credits]);

           $pdo->commit();

        // guardar mensaje flash en sesión y redirigir (PRG)
        $_SESSION['flash_ok'] = "Recarga realizada correctamente (+{$credits} créditos).";

        header('Location: recargas.php');
        exit;

        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $err = $e->getMessage();
}

// ---------------------------------
// Listar historial de recargas
// ---------------------------------
$sqlTopups = "
    SELECT
        t.id,
        t.created_at,
        c.id   AS client_id,
        u.name AS client_name,
        u.email AS client_email,
        p.name AS plan_name,
        t.credits_added
    FROM top_ups t
    JOIN clients c ON c.id = t.client_id
    JOIN users u   ON u.id = c.user_id
    JOIN plans p   ON p.id = t.plan_id
    ORDER BY t.created_at DESC, t.id DESC
";
$topups = $pdo->query($sqlTopups)->fetchAll();

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Intraesa | Recargas</title>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0">Recargas de créditos</h1>
            </div>

            <?php if ($ok): ?>
                <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
            <?php endif; ?>

            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <!-- Formulario de nueva recarga -->
            <div class="card mb-4">
                <div class="card-header">
                    Nueva recarga
                </div>
                <div class="card-body">
                    <?php if (empty($clients) || empty($plans)): ?>
                        <p class="text-muted">
                            Debes tener al menos un cliente y un plan activo para realizar recargas.
                        </p>
                    <?php else: ?>
                        <form method="post" class="row g-3">
                            <input type="hidden" name="action" value="top_up">

                            <div class="col-md-5">
                                <label class="form-label">Cliente</label>
                                <select name="client_id" class="form-select" required>
                                    <option value="">Selecciona un cliente…</option>
                                    <?php foreach ($clients as $c): ?>
                                        <option value="<?= $c['client_id'] ?>">
                                            <?= htmlspecialchars($c['name']) ?>
                                            (<?= htmlspecialchars($c['email']) ?>)
                                            - Saldo: <?= (int)$c['credits_balance'] ?> cr.
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Plan</label>
                                <select name="plan_id" class="form-select" required>
                                    <option value="">Selecciona un plan…</option>
                                    <?php foreach ($plans as $p): ?>
                                        <option value="<?= $p['id'] ?>">
                                            <?= htmlspecialchars($p['name']) ?>
                                            (<?= (int)$p['credits'] ?> créditos, $<?= number_format((float)$p['price_usd'], 2) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100">
                                    Recargar
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historial de recargas -->
            <div class="card">
                <div class="card-header">
                    Historial de recargas
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Correo</th>
                            <th>Plan</th>
                            <th>Créditos</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($topups)): ?>
                            <tr><td colspan="6" class="text-center text-muted">Sin recargas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($topups as $t): ?>
                                <tr>
                                    <td><?= $t['id'] ?></td>
                                    <td><?= $t['created_at'] ?></td>
                                    <td><?= htmlspecialchars($t['client_name']) ?></td>
                                    <td><?= htmlspecialchars($t['client_email']) ?></td>
                                    <td><?= htmlspecialchars($t['plan_name']) ?></td>
                                    <td><?= (int)$t['credits_added'] ?></td>
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
