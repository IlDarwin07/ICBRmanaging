<?php
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();
do_logout();
header('Location: ' . base_path('/login.php'));
exit;
