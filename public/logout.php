<?php
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();
do_logout();
header('Location: /login.php');
exit;
