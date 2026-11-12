<?php
// Saugos įgaliotinis: kategorijų sąrašas, kūrimas, redagavimas, šalinimas
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('saugos');

$klaidos = [];
$redaguojama = null;
$forma = ['pavadinimas' => '', 'aprasymas' => ''];

if (get('redaguoti') !== '') {
    $redaguojama = vienas('SELECT * FROM kategorijos WHERE id = ?', [(int)get('redaguoti')]);
    if (!$redaguojama) {
        pranesimas('Kategorija nerasta.', 'klaida');
        eiti('saugos_kategorijos.php');
    }
    $forma = ['pavadinimas' => $redaguojama['pavadinimas'], 'aprasymas' => (string)$redaguojama['aprasymas']];
}

if (yra_post()) {
    tikrinti_csrf();
    $veiksmas = post('veiksmas');

    if ($veiksmas === 'salinti') {
        $id = (int)post('id');
        $kat = vienas('SELECT * FROM kategorijos WHERE id = ?', [$id]);
        $url_sk = (int)reiksme('SELECT COUNT(*) FROM url_adresai WHERE kategorijos_id = ?', [$id]);
        if (!$kat) {
            pranesimas('Kategorija nerasta.', 'klaida');
        } elseif ($url_sk > 0) {
            pranesimas("Kategorijos „{$kat['pavadinimas']}“ pašalinti negalima: jai priskirta URL adresų ($url_sk). Pirmiausia pašalinkite arba perkelkite adresus.", 'klaida');
        } else {
            vykdyti('DELETE FROM kategorijos WHERE id = ?', [$id]);
            pranesimas("Kategorija „{$kat['pavadinimas']}“ pašalinta.");
        }
        eiti('saugos_kategorijos.php');
    }

    // Kūrimas arba redagavimas
    $forma['pavadinimas'] = post('pavadinimas');
    $forma['aprasymas'] = post('aprasymas');
    $id = (int)post('id');

    $ilgis = mb_strlen($forma['pavadinimas']);
    if ($ilgis === 0) {
        $klaidos['pavadinimas'] = 'Įveskite kategorijos pavadinimą.';
    } elseif ($ilgis < 2 || $ilgis > 60) {
        $klaidos['pavadinimas'] = 'Pavadinimas turi būti nuo 2 iki 60 simbolių.';
    } elseif (!preg_match('/^[\p{L}\d][\p{L}\d\s.,()\/&+-]*$/u', $forma['pavadinimas'])) {
        $klaidos['pavadinimas'] = 'Pavadinime leidžiamos raidės, skaičiai, tarpai ir simboliai . , ( ) / & + -';
    } elseif (reiksme('SELECT COUNT(*) FROM kategorijos WHERE pavadinimas = ? AND id <> ?', [$forma['pavadinimas'], $id])) {
        $klaidos['pavadinimas'] = 'Kategorija tokiu pavadinimu jau yra.';
    }
    if (mb_strlen($forma['aprasymas']) > 255) {
        $klaidos['aprasymas'] = 'Aprašymas per ilgas (daugiausia 255 simboliai).';
    }

    if (!$klaidos) {
        if ($id) {
            vykdyti('UPDATE kategorijos SET pavadinimas = ?, aprasymas = ? WHERE id = ?', [$forma['pavadinimas'], $forma['aprasymas'] ?: null, $id]);
            pranesimas("Kategorija „{$forma['pavadinimas']}“ atnaujinta.");
        } else {
            vykdyti('INSERT INTO kategorijos (pavadinimas, aprasymas) VALUES (?, ?)', [$forma['pavadinimas'], $forma['aprasymas'] ?: null]);
            pranesimas("Kategorija „{$forma['pavadinimas']}“ sukurta. Dabar galite jai priskirti URL adresus.");
        }
        eiti('saugos_kategorijos.php');
    }
    if ($id) {
        $redaguojama = vienas('SELECT * FROM kategorijos WHERE id = ?', [$id]);
    }
}

$kategorijos = visi(
    'SELECT k.*,
            (SELECT COUNT(*) FROM url_adresai u WHERE u.kategorijos_id = k.id) AS url_sk,
            (SELECT GROUP_CONCAT(g.pavadinimas ORDER BY g.klase_nuo SEPARATOR ", ")
               FROM draudimai d JOIN mokiniu_grupes g ON g.id = d.grupes_id WHERE d.kategorijos_id = k.id) AS draudziama
       FROM kategorijos k ORDER BY k.pavadinimas'
);

$GLOBALS['puslapio_pavadinimas'] = 'Kategorijos';
require __DIR__ . '/inc/virsus.php';
?>
<div class="dvi-kolonos platesne-kaire">
    <div class="kortele">
        <h1>URL kategorijos</h1>
        <?php if ($kategorijos): ?>
        <table class="lentele">
            <tr><th>Pavadinimas</th><th class="sk">URL</th><th>Draudžiama grupėms</th><th></th></tr>
            <?php foreach ($kategorijos as $k): ?>
            <tr>
                <td><strong><?= e($k['pavadinimas']) ?></strong>
                    <?php if ($k['aprasymas']): ?><br><small class="pastaba"><?= e($k['aprasymas']) ?></small><?php endif; ?></td>
                <td class="sk"><a href="saugos_url.php?kategorija=<?= (int)$k['id'] ?>"><?= (int)$k['url_sk'] ?></a></td>
                <td><?= $k['draudziama'] ? e($k['draudziama']) : '<span class="pastaba">–</span>' ?></td>
                <td class="veiksmai">
                    <a class="mygtukas mazas antrinis" href="?redaguoti=<?= (int)$k['id'] ?>">Redaguoti</a>
                    <form method="post" onsubmit="return confirm('Ar tikrai pašalinti kategoriją?');">
                        <?= csrf_laukas() ?>
                        <input type="hidden" name="veiksmas" value="salinti">
                        <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                        <button class="mygtukas mazas pavojingas" type="submit">Šalinti</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?><p class="pastaba">Kategorijų dar nėra.</p><?php endif; ?>
    </div>
    <div class="kortele">
        <h2><?= $redaguojama ? 'Redaguoti kategoriją' : 'Nauja kategorija' ?></h2>
        <form method="post" novalidate>
            <?= csrf_laukas() ?>
            <input type="hidden" name="id" value="<?= $redaguojama ? (int)$redaguojama['id'] : 0 ?>">
            <label>Pavadinimas *</label>
            <input type="text" name="pavadinimas" maxlength="60" value="<?= e($forma['pavadinimas']) ?>"<?= klaidos_klase($klaidos, 'pavadinimas') ?>>
            <?= lauko_klaida($klaidos, 'pavadinimas') ?>
            <label>Aprašymas</label>
            <textarea name="aprasymas" rows="3" maxlength="255"<?= klaidos_klase($klaidos, 'aprasymas') ?>><?= e($forma['aprasymas']) ?></textarea>
            <?= lauko_klaida($klaidos, 'aprasymas') ?>
            <button class="mygtukas" type="submit"><?= $redaguojama ? 'Išsaugoti pakeitimus' : 'Sukurti kategoriją' ?></button>
            <?php if ($redaguojama): ?><a class="mygtukas antrinis" href="saugos_kategorijos.php">Atšaukti</a><?php endif; ?>
        </form>
    </div>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
