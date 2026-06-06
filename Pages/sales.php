<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['kasir']);
header('Location: ' . app_url('Pages/cek_order.php'));
exit;
