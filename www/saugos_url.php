<?php
// Saugos įgaliotinis: URL (domenų) sąrašai kategorijose
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/tikrinimas.php';
$v = reikalauti_roles('saugos');

$kategorijos = visi('SELECT id, pavadinimas FROM kategorijos ORDER BY pavadinimas');
$kat_id_sarasas = array_map('intval', array_column($kategorijos, 'id'));

$klaidos = [];
$redaguojamas = null;
$forma = ['adresas' => '', 'kategorijos_id' => (int)get('kategorija'), 'pastaba' => ''];
$masine = ['adresai' => '', 'kategorijos_id' => (int)get('kategorija')];
$masines_klaidos = [];

if (get('redaguoti') !== '') {
    $redaguojamas = vienas('SELECT * FROM url_adresai WHERE id = ?', [(int)get('redaguoti')]);
    if (!$redaguojamas) {
        pranesimas('Adresas nerastas.', 'klaida');
        eiti('saugos_url.php');
    }
    $forma = ['adresas' => $redaguojamas['adresas'], 'kategorijos_id' => (int)$redaguojamas['kategorijos_id'], 'pastaba' => (string)$redaguojamas['pastaba']];
}

if (yra_post()) {
    tikrinti_csrf();
    $veiksmas = post('veiksmas');

    if ($veiksmas === 'salinti') {
        $u = vienas('SELECT * FROM url_adresai WHERE id = ?', [(int)post('id')]);
        if ($u) {
            vykdyti('DELETE FROM url_adresai WHERE id = ?', [(int)$u['id']]);
            pranesimas("Adresas {$u['adresas']} pašalintas iš sąrašo.");
        } else {
            pranesimas('Adresas nerastas.', 'klaida');
        }
        eiti('saugos_url.php?' . http_build_query(['kategorija' => post('grizti_kategorija')]));
    }

    if ($veiksmas === 'masinis') {
        // Keli adresai vienu metu (po vieną eilutėje)
        $masine['adresai'] = post('adresai');
        $masine['kategorijos_id'] = (int)post('kategorijos_id');
        if (!in_array($masine['kategorijos_id'], $kat_id_sarasas, true)) {
            $masines_klaidos['kategorijos_id'] = 'Pasirinkite kategoriją.';
        }
        $eilutes = array_values(array_filter(array_map('trim', preg_split('/\R/u', $masine['adresai']))));
        if (!$eilutes) {
            $masines_klaidos['adresai'] = 'Įveskite bent vieną adresą.';
        } elseif (count($eilutes) > 200) {
            $masines_klaidos['adresai'] = 'Vienu metu galima įvesti daugiausia 200 adresų.';
        }
        $domenai = [];
        $blogi = [];
        foreach ($eilutes as $nr => $eil) {
            $k = null;
            $d = normalizuoti_domena($eil, $k);
            if ($d === null) {
                $blogi[] = ($nr + 1) . ' eil. „' . $eil . '“: ' . $k;
            } else {
                $domenai[$d] = true;
            }
        }
        if ($blogi) {
            $masines_klaidos['adresai'] = "Rasta klaidingų adresų:\n" . implode("\n", $blogi);
        }
        if (!$masines_klaidos) {
            $prideta = 0;
            $jau_yra = 0;
            foreach (array_keys($domenai) as $d) {
                if (reiksme('SELECT COUNT(*) FROM url_adresai WHERE kategorijos_id = ? AND adresas = ?', [$masine['kategorijos_id'], $d])) {
                    $jau_yra++;
                    continue;
                }
                vykdyti('INSERT INTO url_adresai (kategorijos_id, adresas, sukure_id) VALUES (?, ?, ?)', [$masine['kategorijos_id'], $d, (int)$v['id']]);
                $prideta++;
            }
            pranesimas("Pridėta adresų: $prideta" . ($jau_yra ? ", praleista (jau buvo sąraše): $jau_yra" : '') . '.');
            eiti('saugos_url.php?kategorija=' . $masine['kategorijos_id']);
        }
    } elseif ($veiksmas === 'issaugoti') {
        $id = (int)post('id');
        $forma['adresas'] = post('adresas');
        $forma['kategorijos_id'] = (int)post('kategorijos_id');
        $forma['pastaba'] = post('pastaba');

        $k = null;
        $domenas = normalizuoti_domena($forma['adresas'], $k);
        if ($domenas === null) {
            $klaidos['adresas'] = $k;
        }
        if (!in_array($forma['kategorijos_id'], $kat_id_sarasas, true)) {
            $klaidos['kategorijos_id'] = 'Pasirinkite kategoriją.';
        }
        if (mb_strlen($forma['pastaba']) > 255) {
            $klaidos['pastaba'] = 'Pastaba per ilga (daugiausia 255 simboliai).';
        }
        if (!$klaidos && reiksme('SELECT COUNT(*) FROM url_adresai WHERE kategorijos_id = ? AND adresas = ? AND id <> ?', [$forma['kategorijos_id'], $domenas, $id])) {
            $klaidos['adresas'] = "Adresas $domenas šioje kategorijoje jau yra.";
        }
        if (!$klaidos) {
            if ($id) {
                vykdyti('UPDATE url_adresai SET adresas = ?, kategorijos_id = ?, pastaba = ? WHERE id = ?', [$domenas, $forma['kategorijos_id'], $forma['pastaba'] ?: null, $id]);
                pranesimas("Adresas $domenas atnaujintas.");
            } else {
                vykdyti('INSERT INTO url_adresai (kategorijos_id, adresas, pastaba, sukure_id) VALUES (?, ?, ?, ?)', [$forma['kategorijos_id'], $domenas, $forma['pastaba'] ?: null, (int)$v['id']]);
                $kitur = visi('SELECT k.pavadinimas FROM url_adresai u JOIN kategorijos k ON k.id = u.kategorijos_id WHERE u.adresas = ? AND u.kategorijos_id <> ?', [$domenas, $forma['kategorijos_id']]);
                pranesimas("Adresas $domenas pridėtas" . ($domenas !== $forma['adresas'] ? " (įvesta: {$forma['adresas']})" : '') . '.'
                    . ($kitur ? ' Šis domenas taip pat priskirtas: ' . implode(', ', array_column($kitur, 'pavadinimas')) . '.' : ''));
            }
            eiti('saugos_url.php?kategorija=' . $forma['kategorijos_id']);
        }
        if ($id) {
            $redaguojamas = vienas('SELECT * FROM url_adresai WHERE id = ?', [$id]);
        }
    }
}

