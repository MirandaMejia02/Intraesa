<?php
// admin/clientes.php
// Gestión de clientes + creación automática de usuario y wallet
// + estado activo/inactivo + edición y ajuste de créditos

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

// ---------------------------------
// Obtener/crear id del rol "client"
// ---------------------------------
function getClientRoleId(PDO $pdo): int {
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'client' LIMIT 1");
    $stmt->execute();
    $id = $stmt->fetchColumn();
    if (!$id) {
        $pdo->prepare("INSERT INTO roles (name) VALUES ('client')")->execute();
        return (int)$pdo->lastInsertId();
    }
    return (int)$id;
}

// ---------------------------------
// Manejo del formulario (crear / editar cliente)
// ---------------------------------
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // CREAR CLIENTE
        if ($action === 'create_client') {
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $company  = trim($_POST['company'] ?? '');

            if ($name === '' || $email === '' || $password === '') {
                throw new Exception('Nombre, correo y contraseña son obligatorios.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El correo no tiene un formato válido.');
            }
            if (strlen($password) < 6) {
                throw new Exception('La contraseña debe tener al menos 6 caracteres.');
            }

            // ¿Existe ya ese correo?
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ya existe un usuario con ese correo.');
            }

            $pdo->beginTransaction();

            // 1) Crear usuario
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $email, $hash]);
            $userId = (int)$pdo->lastInsertId();

            // 2) Asegurar rol client y asignarlo
            $clientRoleId = getClientRoleId($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO user_roles (user_id, role_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $clientRoleId]);

            // 3) Crear cliente (por defecto activo = 1 gracias a DEFAULT is_active)
            $stmt = $pdo->prepare("
                INSERT INTO clients (user_id, company)
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $company !== '' ? $company : null]);
            $clientId = (int)$pdo->lastInsertId();

            // 4) Crear wallet en 0 créditos
            $stmt = $pdo->prepare("
                INSERT INTO wallets (client_id, credits_balance)
                VALUES (?, 0)
            ");
            $stmt->execute([$clientId]);

            $pdo->commit();
            $ok = "Cliente creado correctamente con wallet en 0 créditos.";

        // EDITAR CLIENTE + AJUSTAR CRÉDITOS
        } elseif ($action === 'update_client') {
            $clientId     = (int)($_POST['client_id'] ?? 0);
            $name         = trim($_POST['name'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $company      = trim($_POST['company'] ?? '');
            $creditsDelta = (int)($_POST['credits_delta'] ?? 0); // puede ser negativo

            if ($clientId <= 0) {
                throw new Exception('Cliente inválido.');
            }
            if ($name === '' || $email === '') {
                throw new Exception('Nombre y correo son obligatorios.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El correo no tiene un formato válido.');
            }

            $pdo->beginTransaction();

            // 1) Obtener user_id del cliente
            $stmt = $pdo->prepare("SELECT user_id FROM clients WHERE id = ?");
            $stmt->execute([$clientId]);
            $userId = (int)$stmt->fetchColumn();

            if (!$userId) {
                throw new Exception('No se encontró el cliente.');
            }

            // 2) Actualizar datos básicos en users
            $stmt = $pdo->prepare("
                UPDATE users
                SET name = ?, email = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $userId]);

            // 3) Actualizar empresa en clients
            $stmt = $pdo->prepare("
                UPDATE clients
                SET company = ?
                WHERE id = ?
            ");
            $stmt->execute([$company !== '' ? $company : null, $clientId]);

            // 4) Ajustar créditos (si se indicó algo distinto de 0)
            if ($creditsDelta !== 0) {
                // Leer saldo actual
                $stmt = $pdo->prepare("
                    SELECT credits_balance
                    FROM wallets
                    WHERE client_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$clientId]);
                $current = (int)$stmt->fetchColumn();

                $newBalance = $current + $creditsDelta;
                if ($newBalance < 0) {
                    $newBalance = 0; // no dejamos negativos
                }

                $stmt = $pdo->prepare("
                    UPDATE wallets
                    SET credits_balance = ?
                    WHERE client_id = ?
                ");
                $stmt->execute([$newBalance, $clientId]);

                // Aquí podrías insertar en wallet_movements si quieres dejar bitácora
            }

            $pdo->commit();
            $ok = 'Cliente actualizado correctamente.';
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $err = $e->getMessage();
}

// ---------------------------------
// Listar clientes + saldo + estado + número de envíos
// ---------------------------------
$sql = "
    SELECT
        c.id AS client_id,
        u.name,
        u.email,
        c.company,
        COALESCE(w.credits_balance, 0) AS credits_balance,
        c.is_active,
        (
            SELECT COUNT(*)
            FROM shipments s
            WHERE s.client_id = c.id
        ) AS shipments_total
    FROM clients c
    JOIN users u ON u.id = c.user_id
    LEFT JOIN wallets w ON w.client_id = c.id
    ORDER BY c.id
";
$stmt = $pdo->query($sql);
$clients = $stmt->fetchAll();

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Intraesa | Clientes</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <!-- Bootstrap 5 -->
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
        .table td, .table th {
            vertical-align: middle;
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
                <a href="admins.php" class="list-group-item list-group-item-action<?= basename($_SERVER['PHP_SELF']) === 'admins.php' ? ' active' : '' ?>">
                    Administradores
                </a>
            </div>
        </aside>

        <!-- Contenido -->
        <main class="col-12 col-md-9 col-lg-10 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0">Clientes</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateClient">
                    Nuevo cliente
                </button>
            </div>

            <?php if ($ok): ?>
                <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
            <?php endif; ?>

            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Empresa</th>
                            <th class="text-end">Créditos</th>
                            <th class="text-center">Envíos</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end" style="min-width: 200px;">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    No hay clientes registrados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as $c): ?>
                                <?php $isActive = (int)($c['is_active'] ?? 1) === 1; ?>
                                <tr class="<?= $isActive ? '' : 'table-secondary' ?>">
                                    <td><?= (int)$c['client_id'] ?></td>
                                    <td><?= htmlspecialchars($c['name']) ?></td>
                                    <td><?= htmlspecialchars($c['email']) ?></td>
                                    <td><?= htmlspecialchars($c['company'] ?? '') ?></td>
                                    <td class="text-end"><?= (int)$c['credits_balance'] ?></td>
                                    <td class="text-center"><?= (int)($c['shipments_total'] ?? 0) ?></td>
                                    <td class="text-center">
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <!-- Botón Editar (abre modal) -->
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary me-1 btn-edit-client"
                                            data-id="<?= (int)$c['client_id'] ?>"
                                            data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>"
                                            data-email="<?= htmlspecialchars($c['email'], ENT_QUOTES) ?>"
                                            data-company="<?= htmlspecialchars($c['company'] ?? '', ENT_QUOTES) ?>"
                                            data-credits="<?= (int)$c['credits_balance'] ?>"
                                        >
                                            Editar
                                        </button>

                                        <!-- Activar / Desactivar -->
                                        <a
                                            href="cliente_toggle.php?id=<?= (int)$c['client_id'] ?>"
                                            class="btn btn-sm btn-outline-secondary"
                                            onclick="return confirm('¿Cambiar el estado de este cliente?');"
                                        >
                                            <?= $isActive ? 'Desactivar' : 'Activar' ?>
                                        </a>
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

<!-- MODAL: Nuevo cliente -->
<div class="modal fade" id="modalCreateClient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create_client">

                <div class="mb-3">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                    <div class="form-text">Mínimo 6 caracteres.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Empresa (opcional)</label>
                    <input type="text" name="company" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear cliente</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Editar cliente -->
<div class="modal fade" id="modalEditClient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update_client">
                <input type="hidden" name="client_id" id="editClientId">

                <div class="mb-3">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" name="name" id="editName" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="email" id="editEmail" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Empresa (opcional)</label>
                    <input type="text" name="company" id="editCompany" class="form-control">
                </div>

                <hr>

                <div class="mb-2">
                    <label class="form-label">Créditos actuales</label>
                    <div class="form-control-plaintext" id="editCreditsCurrent">0</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Ajuste de créditos
                        <small class="text-muted">(puede ser negativo, ej. -10)</small>
                    </label>
                    <input type="number" name="credits_delta" id="editCreditsDelta" class="form-control" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>
<div aria-activedescendant="cdn"action accesskey="POST"></div>





<!-- JS: jQuery + Bootstrap + lógica del modal -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(function () {
    $('.btn-edit-client').on('click', function () {
        const btn = $(this);

        $('#editClientId').val(btn.data('id'));
        $('#editName').val(btn.data('name'));
        $('#editEmail').val(btn.data('email'));
        $('#editCompany').val(btn.data('company'));
        $('#editCreditsCurrent').text(btn.data('credits'));
        $('#editCreditsDelta').val(0); // arrancar sin ajuste

        const modalEl = document.getElementById('modalEditClient');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    });
});
</script>
</body>
</html>
