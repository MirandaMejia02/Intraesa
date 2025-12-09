<?php 
// ======================
//   LOGIN INTRAESA
// ======================

require_once __DIR__ . '/config/db.php';

// Si ya hay sesión, mandamos según rol
if (is_logged_in()) {
    $user  = $_SESSION['user'] ?? null;
    $roles = $user['roles'] ?? [];
    $role  = $_SESSION['role'] ?? ($roles[0] ?? null);

    if ($role === 'admin') {
        header('Location: ./admin/dashboard.php');
    } elseif ($role === 'client') {
        header('Location: ./client/envios.php');
    } else {
        header('Location: ./admin/dashboard.php');
    }
    exit;
}

$error = null;

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['userNickname'] ?? '');
    $pass  = trim($_POST['userPassword'] ?? '');

    if ($email === '' || $pass === '') {
        $error = "Completa usuario y contraseña.";
    } else {
        try {
            $pdo = db();

            $sql = "
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.password_hash,
                    (
                        SELECT r.name
                        FROM roles r
                        JOIN user_roles ur ON ur.role_id = r.id
                        WHERE ur.user_id = u.id
                        LIMIT 1
                    ) AS role
                FROM users u
                WHERE u.email = :email
                LIMIT 1
            ";

            $st = $pdo->prepare($sql);
            $st->execute(['email' => $email]);
            $u = $st->fetch(PDO::FETCH_ASSOC);

            if ($u && password_verify($pass, $u['password_hash'])) {

                $roleName = $u['role'] ?: 'client';

                $_SESSION['user'] = [
                    'id'    => (int)$u['id'],
                    'name'  => $u['name'],
                    'email' => $u['email'],
                    'roles' => [$roleName],
                ];

                $_SESSION['user_id']  = (int)$u['id'];
                $_SESSION['userName'] = $u['name'];
                $_SESSION['role']     = $roleName;

                // Redirección según rol
                if ($roleName === 'admin') {
                    header('Location: ./admin/dashboard.php');
                } elseif ($roleName === 'client') {
                    header('Location: ./client/envios.php');
                } else {
                    header('Location: ./admin/dashboard.php');
                }
                exit;

            } else {
                $error = "Usuario o contraseña incorrectos.";
            }

        } catch (Throwable $e) {
            $error = "Error al intentar iniciar sesión.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Intraesa · Acceso</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- tu CSS nuevo -->
  <link rel="stylesheet" href="./public/login.css" />
</head>

<body>

  <!-- Fondo animado -->
  <div class="backdrop">
    <span class="orb orb-1"></span>
    <span class="orb orb-2"></span>
    <span class="orb orb-3"></span>
  </div>

  <main class="page">

    <!-- PANEL IZQUIERDO (hero / marketing) -->
 <section class="panel panel-hero">
  <div class="brand">
    <img src="./public/login/img/INTRAESA_logo_transparent.png" class="brand-logo" alt="Intraesa">
  </div>


      <h1 class="hero-title">Gestiona tus envíos con un panel tan ágil como tú</h1>
      <ul class="hero-list">
        <li><i class="fa-solid fa-circle-check"></i> Autenticación segura y roles claros</li>
        <li><i class="fa-solid fa-circle-check"></i> Créditos y recargas centralizados</li>
        <li><i class="fa-solid fa-circle-check"></i> Seguimiento en tiempo real de envíos</li>
      </ul>
    </section>

    <!-- PANEL DERECHO (FORMULARIO) -->
    <section class="panel panel-form">
      <div class="panel-header">
        <h2>Bienvenido</h2>
        <p>Inicia sesión para continuar con tu cuenta</p>
      </div>

      <form method="POST" autocomplete="off" class="form-card">

        <label class="input-block">
          <span class="input-label">Correo electrónico</span>
          <div class="input-wrapper">
            <i class="fa-regular fa-envelope"></i>
            <input type="email" name="userNickname" placeholder="tucorreo@empresa.com" required />
          </div>
        </label>

        <label class="input-block">
          <span class="input-label">Contraseña</span>
          <div class="input-wrapper">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="userPassword" placeholder="••••••••" required />
          </div>
        </label>

        <?php if (!empty($error)): ?>
          <div class="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <button type="submit" class="primary-btn">
          <span>Ingresar</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>

      </form>

    </section>

  </main>

  <!-- tu JS -->
  <script src="./public/login.js"></script>
</body>

</html>
