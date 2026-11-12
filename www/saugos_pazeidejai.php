<?php
// Saugos įgaliotinis mato mokinius, kurie bandė prisijungti prie blokuojamų URL
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('saugos');

$klaidos = [];
$nuo = get('nuo', date('Y-m-d', strtotime('-30 days')));
$iki = get('iki', date('Y-m-d'));
$klase = get('klase');
$kategorija = (int)get('kategorija');
if (!tinkama_data($nuo)) {
    $klaidos['nuo'] = 'Neteisinga data.';
}
if (!tinkama_data($iki)) {
    $klaidos['iki'] = 'Neteisinga data.';
}
if (!$klaidos && $nuo > $iki) {
    $klaidos['iki'] = 'Pabaigos data ankstesnė už pradžios.';
}

$kategorijos = visi('SELECT id, pavadinimas FROM kategorijos ORDER BY pavadinimas');
$klases = array_column(visi('SELECT klase FROM mokiniai GROUP BY klase ORDER BY MIN(klases_nr), klase'), 'klase');

$salygos = ["b.rezultatas = 'draudziama'"];
$param = [];
if (!$klaidos) {
    $salygos[] = 'b.laikas BETWEEN ? AND ?';
    $param[] = $nuo . ' 00:00:00';
    $param[] = $iki . ' 23:59:59';
}
if ($klase !== '') {
    $salygos[] = 'm.klase = ?';
    $param[] = $klase;
}
if ($kategorija) {
    $salygos[] = 'b.kategorijos_id = ?';
    $param[] = $kategorija;
}
$kur = implode(' AND ', $salygos);

$mokinys_id = (int)get('mokinys');
$pazeidejai = [];
$detales = [];
$mokinys = null;
if (!$klaidos) {
    $pazeidejai = visi(
        "SELECT v.id, v.vardas, v.pavarde, v.prisijungimo_vardas, m.klase, g.pavadinimas AS grupe,
                COUNT(*) AS bandymu, MAX(b.laikas) AS paskutinis,
                GROUP_CONCAT(DISTINCT COALESCE(k.pavadinimas, '?') ORDER BY k.pavadinimas SEPARATOR ', ') AS kategorijos
           FROM bandymai b
           JOIN vartotojai v ON v.id = b.mokinio_id
           JOIN mokiniai m ON m.vartotojo_id = v.id
           JOIN mokiniu_grupes g ON g.id = m.grupes_id
           LEFT JOIN kategorijos k ON k.id = b.kategorijos_id
          WHERE $kur
          GROUP BY v.id, v.vardas, v.pavarde, v.prisijungimo_vardas, m.klase, g.pavadinimas
          ORDER BY bandymu DESC, paskutinis DESC",
        $param
    );
    if ($mokinys_id) {
        $mokinys = vienas('SELECT v.*, m.klase FROM vartotojai v JOIN mokiniai m ON m.vartotojo_id = v.id WHERE v.id = ?', [$mokinys_id]);
        if ($mokinys) {
            $detales = visi(
                "SELECT b.*, k.pavadinimas AS kategorija FROM bandymai b
                   JOIN mokiniai m ON m.vartotojo_id = b.mokinio_id
                   LEFT JOIN kategorijos k ON k.id = b.kategorijos_id
                  WHERE $kur AND b.mokinio_id = ? ORDER BY b.laikas DESC",
                array_merge($param, [$mokinys_id])
            );
        }
    }
}

$GLOBALS['puslapio_pavadinimas'] = 'Pažeidėjai';
require __DIR__ . '/inc/virsus.php';
?>
<div class="kortele">
    <h1>Mokiniai, bandę atidaryti blokuojamus URL</h1>
    <form method="get" class="filtrai" novalidate>
        <div><label>Nuo</label><input type="date" name="nuo" value="<?= e($nuo) ?>"<?= klaidos_klase($klaidos, 'nuo') ?>><?= lauko_klaida($klaidos, 'nuo') ?></div>
        <div><label>Iki</label><input type="date" name="iki" value="<?= e($iki) ?>"<?= klaidos_klase($klaidos, 'iki') ?>><?= lauko_klaida($klaidos, 'iki') ?></div>
        <div><label>Klasė</label><select name="klase"><option value="">Visos</option>
            <?php foreach ($klases as $kl): ?><option<?= $kl === $klase ? ' selected' : '' ?>><?= e($kl) ?></option><?php endforeach; ?></select></div>
        <div><label>Kategorija</label><select name="kategorija"><option value="">Visos</option>
            <?php foreach ($kategorijos as $k): ?><option value="<?= (int)$k['id'] ?>"<?= (int)$k['id'] === $kategorija ? ' selected' : '' ?>><?= e($k['pavadinimas']) ?></option><?php endforeach; ?></select></div>
        <div><button class="mygtukas" type="submit">Rodyti</button></div>
    </form>

    <?php if (!$klaidos): ?>
        <p>Rasta mokinių: <strong><?= count($pazeidejai) ?></strong>, užblokuotų bandymų iš viso:
           <strong><?= array_sum(array_column($pazeidejai, 'bandymu')) ?></strong></p>
        <?php if ($pazeidejai): ?>
        <table class="lentele">
            <tr><th>Mokinys</th><th>Klasė</th><th>Grupė</th><th class="sk">Užblokuota bandymų</th><th>Kategorijos</th><th>Paskutinis bandymas</th><th></th></tr>
            <?php foreach ($pazeidejai as $p): ?>
            <tr<?= (int)$p['id'] === $mokinys_id ? ' class="pazymeta"' : '' ?>>
                <td><strong><?= e($p['vardas'] . ' ' . $p['pavarde']) ?></strong><br><small class="pastaba"><?= e($p['prisijungimo_vardas']) ?></small></td>
                <td><?= e($p['klase']) ?></td>
                <td><?= e($p['grupe']) ?></td>
                <td class="sk"><span class="zyma raudona"><?= (int)$p['bandymu'] ?></span></td>
                <td><?= e($p['kategorijos']) ?></td>
                <td><?= e(data_laikas($p['paskutinis'])) ?></td>
                <td><a class="mygtukas mazas antrinis" href="?<?= e(http_build_query(array_merge($_GET, ['mokinys' => $p['id']]))) ?>#detales">Detaliau</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
            <p class="pranesimas info">Pasirinktu laikotarpiu mokinių, bandžiusių atidaryti draudžiamus adresus, nėra.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($mokinys): ?>
<div class="kortele" id="detales">
    <h2><?= e($mokinys['vardas'] . ' ' . $mokinys['pavarde']) ?> (<?= e($mokinys['klase']) ?> kl.) – užblokuoti bandymai</h2>
    <table class="lentele">
        <tr><th>Laikas</th><th>Įvestas adresas</th><th>Domenas</th><th>Kategorija</th><th>IP</th></tr>
        <?php foreach ($detales as $d): ?>
        <tr><td><?= e(data_laikas($d['laikas'])) ?></td><td class="ilgas"><?= e($d['ivestas_url']) ?></td>
            <td><?= e($d['domenas']) ?></td><td><?= e($d['kategorija'] ?? '(pašalinta)') ?></td><td><?= e($d['ip_adresas'] ?? '') ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/inc/apacia.php'; ?>
