<?php
// Saugos įgaliotinis: visų mokinių bandymų žurnalas
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('saugos');

$klaidos = [];
$nuo = get('nuo', date('Y-m-d', strtotime('-30 days')));
$iki = get('iki', date('Y-m-d'));
$rezultatas = get('rezultatas');
$klase = get('klase');
$paieska = get('paieska');
if (!tinkama_data($nuo)) {
    $klaidos['nuo'] = 'Neteisinga data.';
}
if (!tinkama_data($iki)) {
    $klaidos['iki'] = 'Neteisinga data.';
}
if (!$klaidos && $nuo > $iki) {
    $klaidos['iki'] = 'Pabaigos data ankstesnė už pradžios.';
}
if (!in_array($rezultatas, ['', 'leidziama', 'draudziama'], true)) {
    $rezultatas = '';
}

$klases = array_column(visi('SELECT klase FROM mokiniai GROUP BY klase ORDER BY MIN(klases_nr), klase'), 'klase');
$bandymai = [];
$viso = 0;
$puslapis = dabartinis_puslapis();
if (!$klaidos) {
    $salygos = ['b.laikas BETWEEN ? AND ?'];
    $param = [$nuo . ' 00:00:00', $iki . ' 23:59:59'];
    if ($rezultatas !== '') {
        $salygos[] = 'b.rezultatas = ?';
        $param[] = $rezultatas;
    }
    if ($klase !== '') {
        $salygos[] = 'm.klase = ?';
        $param[] = $klase;
    }
    if ($paieska !== '') {
        $salygos[] = '(b.domenas LIKE ? OR v.pavarde LIKE ? OR v.vardas LIKE ?)';
        $param[] = '%' . $paieska . '%';
        $param[] = '%' . $paieska . '%';
        $param[] = '%' . $paieska . '%';
    }
    $kur = implode(' AND ', $salygos);
    $is = 'FROM bandymai b JOIN vartotojai v ON v.id = b.mokinio_id JOIN mokiniai m ON m.vartotojo_id = v.id
           LEFT JOIN kategorijos k ON k.id = b.kategorijos_id';
    $viso = (int)reiksme("SELECT COUNT(*) $is WHERE $kur", $param);
    $bandymai = visi(
        "SELECT b.*, v.vardas, v.pavarde, m.klase, k.pavadinimas AS kategorija $is WHERE $kur
          ORDER BY b.laikas DESC, b.id DESC LIMIT " . PUSLAPYJE_IRASU . ' OFFSET ' . (($puslapis - 1) * PUSLAPYJE_IRASU),
        $param
    );
}

$GLOBALS['puslapio_pavadinimas'] = 'Bandymų žurnalas';
require __DIR__ . '/inc/virsus.php';
?>
<div class="kortele">
    <h1>Mokinių bandymų žurnalas</h1>
    <form method="get" class="filtrai" novalidate>
        <div><label>Nuo</label><input type="date" name="nuo" value="<?= e($nuo) ?>"<?= klaidos_klase($klaidos, 'nuo') ?>><?= lauko_klaida($klaidos, 'nuo') ?></div>
        <div><label>Iki</label><input type="date" name="iki" value="<?= e($iki) ?>"<?= klaidos_klase($klaidos, 'iki') ?>><?= lauko_klaida($klaidos, 'iki') ?></div>
        <div><label>Rezultatas</label><select name="rezultatas">
            <option value="">Visi</option>
            <option value="leidziama"<?= $rezultatas === 'leidziama' ? ' selected' : '' ?>>Leidžiama</option>
            <option value="draudziama"<?= $rezultatas === 'draudziama' ? ' selected' : '' ?>>Draudžiama</option></select></div>
        <div><label>Klasė</label><select name="klase"><option value="">Visos</option>
            <?php foreach ($klases as $kl): ?><option<?= $kl === $klase ? ' selected' : '' ?>><?= e($kl) ?></option><?php endforeach; ?></select></div>
        <div><label>Paieška</label><input type="text" name="paieska" value="<?= e($paieska) ?>" placeholder="domenas, vardas, pavardė"></div>
        <div><button class="mygtukas" type="submit">Filtruoti</button></div>
    </form>
    <?php if (!$klaidos): ?>
    <p>Rasta įrašų: <strong><?= $viso ?></strong></p>
    <?php if ($bandymai): ?>
    <table class="lentele">
        <tr><th>Laikas</th><th>Mokinys</th><th>Klasė</th><th>Įvestas adresas</th><th>Domenas</th><th>Kategorija</th><th>Rezultatas</th></tr>
        <?php foreach ($bandymai as $b): ?>
        <tr>
            <td><?= e(data_laikas($b['laikas'])) ?></td>
            <td><?= e($b['vardas'] . ' ' . $b['pavarde']) ?></td>
            <td><?= e($b['klase']) ?></td>
            <td class="ilgas"><?= e($b['ivestas_url']) ?></td>
            <td><?= e($b['domenas']) ?></td>
            <td><?= e($b['kategorija'] ?? '–') ?></td>
            <td><?= $b['rezultatas'] === 'leidziama' ? '<span class="zyma zalia">Leidžiama</span>' : '<span class="zyma raudona">Draudžiama</span>' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?= puslapiavimas($viso, $puslapis) ?>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
