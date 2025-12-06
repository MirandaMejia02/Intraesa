<?php
// logout.php
// Cerrar sesión de forma segura y volver al login

require __DIR__ . '/config/db.php'; // solo para asegurar session_start()

// Limpiar todas las variables de sesión
$_SESSION = [];

// Borrar la cookie de sesión (si existe)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destruir la sesión
session_destroy();

// Evitar que el navegador guarde páginas privadas en caché
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Volver al login
header('Location: index.php');
exit;
