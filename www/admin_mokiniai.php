<?php
// Administratorius: mokinių sąrašas ir valdymas
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('admin');

$klaidos = [];
$redaguojamas = null;
$forma = [];

if (get('redaguoti') !== '') {
    $redaguojamas = vienas('SELECT v.*, m.klase FROM vartotojai v JOIN mokiniai m ON m.vartotojo_id = v.id WHERE v.id = ?', [(int)get('redaguoti')]);
    if (!$redaguojamas) {
        pranesimas('Mokinys nerastas.', 'klaida');
        eiti('admin_mokiniai.php');
    }
    $forma = ['vardas' => $redaguojamas['vardas'], 'pavarde' => $redaguojamas['pavarde'], 'klase' => $redaguojamas['klase']];
}

if (yra_post()) {
    tikrinti_csrf();
    $veiksmas = post('veiksmas');
    $id = (int)post('id');
    $mok = vienas("SELECT v.*, m.klase FROM vartotojai v JOIN mokiniai m ON m.vartotojo_id = v.id WHERE v.id = ? AND v.role = 'mokinys'", [$id]);
    $grizti = 'admin_mokiniai.php?' . post('grizti');
    if (!$mok) {
        pranesimas('Mokinys nerastas.', 'klaida');
        eiti($grizti);
    }
    $vardas = $mok['vardas'] . ' ' . $mok['pavarde'];

    if ($veiksmas === 'aktyvumas') {
        $naujas = $mok['aktyvus'] ? 0 : 1;
        vykdyti('UPDATE vartotojai SET aktyvus = ? WHERE id = ?', [$naujas, $id]);
        pranesimas($naujas ? "Mokinio $vardas paskyra aktyvuota." : "Mokinio $vardas paskyra užblokuota – prisijungti nebegalės.");
        eiti($grizti);
    } elseif ($veiksmas === 'slaptazodis') {
        $s = generuoti_slaptazodi();
        vykdyti('UPDATE vartotojai SET slaptazodis = ? WHERE id = ?', [password_hash($s, PASSWORD_DEFAULT), $id]);
        pranesimas("Mokiniui $vardas ({$mok['prisijungimo_vardas']}) nustatytas naujas slaptažodis: $s", 'info');
        eiti($grizti);
    } elseif ($veiksmas === 'salinti') {
        vykdyti('DELETE FROM vartotojai WHERE id = ?', [$id]);
        pranesimas("Mokinys $vardas pašalintas kartu su jo bandymų istorija.");
        eiti($grizti);
    } elseif ($veiksmas === 'issaugoti') {
        $forma = ['vardas' => post('vardas'), 'pavarde' => post('pavarde'), 'klase' => post('klase')];
        if (!tinkamas_vardas($forma['vardas'])) {
            $klaidos['vardas'] = 'Vardas turi būti 2–50 raidžių.';
        }
        if (!tinkamas_vardas($forma['pavarde'])) {
            $klaidos['pavarde'] = 'Pavardė turi būti 2–50 raidžių.';
        }
        $kl = atpazinti_klase($forma['klase']);
        if (!$kl) {
            $klaidos['klase'] = 'Neteisinga klasė. Pavyzdžiai: 3A, 7B, 10C, IIIG.';
        }
        if (!$klaidos) {
            $g = grupe_pagal_klase($kl['nr']);
            vykdyti('UPDATE vartotojai SET vardas = ?, pavarde = ? WHERE id = ?', [$forma['vardas'], $forma['pavarde'], $id]);
            vykdyti('UPDATE mokiniai SET klase = ?, klases_nr = ?, grupes_id = ? WHERE vartotojo_id = ?', [$kl['klase'], $kl['nr'], (int)$g['id'], $id]);
            pranesimas("Mokinio {$forma['vardas']} {$forma['pavarde']} duomenys išsaugoti (klasė {$kl['klase']}, grupė: {$g['pavadinimas']}).");
            eiti('admin_mokiniai.php');
        }
        $redaguojamas = $mok;
    }
}

// Filtrai
$grupes = visi('SELECT * FROM mokiniu_grupes ORDER BY klase_nuo');
$klases = array_column(visi('SELECT klase FROM mokiniai GROUP BY klase ORDER BY MIN(klases_nr), klase'), 'klase');
$f_grupe = (int)get('grupe');
$f_klase = get('klase');
$f_paieska = get('paieska');
$salygos = ["v.role = 'mokinys'"];
$param = [];
if ($f_grupe) {
    $salygos[] = 'm.grupes_id = ?';
    $param[] = $f_grupe;
}
if ($f_klase !== '') {
    $salygos[] = 'm.klase = ?';
    $param[] = $f_klase;
}
if ($f_paieska !== '') {
    $salygos[] = '(v.vardas LIKE ? OR v.pavarde LIKE ? OR v.prisijungimo_vardas LIKE ?)';
    array_push($param, "%$f_paieska%", "%$f_paieska%", "%$f_paieska%");
}
$kur = implode(' AND ', $salygos);
$is = 'FROM vartotojai v JOIN mokiniai m ON m.vartotojo_id = v.id JOIN mokiniu_grupes g ON g.id = m.grupes_id';
$viso = (int)reiksme("SELECT COUNT(*) $is WHERE $kur", $param);
$puslapis = dabartinis_puslapis();
$mokiniai = visi(
    "SELECT v.*, m.klase, g.pavadinimas AS grupe,
            (SELECT COUNT(*) FROM bandymai b WHERE b.mokinio_id = v.id) AS bandymu
       $is WHERE $kur ORDER BY m.klases_nr, m.klase, v.pavarde, v.vardas
      LIMIT " . PUSLAPYJE_IRASU . ' OFFSET ' . (($puslapis - 1) * PUSLAPYJE_IRASU),
    $param
);
$grizti = http_build_query($_GET);

