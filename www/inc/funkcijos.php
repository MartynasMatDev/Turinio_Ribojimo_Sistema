<?php
// Bendros pagalbinės funkcijos

// Saugus teksto išvedimas HTML (apsauga nuo XSS)
function e($tekstas)
{
    return htmlspecialchars((string)$tekstas, ENT_QUOTES, 'UTF-8');
}

// Peradresavimas
function eiti($url)
{
    header('Location: ' . $url);
    exit;
}

// --- Vienkartiniai pranešimai (rodomi po peradresavimo) ---
function pranesimas($tekstas, $tipas = 'sekme')
{
    $_SESSION['pranesimai'][] = ['tekstas' => $tekstas, 'tipas' => $tipas];
}

function paimti_pranesimus()
{
    $p = $_SESSION['pranesimai'] ?? [];
    unset($_SESSION['pranesimai']);
    return $p;
}

// --- CSRF apsauga formoms ---
function csrf_zetonas()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_laukas()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_zetonas()) . '">';
}

function tikrinti_csrf()
{
    $gautas = $_POST['csrf'] ?? '';
    if (!is_string($gautas) || !hash_equals(csrf_zetonas(), $gautas)) {
        http_response_code(400);
        pranesimas('Formos galiojimas baigėsi. Pakartokite veiksmą.', 'klaida');
        eiti($_SERVER['REQUEST_URI']);
    }
}

function yra_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// POST lauko reikšmė (apkarpyta)
function post($vardas, $numatyta = '')
{
    $r = $_POST[$vardas] ?? $numatyta;
    return is_string($r) ? trim($r) : $numatyta;
}

function get($vardas, $numatyta = '')
{
    $r = $_GET[$vardas] ?? $numatyta;
    return is_string($r) ? trim($r) : $numatyta;
}

// --- Vartotojų rolės ---
function roles_pavadinimai()
{
    return [
        'admin'       => 'Administratorius',
        'saugos'      => 'Saugos įgaliotinis',
        'direktorius' => 'Direktorius',
        'mokinys'     => 'Mokinys',
    ];
}

function roles_pavadinimas($role)
{
    $r = roles_pavadinimai();
    return $r[$role] ?? $role;
}

// --- Duomenų tikrinimas ---

// Prisijungimo vardas: 3–40 simbolių, mažosios lotyniškos raidės, skaičiai, taškas, _ ir -
function tinkamas_prisijungimo_vardas($v)
{
    return (bool)preg_match('/^[a-z0-9][a-z0-9._-]{2,39}$/', $v);
}

// Vardas / pavardė: raidės (ir lietuviškos), tarpai, brūkšneliai
function tinkamas_vardas($v)
{
    return (bool)preg_match('/^[\p{L}][\p{L}\s\'-]{1,49}$/u', $v);
}

// Slaptažodis: bent 8 simboliai, bent viena raidė ir vienas skaitmuo
function slaptazodzio_klaida($s)
{
    if (mb_strlen($s) < 8) {
        return 'Slaptažodis turi būti ne trumpesnis nei 8 simboliai.';
    }
    if (mb_strlen($s) > 72) {
        return 'Slaptažodis per ilgas (daugiausia 72 simboliai).';
    }
    if (!preg_match('/\p{L}/u', $s) || !preg_match('/\d/', $s)) {
        return 'Slaptažodyje turi būti bent viena raidė ir bent vienas skaitmuo.';
    }
    return null;
}

// Atsitiktinis lengvai perskaitomas slaptažodis (be painių simbolių 0/O, 1/l)
function generuoti_slaptazodi($ilgis = 10)
{
    $raides = 'abcdefghijkmnpqrstuvwxyz';
    $skaiciai = '23456789';
    $visi = $raides . strtoupper($raides) . $skaiciai;
    $s = $raides[random_int(0, strlen($raides) - 1)] . $skaiciai[random_int(0, strlen($skaiciai) - 1)];
    for ($i = 2; $i < $ilgis; $i++) {
        $s .= $visi[random_int(0, strlen($visi) - 1)];
    }
    return str_shuffle($s);
}

/**
 * Klasės pavadinimo atpažinimas.
 * Leidžiami formatai: 1–12 su nebūtina raide (3A, 7b, 10C) arba
 * gimnazijos klasės romėniškais skaitmenimis (IG, II G, IIIa, IVG).
 * Grąžina ['klase' => normalizuotas pavadinimas, 'nr' => 1..12] arba null.
 */
function atpazinti_klase($tekstas)
{
    $t = mb_strtoupper(preg_replace('/\s+/u', '', (string)$tekstas));
    if (preg_match('/^(1[0-2]|[1-9])([A-ZĄČĘĖĮŠŲŪŽ]?)$/u', $t, $m)) {
        return ['klase' => $t, 'nr' => (int)$m[1]];
    }
    if (preg_match('/^(IV|III|II|I)(G?)([A-ZĄČĘĖĮŠŲŪŽ]?)$/u', $t, $m)) {
        $rom = ['I' => 9, 'II' => 10, 'III' => 11, 'IV' => 12];
        return ['klase' => $t, 'nr' => $rom[$m[1]]];
    }
    return null;
}

// Pagal klasės numerį randa mokinių grupę (pradinukai / pagrindiniai / gimnazistai)
function grupe_pagal_klase($nr)
{
    return vienas('SELECT * FROM mokiniu_grupes WHERE ? BETWEEN klase_nuo AND klase_iki', [(int)$nr]);
}

// Datos formatavimas
function data_laikas($dt)
{
    return $dt ? date('Y-m-d H:i', strtotime($dt)) : '–';
}

// Data formoje (YYYY-MM-DD) – tikrinimas
function tinkama_data($d)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return false;
    }
    list($y, $m, $dd) = explode('-', $d);
    return checkdate((int)$m, (int)$dd, (int)$y);
}

// --- Puslapiavimas ---
function puslapiavimas($viso, $puslapis, $per_puslapi = PUSLAPYJE_IRASU)
{
    $puslapiu = max(1, (int)ceil($viso / $per_puslapi));
    if ($puslapiu <= 1) {
        return '';
    }
    $param = $_GET;
    $html = '<nav class="puslapiai">';
    for ($i = 1; $i <= $puslapiu; $i++) {
        $param['p'] = $i;
        $klase = $i === $puslapis ? ' class="aktyvus"' : '';
        $html .= '<a' . $klase . ' href="?' . e(http_build_query($param)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

function dabartinis_puslapis()
{
    return max(1, (int)get('p', '1'));
}

// Klaidos žinutė prie formos lauko
function lauko_klaida(array $klaidos, $laukas)
{
    return isset($klaidos[$laukas]) ? '<div class="lauko-klaida">' . e($klaidos[$laukas]) . '</div>' : '';
}

function klaidos_klase(array $klaidos, $laukas)
{
    return isset($klaidos[$laukas]) ? ' class="klaidingas"' : '';
}
