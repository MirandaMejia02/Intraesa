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

    <!-- Bootstrap 5 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" 
          rel="stylesheet">

    <!-- Archivo de estilos futurista del panel admin -->
    <link rel="stylesheet" href="/Proyecto_DWS/public/admin.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>

<!-- BODY con fondo igual al login -->
<body class="intraesa-bg">

<nav class="navbar navbar-expand-lg navbar-dark admin-navbar shadow-sm">
    <div class="container-fluid">

        <!-- Brand -->
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="dashboard.php">
            <img src="/Proyecto_DWS/public/login/img/INTRAESA_logo_transparent.png" 
                 alt="Logo Intraesa"
                 style="height:32px; filter:drop-shadow(0 0 4px rgba(255,255,255,0.3));">
            Intraesa — Panel Admin
        </a>

        <div class="d-flex align-items-center gap-3">
            <span class="text-light small opacity-75">Hola, <?= htmlspecialchars($userName) ?></span>
            <a href="../logout.php" class="btn btn-gradient btn-sm px-3">Cerrar sesión</a>
        </div>

    </div>
</nav>

<!-- Contenedor global -->
<div class="container-fluid py-4">
