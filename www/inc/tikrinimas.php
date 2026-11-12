<?php
// URL normalizavimas ir prieigos tikrinimo logika

/**
 * Iš įvesto adreso išskiria domeną (be www.), pvz.:
 *   "https://www.Facebook.com/grupe?id=1" -> "facebook.com"
 *   "m.youtube.com/watch"                 -> "m.youtube.com"
 * Klaidos atveju grąžina null, o $klaida gauna paaiškinimą.
 */
function normalizuoti_domena($ivestis, &$klaida)
{
    $klaida = null;
    $s = trim((string)$ivestis);
    if ($s === '') {
        $klaida = 'Įveskite URL adresą.';
        return null;
    }
    if (mb_strlen($s) > 500) {
        $klaida = 'Adresas per ilgas (daugiausia 500 simbolių).';
        return null;
    }
    if (preg_match('/\s/u', $s)) {
        $klaida = 'Adrese negali būti tarpų.';
        return null;
    }
    if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $s)) {
        $s = 'http://' . $s;
    }
    $dalys = parse_url($s);
    if ($dalys === false || empty($dalys['host'])) {
        $klaida = 'Neteisingas adreso formatas. Pavyzdys: www.pavyzdys.lt';
        return null;
    }
    $schema = strtolower($dalys['scheme'] ?? 'http');
    if ($schema !== 'http' && $schema !== 'https') {
        $klaida = 'Palaikomi tik http ir https adresai.';
        return null;
    }
    $host = rtrim(mb_strtolower($dalys['host']), '.');
    // Lietuviški (IDN) domenai paverčiami į punycode, jei yra intl plėtinys
    if (preg_match('/[^\x20-\x7e]/', $host) && function_exists('idn_to_ascii')) {
        $ascii = idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46);
        if ($ascii) {
            $host = $ascii;
        }
    }
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }
    $ipv4 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    $domenas = preg_match('/^(?=.{3,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/', $host);
    if (!$ipv4 && !$domenas) {
        $klaida = 'Neteisingas domeno vardas „' . $host . '“. Pavyzdys: www.pavyzdys.lt';
        return null;
    }
    return $host;
}

/**
 * Domeno „priesagos“: m.facebook.com -> [m.facebook.com, facebook.com]
 * (aukščiausio lygio domenas atskirai nenaudojamas)
 */
function domeno_priesagos($host)
{
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return [$host];
    }
    $dalys = explode('.', $host);
    $rez = [];
    for ($i = 0; $i < count($dalys) - 1; $i++) {
        $rez[] = implode('.', array_slice($dalys, $i));
    }
    return $rez;
}

/**
 * Patikrina, ar mokinys (pagal grupę) gali atidaryti domeną.
 * Grąžina: ['leidziama' => bool, 'atitikmenys' => [...], 'draudziantis' => eilutė|null, 'priskirtas' => eilutė|null]
 */
function tikrinti_prieiga($host, $grupes_id)
{
    $priesagos = domeno_priesagos($host);
    $vietos = implode(',', array_fill(0, count($priesagos), '?'));
    // Ilgesnis (tikslesnis) domenas – pirmiau
    $atitikmenys = visi(
        "SELECT u.id AS url_id, u.adresas, k.id AS kategorijos_id, k.pavadinimas AS kategorija,
                (d.grupes_id IS NOT NULL) AS uzdrausta
           FROM url_adresai u
           JOIN kategorijos k ON k.id = u.kategorijos_id
           LEFT JOIN draudimai d ON d.kategorijos_id = k.id AND d.grupes_id = ?
          WHERE u.adresas IN ($vietos)
          ORDER BY uzdrausta DESC, CHAR_LENGTH(u.adresas) DESC, k.pavadinimas",
        array_merge([(int)$grupes_id], $priesagos)
    );
    $draudziantis = null;
    foreach ($atitikmenys as $a) {
        if ($a['uzdrausta']) {
            $draudziantis = $a;
            break;
        }
    }
    return [
        'leidziama'    => $draudziantis === null,
        'atitikmenys'  => $atitikmenys,
        'draudziantis' => $draudziantis,
        'priskirtas'   => $draudziantis ?: ($atitikmenys[0] ?? null),
    ];
}