// Sąrašas su filtrais
$filtras_kat = (int)get('kategorija');
$paieska = get('paieska');
$salygos = [];
$param = [];
if ($filtras_kat) {
    $salygos[] = 'u.kategorijos_id = ?';
    $param[] = $filtras_kat;
}
if ($paieska !== '') {
    $salygos[] = 'u.adresas LIKE ?';
    $param[] = '%' . $paieska . '%';
}
$kur = $salygos ? 'WHERE ' . implode(' AND ', $salygos) : '';
$viso = (int)reiksme("SELECT COUNT(*) FROM url_adresai u $kur", $param);
$puslapis = dabartinis_puslapis();
$adresai = visi(
    "SELECT u.*, k.pavadinimas AS kategorija, v.vardas, v.pavarde
       FROM url_adresai u JOIN kategorijos k ON k.id = u.kategorijos_id
       LEFT JOIN vartotojai v ON v.id = u.sukure_id
       $kur ORDER BY k.pavadinimas, u.adresas
      LIMIT " . PUSLAPYJE_IRASU . ' OFFSET ' . (($puslapis - 1) * PUSLAPYJE_IRASU),
    $param
);

$GLOBALS['puslapio_pavadinimas'] = 'URL sąrašai';
require __DIR__ . '/inc/virsus.php';

function kategoriju_pasirinkimas($kategorijos, $pasirinkta, $klaidingas = false)
{
    $h = '<select name="kategorijos_id"' . ($klaidingas ? ' class="klaidingas"' : '') . '><option value="">– pasirinkite –</option>';
    foreach ($kategorijos as $k) {
        $h .= '<option value="' . (int)$k['id'] . '"' . ((int)$k['id'] === (int)$pasirinkta ? ' selected' : '') . '>' . e($k['pavadinimas']) . '</option>';
    }
    return $h . '</select>';
}
?>
<?php if (!$kategorijos): ?>
    <div class="pranesimas info">Pirmiausia sukurkite bent vieną <a href="saugos_kategorijos.php">kategoriją</a>.</div>
