<?php
require_once __DIR__ . '/../Includes/config.php';

session_destroy();

header('Location: ' . app_url('Pages/index.php'));
exit;