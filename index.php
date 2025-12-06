<?php
// ======================
//   LOGIN INTRAESA
// ======================

require_once __DIR__ . '/config/db.php'; // aquí ya tienes db(), session_start(), etc.

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
        // fallback por si algún usuario no tiene rol claro
        header('Location: ./admin/dashboard.php');
    }
    exit;
}

$error = null;

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vamos a loguear SOLO por correo (aunque el label diga "Usuario")
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
                // si no tiene rol asignado en user_roles, lo tratamos como client
                $roleName = $u['role'] ?: 'client';

                // estructura nueva para helpers
                $_SESSION['user'] = [
                    'id'    => (int)$u['id'],
                    'name'  => $u['name'],
                    'email' => $u['email'],
                    'roles' => [$roleName],
                ];

                // variables clásicas por compatibilidad
                $_SESSION['user_id']  = (int)$u['id'];
                $_SESSION['userName'] = $u['name'];
                $_SESSION['role']     = $roleName;

                // Redirigir según rol
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
            // Si quieres debug:
            // $error .= " Detalle: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign in Form</title>
  <link rel="stylesheet" href="./public/login/login.css" />
</head>

<body>
  <main>
    <div class="box">
      <div class="inner-box">
        <div class="forms-wrap">
          <form action="" method="POST" autocomplete="off" class="sign-in-form">
            <div class="logo">
              <img src="./public/login/img/logoIntraensa.png" alt="logo" />
            </div>

            <div class="heading">
              <h2>Bienvenido a Intraesa</h2>
            </div>

            <div class="actual-form">
              <div class="input-wrap">
                <input
                  type="text"
                  name="userNickname"
                  class="input-field"
                  autocomplete="off"
                  required
                />
                <label>Usuario (correo)</label>
              </div>

              <div class="input-wrap">
                <input
                  type="password"
                  name="userPassword"
                  class="input-field"
                  autocomplete="off"
                  required
                />
                <label>Contraseña</label>
              </div>

              <input type="submit" value="Iniciar Sesión" class="sign-btn" />
            </div>

            <?php if (!empty($error)): ?>
              <p style="color:red; margin-top:10px;">
                <?= htmlspecialchars($error) ?>
              </p>
            <?php endif; ?>

          </form>
        </div>

        <div class="carousel">
          <div class="images-wrapper">
            <img src="./public/login/img/image1.png" class="image img-1 show" alt="" />
            <img src="./public/login/img/image2.png" class="image img-2" alt="" />
            <img src="./public/login/img/image3.png" class="image img-3" alt="" />
          </div>

          <div class="text-slider">
            <div class="text-wrap">
              <div class="text-group">
                <h2>Pide como millonario</h2>
                <h2>Personalizalo a tu manera</h2>
                <h2>Primero eres tú</h2>
              </div>
            </div>

            <div class="bullets">
              <span class="active" data-value="1"></span>
              <span data-value="2"></span>
              <span data-value="3"></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="./public/login/login.js"></script>
</body>

</html>
