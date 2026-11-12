<?php
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
start_soci_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    socio_logout();
}
header('Location: ' . base_path('/soci-area/login.php'));
exit;
