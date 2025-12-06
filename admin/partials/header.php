<?php
// admin/partials/header.php
require_once __DIR__ . '/../../config/db.php';
require_role('admin');

$userName = $_SESSION['user']['name'] ?? 'Admin';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Intraesa - Panel admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 desde CDN (solo CSS) -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- jQuery global, SIN integrity -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Intraesa - Panel admin</a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-light small">Hola, <?= htmlspecialchars($userName) ?></span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Cerrar sesión</a>
        </div>
    </div>
</nav>

<div class="container-fluid py-3">
