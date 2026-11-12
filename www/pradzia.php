<?php
// Nukreipia prisijungusį vartotoją į jo rolės pradinį puslapį
require_once __DIR__ . '/inc/auth.php';
$v = vartotojas();
eiti($v ? pradzios_puslapis($v['role']) : 'index.php');
