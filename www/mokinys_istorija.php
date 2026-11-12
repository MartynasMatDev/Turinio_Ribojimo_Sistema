<?php
// Mokinio bandymų istorija
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('mokinys');

$puslapis = dabartinis_puslapis();
$viso = (int)reiksme('SELECT COUNT(*) FROM bandymai WHERE mokinio_id = ?', [(int)$v['id']]);
$nuo = ($puslapis - 1) * PUSLAPYJE_IRASU;
$bandymai = visi(
    'SELECT b.*, k.pavadinimas AS kategorija FROM bandymai b
       LEFT JOIN kategorijos k ON k.id = b.kategorijos_id
      WHERE b.mokinio_id = ? ORDER BY b.laikas DESC, b.id DESC LIMIT ' . PUSLAPYJE_IRASU . ' OFFSET ' . (int)$nuo,
    [(int)$v['id']]
);
$draudziamu = (int)reiksme("SELECT COUNT(*) FROM bandymai WHERE mokinio_id = ? AND rezultatas = 'draudziama'", [(int)$v['id']]);

$GLOBALS['puslapio_pavadinimas'] = 'Mano bandymai';
require __DIR__ . '/inc/virsus.php';
?>
<div class="kortele">
    <h1>Mano bandymai</h1>
    <p>Iš viso bandymų: <strong><?= $viso ?></strong>, iš jų užblokuota: <strong><?= $draudziamu ?></strong>.</p>
    <?php if ($bandymai): ?>
    <table class="lentele">
        <tr><th>Laikas</th><th>Įvestas adresas</th><th>Domenas</th><th>Kategorija</th><th>Rezultatas</th></tr>
        <?php foreach ($bandymai as $b): ?>
        <tr>
            <td><?= e(data_laikas($b['laikas'])) ?></td>
            <td class="ilgas"><?= e($b['ivestas_url']) ?></td>
            <td><?= e($b['domenas']) ?></td>
            <td><?= e($b['kategorija'] ?? '–') ?></td>
            <td><?= $b['rezultatas'] === 'leidziama'
                    ? '<span class="zyma zalia">Leidžiama</span>'
                    : '<span class="zyma raudona">Draudžiama</span>' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?= puslapiavimas($viso, $puslapis) ?>
    <?php else: ?>
        <p class="pastaba">Bandymų dar nėra. <a href="mokinys_tikrinti.php">Išbandykite naršymą.</a></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
