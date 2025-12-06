<?php
// admin/planes.php
// CRUD sencillo de planes usando SOLO: id, name, price_usd, credits, is_active

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

// ------------------------------
// Manejo de formularios
// ------------------------------
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name    = trim($_POST['name'] ?? '');
            $price   = (float)($_POST['price_usd'] ?? 0);
            $credits = (int)($_POST['credits'] ?? 0);
            $active  = isset($_POST['is_active']) ? 1 : 0;

            if ($name === '') {
                throw new Exception('El nombre del plan es obligatorio.');
            }
            if ($price < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }
            if ($credits < 0) {
                throw new Exception('Los créditos no pueden ser negativos.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO plans (name, price_usd, credits, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $price, $credits, $active]);

            $ok = "Plan creado correctamente.";
        }

        if ($action === 'update') {
            $id      = (int)($_POST['id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $price   = (float)($_POST['price_usd'] ?? 0);
            $credits = (int)($_POST['credits'] ?? 0);
            $active  = isset($_POST['is_active']) ? 1 : 0;

            if ($id <= 0) {
                throw new Exception('ID de plan inválido.');
            }
            if ($name === '') {
                throw new Exception('El nombre del plan es obligatorio.');
            }
            if ($price < 0) {
                throw new Exception('El precio no puede ser negativo.');
            }
            if ($credits < 0) {
                throw new Exception('Los créditos no pueden ser negativos.');
            }

            $stmt = $pdo->prepare("
                UPDATE plans
                SET name = ?, price_usd = ?, credits = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $price, $credits, $active, $id]);

            $ok = "Plan actualizado correctamente.";
        }

        if ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID de plan inválido.');
            }

            $stmt = $pdo->prepare("
                UPDATE plans
                SET is_active = 1 - is_active
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            $ok = "Estado del plan actualizado.";
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID de plan inválido.');
            }

            $stmt = $pdo->prepare("DELETE FROM plans WHERE id = ?");
            $stmt->execute([$id]);

            $ok = "Plan eliminado.";
        }
    }
} catch (Throwable $e) {
    $err = $e->getMessage();
}

// ------------------------------
// Listar planes (SOLO columnas usadas)
// ------------------------------
$stmt = $pdo->query("
    SELECT id, name, price_usd, credits, is_active
    FROM plans
    ORDER BY id
");
$plans = $stmt->fetchAll();

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Intraesa | Planes</title>
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


        <!-- Contenido principal -->
        <main class="col-12 col-md-9 col-lg-10 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0">Planes</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
                    Nuevo plan
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
                            <th>Precio</th>
                            <th>Créditos</th>
                            <th>Activo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($plans)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No hay planes registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($plans as $p): ?>
                                <tr>
                                    <td><?= $p['id'] ?></td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td>$<?= number_format((float)$p['price_usd'], 2) ?></td>
                                    <td><?= (int)$p['credits'] ?></td>
                                    <td>
                                        <?php if ($p['is_active']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <!-- Editar -->
                                        <button
                                            class="btn btn-sm btn-info btn-edit"
                                            data-id="<?= $p['id'] ?>"
                                            data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                                            data-price="<?= $p['price_usd'] ?>"
                                            data-credits="<?= $p['credits'] ?>"
                                            data-active="<?= $p['is_active'] ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEdit"
                                        >
                                            Editar
                                        </button>

                                        <!-- Activar / desactivar -->
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                Activar/Desactivar
                                            </button>
                                        </form>

                                        <!-- Eliminar -->
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('¿Seguro que deseas eliminar este plan?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                Eliminar
                                            </button>
                                        </form>
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

<!-- MODAL: Crear -->
<div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create">

                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Precio USD</label>
                    <input type="number" step="0.01" min="0" name="price_usd" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Créditos</label>
                    <input type="number" min="0" name="credits" class="form-control" required>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="create_is_active" checked>
                    <label class="form-check-label" for="create_is_active">
                        Activo
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Editar -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">

                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Precio USD</label>
                    <input type="number" step="0.01" min="0" name="price_usd" id="edit_price" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Créditos</label>
                    <input type="number" min="0" name="credits" id="edit_credits" class="form-control" required>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                    <label class="form-check-label" for="edit_is_active">
                        Activo
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function() {
    $('.btn-edit').on('click', function() {
        const btn = $(this);
        $('#edit_id').val(btn.data('id'));
        $('#edit_name').val(btn.data('name') || '');
        $('#edit_price').val(btn.data('price') || 0);
        $('#edit_credits').val(btn.data('credits') || 0);
        $('#edit_is_active').prop('checked', String(btn.data('active')) === '1');
    });
});
</script>
</body>
</html>
