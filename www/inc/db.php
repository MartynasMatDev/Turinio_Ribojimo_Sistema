<?php
// Darbas su duomenų baze (mysqli + paruoštos užklausos)

function db()
{
    static $jungtis = null;
    if ($jungtis === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $jungtis = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $jungtis->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            http_response_code(503);
            echo '<!DOCTYPE html><html lang="lt"><head><meta charset="utf-8"><title>Klaida</title></head>'
               . '<body style="font-family:sans-serif;padding:40px">'
               . '<h2>Nepavyko prisijungti prie duomenų bazės</h2>'
               . '<p>Jei sistema ką tik paleista, duomenų bazė dar gali būti ruošiama. '
               . 'Palaukite kelias sekundes ir atnaujinkite puslapį.</p></body></html>';
            exit;
        }
    }
    return $jungtis;
}

// Įvykdo užklausą su parametrais. Parametrų tipai nustatomi automatiškai.
function uzklausa($sql, array $param = [])
{
    $st = db()->prepare($sql);
    if ($param) {
        $tipai = '';
        foreach ($param as $p) {
            if (is_int($p)) {
                $tipai .= 'i';
            } elseif (is_float($p)) {
                $tipai .= 'd';
            } else {
                $tipai .= 's';
            }
        }
        $st->bind_param($tipai, ...$param);
    }
    $st->execute();
    return $st;
}

// Grąžina visas eilutes asociatyviu masyvu
function visi($sql, array $param = [])
{
    $rez = uzklausa($sql, $param)->get_result();
    return $rez ? $rez->fetch_all(MYSQLI_ASSOC) : [];
}

// Grąžina pirmą eilutę arba null
function vienas($sql, array $param = [])
{
    $eil = visi($sql, $param);
    return $eil ? $eil[0] : null;
}

// Grąžina vieną reikšmę (pvz. COUNT(*))
function reiksme($sql, array $param = [])
{
    $e = vienas($sql, $param);
    return $e ? reset($e) : null;
}

// INSERT / UPDATE / DELETE. Grąžina paveiktų eilučių skaičių
function vykdyti($sql, array $param = [])
{
    $st = uzklausa($sql, $param);
    return $st->affected_rows;
}

function paskutinis_id()
{
    return db()->insert_id;
}
