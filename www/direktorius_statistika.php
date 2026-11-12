<?php
// Direktoriaus suvestinė: užblokuotų bandymų statistika pagal grupes ir kategorijas
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('direktorius');

$klaidos = [];
$nuo = get('nuo', date('Y-m-d', strtotime('-30 days')));
$iki = get('iki', date('Y-m-d'));
if (!tinkama_data($nuo)) {
    $klaidos['nuo'] = 'Neteisinga data (formatas MMMM-MM-DD).';
}
if (!tinkama_data($iki)) {
    $klaidos['iki'] = 'Neteisinga data (formatas MMMM-MM-DD).';
}
if (!$klaidos && $nuo > $iki) {
    $klaidos['iki'] = 'Pabaigos data negali būti ankstesnė už pradžios datą.';
}

$grupes = visi('SELECT * FROM mokiniu_grupes ORDER BY klase_nuo');
$lentele = [];
$suvestine = [];
if (!$klaidos) {
    $param = [$nuo . ' 00:00:00', $iki . ' 23:59:59'];
    foreach (visi(
        "SELECT k.pavadinimas AS kategorija, m.grupes_id, COUNT(*) AS kiek
           FROM bandymai b
           JOIN mokiniai m ON m.vartotojo_id = b.mokinio_id
           LEFT JOIN kategorijos k ON k.id = b.kategorijos_id
          WHERE b.rezultatas = 'draudziama' AND b.laikas BETWEEN ? AND ?
          GROUP BY k.pavadinimas, m.grupes_id
          ORDER BY k.pavadinimas",
        $param
    ) as $r) {
        $pav = $r['kategorija'] ?? '(kategorija pašalinta)';
        $lentele[$pav][(int)$r['grupes_id']] = (int)$r['kiek'];
    }
    $suvestine = visi(
        "SELECT g.id, g.pavadinimas,
                COUNT(DISTINCT m.vartotojo_id) AS mokiniu,
                COUNT(b.id) AS bandymu,
                SUM(b.rezultatas = 'draudziama') AS uzblokuota,
                COUNT(DISTINCT CASE WHEN b.rezultatas = 'draudziama' THEN b.mokinio_id END) AS pazeideju
           FROM mokiniu_grupes g
           LEFT JOIN mokiniai m ON m.grupes_id = g.id
           LEFT JOIN bandymai b ON b.mokinio_id = m.vartotojo_id AND b.laikas BETWEEN ? AND ?
          GROUP BY g.id, g.pavadinimas ORDER BY g.klase_nuo",
        $param
    );
    $top = visi(
        "SELECT b.domenas, COUNT(*) AS kiek FROM bandymai b
          WHERE b.rezultatas = 'draudziama' AND b.laikas BETWEEN ? AND ?
          GROUP BY b.domenas ORDER BY kiek DESC, b.domenas LIMIT 10",
        $param
    );
}

$GLOBALS['puslapio_pavadinimas'] = 'Statistika';
require __DIR__ . '/inc/virsus.php';
?>
<div class="kortele">
    <h1>Užblokuotų bandymų statistika</h1>
    <form method="get" class="filtrai" novalidate>
        <div><label>Nuo</label><input type="date" name="nuo" value="<?= e($nuo) ?>"<?= klaidos_klase($klaidos, 'nuo') ?>><?= lauko_klaida($klaidos, 'nuo') ?></div>
        <div><label>Iki</label><input type="date" name="iki" value="<?= e($iki) ?>"<?= klaidos_klase($klaidos, 'iki') ?>><?= lauko_klaida($klaidos, 'iki') ?></div>
        <div><button class="mygtukas" type="submit">Rodyti</button></div>
    </form>
</div>
<?php if (!$klaidos): ?>
<div class="statistika">
    <?php foreach ($suvestine as $s): ?>
    <div class="kortele stat">
        <h3><?= e($s['pavadinimas']) ?></h3>
        <div class="stat-skaicius"><?= (int)$s['uzblokuota'] ?></div>
        <div class="pastaba">užblokuotų bandymų iš <?= (int)$s['bandymu'] ?></div>
        <div class="pastaba"><?= (int)$s['pazeideju'] ?> iš <?= (int)$s['mokiniu'] ?> mokinių bandė atidaryti draudžiamą turinį</div>
    </div>
    <?php endforeach; ?>
</div>
<div class="dvi-kolonos">
    <div class="kortele">
        <h2>Pagal kategorijas ir grupes</h2>
        <?php if ($lentele): ?>
        <table class="lentele">
            <tr><th>Kategorija</th><?php foreach ($grupes as $g): ?><th class="sk"><?= e($g['pavadinimas']) ?></th><?php endforeach; ?><th class="sk">Iš viso</th></tr>
            <?php foreach ($lentele as $kat => $eil): ?>
            <tr><td><?= e($kat) ?></td>
                <?php foreach ($grupes as $g): ?><td class="sk"><?= $eil[(int)$g['id']] ?? 0 ?></td><?php endforeach; ?>
                <td class="sk"><strong><?= array_sum($eil) ?></strong></td></tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?><p class="pastaba">Pasirinktu laikotarpiu užblokuotų bandymų nėra.</p><?php endif; ?>
    </div>
    <div class="kortele">
        <h2>Dažniausiai blokuoti domenai</h2>
        <?php if ($top): ?>
        <table class="lentele">
            <tr><th>Domenas</th><th class="sk">Bandymų</th></tr>
            <?php foreach ($top as $t): ?><tr><td><?= e($t['domenas']) ?></td><td class="sk"><?= (int)$t['kiek'] ?></td></tr><?php endforeach; ?>
        </table>
        <?php else: ?><p class="pastaba">Duomenų nėra.</p><?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/inc/apacia.php'; ?>
