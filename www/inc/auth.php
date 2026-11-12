<?php
// Sesijos, prisijungimas ir teisių tikrinimas

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funkcijos.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('TRSID');
    session_set_cookie_params(0, '/', '', false, true); // httponly slapukas
    session_start();
}

// Šiuo metu prisijungęs vartotojas arba null
function vartotojas()
{
    static $v = false;
    if ($v === false) {
        $v = null;
        if (!empty($_SESSION['vartotojo_id'])) {
            $v = vienas(
                'SELECT v.*, m.klase, m.klases_nr, m.grupes_id, g.pavadinimas AS grupes_pavadinimas
                   FROM vartotojai v
                   LEFT JOIN mokiniai m ON m.vartotojo_id = v.id
                   LEFT JOIN mokiniu_grupes g ON g.id = m.grupes_id
                  WHERE v.id = ?',
                [(int)$_SESSION['vartotojo_id']]
            );
            // Jei paskyra ištrinta ar užblokuota – sesija nutraukiama
            if (!$v || !$v['aktyvus']) {
                $_SESSION = [];
                session_destroy();
                $v = null;
            }
        }
    }
    return $v;
}

// Puslapis leidžiamas tik nurodytoms rolėms. Kitu atveju – į prisijungimą arba klaida 403.
function reikalauti_roles($roles)
{
    $roles = (array)$roles;
    $v = vartotojas();
    if (!$v) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        pranesimas('Norėdami tęsti, prisijunkite prie sistemos.', 'klaida');
        eiti('index.php');
    }
    if (!in_array($v['role'], $roles, true)) {
        http_response_code(403);
        $GLOBALS['puslapio_pavadinimas'] = 'Prieiga negalima';
        require __DIR__ . '/virsus.php';
        echo '<div class="kortele"><h1>Prieiga negalima</h1>'
           . '<p>Ši funkcija skirta tik šioms vartotojų kategorijoms: '
           . e(implode(', ', array_map('roles_pavadinimas', $roles))) . '.</p>'
           . '<p><a class="mygtukas" href="pradzia.php">Grįžti į pradžią</a></p></div>';
        require __DIR__ . '/apacia.php';
        exit;
    }
    return $v;
}

// Pradinis puslapis pagal rolę
function pradzios_puslapis($role)
{
    $p = [
        'admin'       => 'admin_apzvalga.php',
        'saugos'      => 'saugos_pazeidejai.php',
        'direktorius' => 'direktorius_draudimai.php',
        'mokinys'     => 'mokinys_tikrinti.php',
    ];
    return $p[$role] ?? 'index.php';
}
