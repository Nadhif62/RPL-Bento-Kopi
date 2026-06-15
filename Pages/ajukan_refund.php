<?php
require_once __DIR__ . '/../Includes/config.php';
require_login(['manager']);

header('Location: ' . app_url('Pages/manager_refunds.php'));
exit;
