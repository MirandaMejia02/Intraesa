<?php
// config/db.php

function db() {
    static $pdo;
    if ($pdo) {
        return $pdo;
    }

    $host = 'localhost';
    $db   = 'intraesa';
    $user = 'root';   // cambia si usas otro usuario
    $pass = 'zaphkiel';       // cambia si tienes password
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}

/* ============================
   SESIÓN + HELPERS DE AUTH
   ============================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function has_role(string $roleName): bool {
    if (!is_logged_in()) return false;
    return in_array($roleName, $_SESSION['user']['roles'] ?? []);
}

function require_login() {
    if (!is_logged_in()) {
        // si llamas desde /admin/x.php te mandará al login en /public
        header('Location: ../index.php');
        exit;
    }
}


function require_role(string $roleName) {
    require_login();
    if (!has_role($roleName)) {
        http_response_code(403);
        echo "Acceso denegado. No tienes el rol requerido.";
        exit;
    }
}
// ============================
// Helpers de etiquetas de estados
// ============================
function shipment_status_label(string $status): string {
    $map = [
        'verifying'          => 'En verificación',
        'pending_collection' => 'Pendiente de recolección',
        'collected'          => 'Recolectado',
        'in_transit'         => 'En tránsito',
        'delivered'          => 'Entregado',
    ];

    return $map[$status] ?? $status; // fallback por si aparece algo raro
}
