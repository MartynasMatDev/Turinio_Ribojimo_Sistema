<?php
// Administratorius: mokinių sąrašo importas iš išorinio CSV failo
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('admin');

// Sugeneruotų slaptažodžių atsisiuntimas (CSV)
if (get('atsisiusti') === '1') {
    $sarasas = $_SESSION['importo_slaptazodziai'] ?? [];
    if (!$sarasas) {
        pranesimas('Nėra sugeneruotų slaptažodžių atsisiuntimui.', 'klaida');
        eiti('admin_importas.php');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mokiniu_prisijungimai_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM, kad Excel teisingai rodytų lietuviškas raides
    $f = fopen('php://output', 'w');
    fputcsv($f, ['Vardas', 'Pavardė', 'Klasė', 'Prisijungimo vardas', 'Slaptažodis'], ';');
    foreach ($sarasas as $eil) {
        fputcsv($f, $eil, ';');
    }
    fclose($f);
    exit;
}

$klaidos = [];
$eiluciu_klaidos = [];
$atnaujinti = false;

if (yra_post()) {
    tikrinti_csrf();
    $atnaujinti = !empty($_POST['atnaujinti']);
    $failas = $_FILES['failas'] ?? null;

    if (!$failas || $failas['error'] === UPLOAD_ERR_NO_FILE) {
        $klaidos['failas'] = 'Pasirinkite importuojamą failą.';
    } elseif ($failas['error'] !== UPLOAD_ERR_OK) {
        $klaidos['failas'] = 'Failo įkelti nepavyko (klaidos kodas ' . (int)$failas['error'] . ').';
    } elseif ($failas['size'] > 1024 * 1024) {
        $klaidos['failas'] = 'Failas per didelis (daugiausia 1 MB).';
    } elseif (!preg_match('/\.(csv|txt)$/i', $failas['name'])) {
        $klaidos['failas'] = 'Leidžiami tik .csv arba .txt failai.';
    }

    $eilutes = [];
    if (!$klaidos) {
        $turinys = file_get_contents($failas['tmp_name']);
        $turinys = preg_replace('/^\xEF\xBB\xBF/', '', $turinys);
        if (!mb_check_encoding($turinys, 'UTF-8')) {
            // Excel lietuviškoje Windows dažnai išsaugo Windows-1257 koduote
            $turinys = mb_convert_encoding($turinys, 'UTF-8', 'Windows-1257');
        }
        $visos = preg_split('/\R/u', $turinys);
        $pirma = '';
        foreach ($visos as $l) {
            if (trim($l) !== '') {
                $pirma = $l;
                break;
            }
        }
        $skirtukai = [';' => substr_count($pirma, ';'), ',' => substr_count($pirma, ','), "\t" => substr_count($pirma, "\t")];
        arsort($skirtukai);
        $skirtukas = key($skirtukai);

        foreach ($visos as $nr => $l) {
            if (trim($l) === '') {
                continue;
            }
            $laukai = array_map('trim', str_getcsv($l, $skirtukas));
            if (!$eilutes && preg_match('/vard/iu', $laukai[0] ?? '') && preg_match('/pavard/iu', $laukai[1] ?? '')) {
                continue; // antraštės eilutė
            }
            $eilutes[] = ['nr' => $nr + 1, 'laukai' => $laukai];
        }
        if (!$eilutes) {
            $klaidos['failas'] = 'Faile nerasta nė vienos duomenų eilutės.';
        } elseif (count($eilutes) > 2000) {
            $klaidos['failas'] = 'Per daug eilučių (daugiausia 2000).';
        }
    }

    // Kiekvienos eilutės tikrinimas
    $paruosta = [];
    if (!$klaidos) {
        $faile_vardai = [];
        $grupes = [];
        foreach (visi('SELECT * FROM mokiniu_grupes') as $g) {
            $grupes[] = $g;
        }
        foreach ($eilutes as $e) {
            $l = $e['laukai'];
            $kl = [];
            if (count($l) < 4) {
                $eiluciu_klaidos[] = ['nr' => $e['nr'], 'tekstas' => 'Per mažai stulpelių (reikia: vardas, pavardė, klasė, prisijungimo vardas).'];
                continue;
            }
            list($vardas, $pavarde, $klase_txt, $pv) = $l;
            $pv = mb_strtolower($pv);
            $slapt = $l[4] ?? '';
            if (!tinkamas_vardas($vardas)) {
                $kl[] = "neteisingas vardas „{$vardas}“";
            }
            if (!tinkamas_vardas($pavarde)) {
                $kl[] = "neteisinga pavardė „{$pavarde}“";
            }
            $klase = atpazinti_klase($klase_txt);
            if (!$klase) {
                $kl[] = "neteisinga klasė „{$klase_txt}“ (pvz. 3A, 7B, 10C, IIIG)";
            }
            if (!tinkamas_prisijungimo_vardas($pv)) {
                $kl[] = "neteisingas prisijungimo vardas „{$pv}“ (3–40 simbolių: a–z, 0–9, . _ -)";
            } elseif (isset($faile_vardai[$pv])) {
                $kl[] = "prisijungimo vardas „{$pv}“ faile kartojasi (žr. {$faile_vardai[$pv]} eil.)";
            }
            if ($slapt !== '' && ($k = slaptazodzio_klaida($slapt))) {
                $kl[] = mb_strtolower(mb_substr($k, 0, 1)) . mb_substr($k, 1);
            }
            $esamas = null;
            if (!$kl) {
                $esamas = vienas('SELECT id, role FROM vartotojai WHERE prisijungimo_vardas = ?', [$pv]);
                if ($esamas && $esamas['role'] !== 'mokinys') {
                    $kl[] = "prisijungimo vardas „{$pv}“ jau naudojamas darbuotojo paskyroje";
                }
            }
            $faile_vardai[$pv] = $e['nr'];
            if ($kl) {
                $eiluciu_klaidos[] = ['nr' => $e['nr'], 'tekstas' => implode('; ', $kl) . '.'];
                continue;
            }
            $grupe = null;
            foreach ($grupes as $g) {
                if ($klase['nr'] >= $g['klase_nuo'] && $klase['nr'] <= $g['klase_iki']) {
                    $grupe = $g;
                }
            }
            $paruosta[] = compact('vardas', 'pavarde', 'pv', 'slapt', 'esamas', 'grupe') + ['klase' => $klase];
        }
    }

    // Įrašymas (tik jei visos eilutės teisingos)
    if (!$klaidos && !$eiluciu_klaidos && $paruosta) {
        $nauji = 0;
        $atnaujinta = 0;
        $praleista = 0;
        $slaptazodziai = [];
        db()->begin_transaction();
        foreach ($paruosta as $p) {
            if ($p['esamas']) {
                if (!$atnaujinti) {
                    $praleista++;
                    continue;
                }
                $id = (int)$p['esamas']['id'];
                vykdyti('UPDATE vartotojai SET vardas = ?, pavarde = ? WHERE id = ?', [$p['vardas'], $p['pavarde'], $id]);
                vykdyti('UPDATE mokiniai SET klase = ?, klases_nr = ?, grupes_id = ? WHERE vartotojo_id = ?',
                    [$p['klase']['klase'], $p['klase']['nr'], (int)$p['grupe']['id'], $id]);
                if ($p['slapt'] !== '') {
                    vykdyti('UPDATE vartotojai SET slaptazodis = ? WHERE id = ?', [password_hash($p['slapt'], PASSWORD_DEFAULT), $id]);
                }
                $atnaujinta++;
            } else {
                $slapt = $p['slapt'] !== '' ? $p['slapt'] : generuoti_slaptazodi();
                vykdyti("INSERT INTO vartotojai (prisijungimo_vardas, slaptazodis, vardas, pavarde, role) VALUES (?, ?, ?, ?, 'mokinys')",
                    [$p['pv'], password_hash($slapt, PASSWORD_DEFAULT), $p['vardas'], $p['pavarde']]);
                $id = paskutinis_id();
                vykdyti('INSERT INTO mokiniai (vartotojo_id, klase, klases_nr, grupes_id) VALUES (?, ?, ?, ?)',
                    [$id, $p['klase']['klase'], $p['klase']['nr'], (int)$p['grupe']['id']]);
                if ($p['slapt'] === '') {
                    $slaptazodziai[] = [$p['vardas'], $p['pavarde'], $p['klase']['klase'], $p['pv'], $slapt];
                }
                $nauji++;
            }
        }
        vykdyti('INSERT INTO importai (administratoriaus_id, failo_vardas, sukurta_nauju, atnaujinta, praleista, laikas) VALUES (?, ?, ?, ?, ?, NOW())',
            [(int)$v['id'], mb_substr($failas['name'], 0, 255), $nauji, $atnaujinta, $praleista]);
        db()->commit();

        $_SESSION['importo_slaptazodziai'] = $slaptazodziai;
        pranesimas("Importas baigtas: sukurta naujų mokinių – $nauji, atnaujinta – $atnaujinta, praleista (jau buvo) – $praleista.");
        eiti('admin_importas.php?rezultatas=1');
    }
}

$sugeneruoti = get('rezultatas') === '1' ? ($_SESSION['importo_slaptazodziai'] ?? []) : [];
$istorija = visi(
    'SELECT i.*, v.vardas, v.pavarde FROM importai i LEFT JOIN vartotojai v ON v.id = i.administratoriaus_id
      ORDER BY i.laikas DESC, i.id DESC LIMIT 10'
);

$GLOBALS['puslapio_pavadinimas'] = 'Mokinių importas';
require __DIR__ . '/inc/virsus.php';
?>
<div class="dvi-kolonos">
    <div class="kortele">
        <h1>Mokinių sąrašo importas</h1>
        <p>Įkelkite CSV failą (pvz. išsaugotą iš Excel), kurio kiekvienoje eilutėje nurodyta:</p>
        <pre class="kodas">vardas;pavardė;klasė;prisijungimo_vardas[;slaptažodis]
Jonas;Petraitis;3A;jonas.petraitis
Eglė;Kazlauskienė;IIIG;egle.kazlauskiene;Pradinis123</pre>
        <ul class="pastaba">
            <li>Skirtukas – kabliataškis, kablelis arba tabuliacija. Antraštės eilutė neprivaloma.</li>
            <li>Klasė: 1–12 su raide (3A, 10C) arba gimnazijos klasės (IG, IIG, IIIG, IVG).
                Pagal klasę mokinys priskiriamas grupei: 1–4 – pradinukai, 5–8 – pagrindiniai, 9–12 (I–IV gimn.) – gimnazistai.</li>
            <li>Jei slaptažodis nenurodytas, sistema jį sugeneruos ir parodys po importo.</li>
            <li>Jei bent viena eilutė klaidinga, neimportuojama nieko – pataisykite failą ir bandykite iš naujo.</li>
        </ul>
        <form method="post" enctype="multipart/form-data" novalidate>
            <?= csrf_laukas() ?>
            <label>CSV failas *</label>
            <input type="file" name="failas" accept=".csv,.txt"<?= klaidos_klase($klaidos, 'failas') ?>>
            <?= lauko_klaida($klaidos, 'failas') ?>
            <label class="zymimasis"><input type="checkbox" name="atnaujinti" value="1"<?= $atnaujinti ? ' checked' : '' ?>>
                Atnaujinti jau esančių mokinių vardą, pavardę ir klasę (pvz. naujiems mokslo metams)</label>
            <button class="mygtukas" type="submit">Importuoti</button>
        </form>

        <?php if ($eiluciu_klaidos): ?>
            <div class="pranesimas klaida">Importas neatliktas: faile rasta klaidingų eilučių (<?= count($eiluciu_klaidos) ?>).</div>
            <table class="lentele">
                <tr><th class="sk">Eilutė</th><th>Klaida</th></tr>
                <?php foreach ($eiluciu_klaidos as $k): ?><tr><td class="sk"><?= (int)$k['nr'] ?></td><td><?= e($k['tekstas']) ?></td></tr><?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    <div>
        <?php if ($sugeneruoti): ?>
        <div class="kortele">
            <h2>Sugeneruoti prisijungimai</h2>
            <p class="pastaba">Slaptažodžiai rodomi tik dabar. Atsisiųskite sąrašą ir perduokite mokiniams.</p>
            <p><a class="mygtukas" href="admin_importas.php?atsisiusti=1">Atsisiųsti CSV</a></p>
            <table class="lentele">
                <tr><th>Mokinys</th><th>Klasė</th><th>Prisijungimo vardas</th><th>Slaptažodis</th></tr>
                <?php foreach ($sugeneruoti as $s): ?>
                <tr><td><?= e($s[0] . ' ' . $s[1]) ?></td><td><?= e($s[2]) ?></td><td><?= e($s[3]) ?></td><td><code><?= e($s[4]) ?></code></td></tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
        <div class="kortele">
            <h2>Importų istorija</h2>
            <?php if ($istorija): ?>
            <table class="lentele">
                <tr><th>Laikas</th><th>Failas</th><th class="sk">Nauji</th><th class="sk">Atnauj.</th><th class="sk">Prale.</th><th>Atliko</th></tr>
                <?php foreach ($istorija as $i): ?>
                <tr><td><?= e(data_laikas($i['laikas'])) ?></td><td><?= e($i['failo_vardas']) ?></td>
                    <td class="sk"><?= (int)$i['sukurta_nauju'] ?></td><td class="sk"><?= (int)$i['atnaujinta'] ?></td><td class="sk"><?= (int)$i['praleista'] ?></td>
                    <td><?= e(trim(($i['vardas'] ?? '') . ' ' . ($i['pavarde'] ?? ''))) ?></td></tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?><p class="pastaba">Importų dar nebuvo.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
