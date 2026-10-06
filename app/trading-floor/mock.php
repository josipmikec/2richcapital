<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['authenticated'] = true;
$_SESSION['user_name'] = 'Test';
$_SESSION['csrf_token'] = 'token';
require 'index.php';