$GLOBALS['puslapio_pavadinimas'] = 'Mokiniai';
require __DIR__ . '/inc/virsus.php';
?>
<?php if ($redaguojamas): ?>
<div class="kortele siaura">
    <h2>Redaguoti mokinį: <?= e($redaguojamas['prisijungimo_vardas']) ?></h2>
    <form method="post" novalidate>
        <?= csrf_laukas() ?>
        <input type="hidden" name="veiksmas" value="issaugoti">
        <input type="hidden" name="id" value="<?= (int)$redaguojamas['id'] ?>">
        <label>Vardas *</label>
        <input type="text" name="vardas" maxlength="50" value="<?= e($forma['vardas']) ?>"<?= klaidos_klase($klaidos, 'vardas') ?>><?= lauko_klaida($klaidos, 'vardas') ?>
        <label>Pavardė *</label>
        <input type="text" name="pavarde" maxlength="50" value="<?= e($forma['pavarde']) ?>"<?= klaidos_klase($klaidos, 'pavarde') ?>><?= lauko_klaida($klaidos, 'pavarde') ?>
        <label>Klasė *</label>
        <input type="text" name="klase" maxlength="6" value="<?= e($forma['klase']) ?>"<?= klaidos_klase($klaidos, 'klase') ?>><?= lauko_klaida($klaidos, 'klase') ?>
        <button class="mygtukas" type="submit">Išsaugoti</button>
        <a class="mygtukas antrinis" href="admin_mokiniai.php">Atšaukti</a>
    </form>
</div>
<?php endif; ?>
<div class="kortele">
    <h1>Mokiniai</h1>
    <form method="get" class="filtrai">
        <div><label>Grupė</label><select name="grupe"><option value="">Visos</option>
            <?php foreach ($grupes as $g): ?><option value="<?= (int)$g['id'] ?>"<?= (int)$g['id'] === $f_grupe ? ' selected' : '' ?>><?= e($g['pavadinimas']) ?></option><?php endforeach; ?></select></div>
        <div><label>Klasė</label><select name="klase"><option value="">Visos</option>
            <?php foreach ($klases as $kl): ?><option<?= $kl === $f_klase ? ' selected' : '' ?>><?= e($kl) ?></option><?php endforeach; ?></select></div>
        <div><label>Paieška</label><input type="text" name="paieska" value="<?= e($f_paieska) ?>" placeholder="vardas, pavardė, prisijungimo vardas"></div>
        <div><button class="mygtukas" type="submit">Filtruoti</button></div>
        <div><a class="mygtukas antrinis" href="admin_importas.php">+ Importuoti sąrašą</a></div>
    </form>
    <p>Rasta mokinių: <strong><?= $viso ?></strong></p>
    <?php if ($mokiniai): ?>
    <table class="lentele">
        <tr><th>Mokinys</th><th>Prisijungimo vardas</th><th>Klasė</th><th>Grupė</th><th class="sk">Bandymų</th><th>Paskutinį kartą prisijungė</th><th>Būsena</th><th></th></tr>
        <?php foreach ($mokiniai as $m): ?>
        <tr<?= $m['aktyvus'] ? '' : ' class="neaktyvus"' ?>>
            <td><strong><?= e($m['pavarde'] . ' ' . $m['vardas']) ?></strong></td>
            <td><?= e($m['prisijungimo_vardas']) ?></td>
            <td><?= e($m['klase']) ?></td>
            <td><?= e($m['grupe']) ?></td>
            <td class="sk"><?= (int)$m['bandymu'] ?></td>
            <td><?= e(data_laikas($m['paskutinis_prisijungimas'])) ?></td>
            <td><?= $m['aktyvus'] ? '<span class="zyma zalia">Aktyvus</span>' : '<span class="zyma pilka">Užblokuotas</span>' ?></td>
            <td class="veiksmai">
                <a class="mygtukas mazas antrinis" href="?redaguoti=<?= (int)$m['id'] ?>">Redaguoti</a>
                <?php foreach (['aktyvumas' => $m['aktyvus'] ? 'Blokuoti' : 'Aktyvuoti', 'slaptazodis' => 'Naujas slaptaž.', 'salinti' => 'Šalinti'] as $veiksmas => $tekstas): ?>
                <form method="post"<?= $veiksmas === 'salinti' ? ' onsubmit="return confirm(\'Pašalinti mokinį ir jo bandymų istoriją?\');"' : '' ?>>
                    <?= csrf_laukas() ?>
                    <input type="hidden" name="veiksmas" value="<?= $veiksmas ?>">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="grizti" value="<?= e($grizti) ?>">
                    <button class="mygtukas mazas <?= $veiksmas === 'salinti' ? 'pavojingas' : 'antrinis' ?>" type="submit"><?= $tekstas ?></button>
                </form>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?= puslapiavimas($viso, $puslapis) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
