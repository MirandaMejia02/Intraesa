<?php
require __DIR__ . '/config/db.php';

$pdo = db();

$email = 'admin@intraesa.test';   // el que te muestra seed_admin.php

$stmt = $pdo->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

echo "<pre>";
var_dump($user);
echo "</pre>";

if ($user) {
    echo "<p>¿password_verify('Admin123')?</p>";
    var_dump(password_verify('Admin123', $user['password_hash']));
} else {
    echo "<p>No existe ningún usuario con ese email.</p>";
}
