<?php
// ---------------------------------------------------------------------
// Sistemos konfigūracija.
// Docker aplinkoje DB serverio vardas yra serviso vardas "mysql"
// (nustatoma compose.yaml faile per aplinkos kintamuosius).
// Paleidžiant be Docker (VM Apache + MySQL) pakeiskite DB_HOST į "localhost".
// ---------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_USER', getenv('DB_USER') ?: 'stud');
define('DB_PASS', getenv('DB_PASS') ?: 'stud');
define('DB_NAME', getenv('DB_NAME') ?: 'turinio_ribojimas');

define('SISTEMOS_PAVADINIMAS', 'Turinio ribojimo sistema mokyklos bevieliui');
define('AUTORIUS', 'Martynas Matutis');
define('PUSLAPYJE_IRASU', 30);

date_default_timezone_set('Europe/Vilnius');
mb_internal_encoding('UTF-8');