<?php endif; ?>
<div class="dvi-kolonos platesne-kaire">
    <div class="kortele">
        <h1>Kontroliuojami URL adresai</h1>
        <p class="pastaba">Sąraše saugomas domenas. Užblokavus domeną, blokuojami ir visi jo subdomenai
           (pvz. facebook.com blokuoja ir m.facebook.com).</p>
        <form method="get" class="filtrai">
            <div><label>Kategorija</label>
                <select name="kategorija"><option value="">Visos</option>
                <?php foreach ($kategorijos as $k): ?>
                    <option value="<?= (int)$k['id'] ?>"<?= (int)$k['id'] === $filtras_kat ? ' selected' : '' ?>><?= e($k['pavadinimas']) ?></option>
                <?php endforeach; ?></select></div>
            <div><label>Paieška</label><input type="text" name="paieska" value="<?= e($paieska) ?>" placeholder="domeno dalis"></div>
            <div><button class="mygtukas" type="submit">Filtruoti</button></div>
        </form>
        <p>Rasta adresų: <strong><?= $viso ?></strong></p>
        <?php if ($adresai): ?>
        <table class="lentele">
            <tr><th>Domenas</th><th>Kategorija</th><th>Pastaba</th><th>Pridėjo</th><th></th></tr>
            <?php foreach ($adresai as $u): ?>
            <tr>
                <td><strong><?= e($u['adresas']) ?></strong></td>
                <td><?= e($u['kategorija']) ?></td>
                <td><?= e($u['pastaba'] ?? '') ?></td>
                <td><small><?= e(trim(($u['vardas'] ?? '') . ' ' . ($u['pavarde'] ?? ''))) ?><br><?= e(data_laikas($u['sukurta'])) ?></small></td>
                <td class="veiksmai">
                    <a class="mygtukas mazas antrinis" href="?redaguoti=<?= (int)$u['id'] ?>">Redaguoti</a>
                    <form method="post" onsubmit="return confirm('Pašalinti adresą iš sąrašo?');">
                        <?= csrf_laukas() ?>
                        <input type="hidden" name="veiksmas" value="salinti">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="grizti_kategorija" value="<?= $filtras_kat ?: '' ?>">
                        <button class="mygtukas mazas pavojingas" type="submit">Šalinti</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?= puslapiavimas($viso, $puslapis) ?>
        <?php endif; ?>
    </div>
    <div>
        <div class="kortele">
            <h2><?= $redaguojamas ? 'Redaguoti adresą' : 'Pridėti adresą' ?></h2>
            <form method="post" novalidate>
                <?= csrf_laukas() ?>
                <input type="hidden" name="veiksmas" value="issaugoti">
                <input type="hidden" name="id" value="<?= $redaguojamas ? (int)$redaguojamas['id'] : 0 ?>">
                <label>URL arba domenas *</label>
                <input type="text" name="adresas" maxlength="500" value="<?= e($forma['adresas']) ?>" placeholder="pvz. https://www.tiktok.com"<?= klaidos_klase($klaidos, 'adresas') ?>>
                <?= lauko_klaida($klaidos, 'adresas') ?>
                <label>Kategorija *</label>
                <?= kategoriju_pasirinkimas($kategorijos, $forma['kategorijos_id'], isset($klaidos['kategorijos_id'])) ?>
                <?= lauko_klaida($klaidos, 'kategorijos_id') ?>
                <label>Pastaba</label>
                <input type="text" name="pastaba" maxlength="255" value="<?= e($forma['pastaba']) ?>"<?= klaidos_klase($klaidos, 'pastaba') ?>>
                <?= lauko_klaida($klaidos, 'pastaba') ?>
                <button class="mygtukas" type="submit"><?= $redaguojamas ? 'Išsaugoti' : 'Pridėti' ?></button>
                <?php if ($redaguojamas): ?><a class="mygtukas antrinis" href="saugos_url.php">Atšaukti</a><?php endif; ?>
            </form>
        </div>
        <?php if (!$redaguojamas): ?>
        <div class="kortele">
            <h2>Pridėti kelis adresus</h2>
            <form method="post" novalidate>
                <?= csrf_laukas() ?>
                <input type="hidden" name="veiksmas" value="masinis">
                <label>Kategorija *</label>
                <?= kategoriju_pasirinkimas($kategorijos, $masine['kategorijos_id'], isset($masines_klaidos['kategorijos_id'])) ?>
                <?= lauko_klaida($masines_klaidos, 'kategorijos_id') ?>
                <label>Adresai (po vieną eilutėje) *</label>
                <textarea name="adresai" rows="6" placeholder="poki.com&#10;www.miniclip.com"<?= klaidos_klase($masines_klaidos, 'adresai') ?>><?= e($masine['adresai']) ?></textarea>
                <?php if (isset($masines_klaidos['adresai'])): ?><div class="lauko-klaida"><?= nl2br(e($masines_klaidos['adresai'])) ?></div><?php endif; ?>
                <button class="mygtukas" type="submit">Pridėti sąrašą</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
