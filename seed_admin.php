<?php
// seed_admin.php
// Script para crear/actualizar un usuario admin con contraseña hasheada.

require __DIR__ . '/config/db.php';  // usamos tu db()
$pdo = db();

// ========== CONFIG DEL ADMIN INICIAL ==========
$email = 'admin@intraesa.test';
$pass  = 'Admin123';        // cámbiala luego
$name  = 'Super Admin';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // 1) Asegurar roles básicos: admin y client
    $pdo->exec("
        INSERT IGNORE INTO roles (name) VALUES ('admin'), ('client')
    ");

    // Obtenemos el id del rol 'admin'
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute(['admin']);
    $role = $stmt->fetch();
    if (!$role) {
        throw new Exception("No se pudo obtener el rol 'admin'.");
    }
    $roleId = (int)$role['id'];

    // 2) Crear o actualizar usuario por email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Crear usuario nuevo con contraseña hasheada
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $email,
            password_hash($pass, PASSWORD_DEFAULT)
        ]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$user['id'];

        // Opcional: actualizar nombre y contraseña
        $stmt = $pdo->prepare("
            UPDATE users
            SET name = ?, password_hash = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name,
            password_hash($pass, PASSWORD_DEFAULT),
            $userId
        ]);
    }

    // 3) Vincular rol admin al usuario (evitar duplicados)
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO user_roles (user_id, role_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$userId, $roleId]);

    $pdo->commit();

    // Mensaje claro para ti
    echo "<h2>✅ Admin listo</h2>";
    echo "<p><strong>Correo:</strong> {$email}</p>";
    echo "<p><strong>Contraseña:</strong> {$pass}</p>";
    echo "<p>Ya puedes iniciar sesión en el login con estas credenciales.</p>";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2>❌ Error al sembrar admin</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
