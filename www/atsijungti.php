<?php
require_once __DIR__ . '/inc/auth.php';
$_SESSION = [];
session_destroy();
session_start();
pranesimas('Sėkmingai atsijungėte.');
eiti('index.php');
