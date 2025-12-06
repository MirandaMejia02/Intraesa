<?php
// admin/admins.php
// Gestión de administradores (usuarios con rol "admin")

require_once __DIR__ . '/../config/db.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Solo admins
require_role('admin');

$pdo = db();
$ok  = null;
$err = null;

// =============================
// Helper: obtener id de un rol
// =============================
function getRoleId(PDO $pdo, string $roleName): int {
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
    $stmt->execute([$roleName]);
    $id = $stmt->fetchColumn();

    if (!$id) {
        // Si no existe el rol, lo creamos
        $stmt = $pdo->prepare("INSERT INTO roles (name) VALUES (?)");
        $stmt->execute([$roleName]);
        $id = $pdo->lastInsertId();
    }

    return (int)$id;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // ===========================
        // Crear un nuevo administrador
        // ===========================
        if ($action === 'create_admin') {
            $name     = trim($_POST['name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if ($name === '' || $email === '' || $password === '') {
                throw new Exception('Nombre, correo y contraseña son obligatorios.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El correo no tiene un formato válido.');
            }
            if (strlen($password) < 6) {
                throw new Exception('La contraseña debe tener al menos 6 caracteres.');
            }

            // ¿Correo ya existe?
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

            // 2) Asignar rol admin
            $adminRoleId = getRoleId($pdo, 'admin');
            $stmt = $pdo->prepare("
                INSERT INTO user_roles (user_id, role_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $adminRoleId]);

            $pdo->commit();
            $ok = 'Administrador creado correctamente.';
        }

        // ===========================
        // Poner / quitar rol admin
        // ===========================
        if ($action === 'toggle_admin') {
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                throw new Exception('Usuario inválido.');
            }

            $currentUserId = $_SESSION['user']['id'] ?? null;
            if ($userId === (int)$currentUserId) {
                throw new Exception('No puedes quitarte el rol admin a ti mismo 🙃');
            }

            $adminRoleId = getRoleId($pdo, 'admin');

            $pdo->beginTransaction();

            // ¿Ya es admin?
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM user_roles
                WHERE user_id = ? AND role_id = ?
            ");
            $stmt->execute([$userId, $adminRoleId]);
            $hasAdmin = (int)$stmt->fetchColumn() > 0;

            if ($hasAdmin) {
                // Quitar rol admin
                $stmt = $pdo->prepare("
                    DELETE FROM user_roles
                    WHERE user_id = ? AND role_id = ?
                ");
                $stmt->execute([$userId, $adminRoleId]);
                $ok = 'El usuario ya no es administrador.';
            } else {
                // Poner rol admin
                $stmt = $pdo->prepare("
                    INSERT INTO user_roles (user_id, role_id)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE role_id = role_id
                ");
                $stmt->execute([$userId, $adminRoleId]);
                $ok = 'Usuario marcado como administrador.';
            }

            $pdo->commit();
        }
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $err = $e->getMessage();
}

// =========================
// Listar todos los usuarios
// =========================
$sql = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.created_at,
        GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS roles,
        MAX(CASE WHEN r.name = 'admin' THEN 1 ELSE 0 END) AS is_admin
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r       ON r.id = ur.role_id
    GROUP BY u.id, u.name, u.email, u.created_at
    ORDER BY u.id
";
$users = $pdo->query($sql)->fetchAll();

$currentUserId = $_SESSION['user']['id'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Intraesa | Administradores</title>
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
                <a href="dashboard.php" class="list-group-item list-group-item-action">Dashboard</a>
                <a href="planes.php"    class="list-group-item list-group-item-action">Planes</a>
                <a href="clientes.php"  class="list-group-item list-group-item-action">Clientes</a>
                <a href="recargas.php"  class="list-group-item list-group-item-action">Recargas</a>
                <a href="envios.php"    class="list-group-item list-group-item-action">Envíos</a>
                <a href="admins.php"    class="list-group-item list-group-item-action active">Administradores</a>
            </div>
        </aside>

        <!-- Contenido -->
        <main class="col-12 col-md-9 col-lg-10 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0">Administradores</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateAdmin">
                    Nuevo administrador
                </button>
            </div>

            <?php if ($ok): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($ok) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <?php if ($err): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($err) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Roles</th>
                            <th>Creado</th>
                            <th class="text-center">¿Admin?</th>
                            <th class="text-end" style="min-width: 160px;">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No hay usuarios registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <?php $isAdmin = ((int)$u['is_admin']) === 1; ?>
                                <tr>
                                    <td><?= (int)$u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['name']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= htmlspecialchars($u['roles'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($u['created_at'] ?? '') ?></td>
                                    <td class="text-center">
                                        <?php if ($isAdmin): ?>
                                            <span class="badge bg-success">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((int)$u['id'] === (int)$currentUserId): ?>
                                            <span class="text-muted small">Tú mismo</span>
                                        <?php else: ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_admin">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit"
                                                        class="btn btn-sm <?= $isAdmin ? 'btn-outline-danger' : 'btn-outline-primary' ?>"
                                                        onclick="return confirm('¿Seguro?');">
                                                    <?= $isAdmin ? 'Quitar admin' : 'Hacer admin' ?>
                                                </button>
                                            </form>
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

<!-- MODAL: Nuevo administrador -->
<div class="modal fade" id="modalCreateAdmin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo administrador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create_admin">

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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear administrador</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
