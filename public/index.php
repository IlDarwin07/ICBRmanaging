<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
header('Location: ' . base_path(is_logged_in() ? '/dashboard.php' : '/login.php'));
exit;
