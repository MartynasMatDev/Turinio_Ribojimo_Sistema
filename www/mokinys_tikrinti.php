<?php
// Mokinys imituoja bandymą atidaryti URL. Sistema praneša, ar leidžiama, ir užregistruoja bandymą.
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/tikrinimas.php';
$v = reikalauti_roles('mokinys');

$klaida = null;
$url = '';
$rezultatas = null;

if (yra_post()) {
    tikrinti_csrf();
    $url = post('url');
    $host = normalizuoti_domena($url, $klaida);
    if ($host !== null) {
        $rezultatas = tikrinti_prieiga($host, (int)$v['grupes_id']);
        $rezultatas['domenas'] = $host;
        $p = $rezultatas['priskirtas'];
        // Bandymo registravimas
        vykdyti(
            'INSERT INTO bandymai (mokinio_id, ivestas_url, domenas, rezultatas, kategorijos_id, url_id, ip_adresas, laikas)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                (int)$v['id'], mb_substr($url, 0, 500), $host,
                $rezultatas['leidziama'] ? 'leidziama' : 'draudziama',
                $p ? (int)$p['kategorijos_id'] : null,
                $p ? (int)$p['url_id'] : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }
}

// Kategorijos, uždraustos šio mokinio grupei
$uzdraustos = visi(
    'SELECT k.pavadinimas FROM draudimai d JOIN kategorijos k ON k.id = d.kategorijos_id
      WHERE d.grupes_id = ? ORDER BY k.pavadinimas',
    [(int)$v['grupes_id']]
);
$paskutiniai = visi(
    'SELECT b.*, k.pavadinimas AS kategorija FROM bandymai b
       LEFT JOIN kategorijos k ON k.id = b.kategorijos_id
      WHERE b.mokinio_id = ? ORDER BY b.laikas DESC, b.id DESC LIMIT 5',
    [(int)$v['id']]
);

$GLOBALS['puslapio_pavadinimas'] = 'Naršymo imitacija';
require __DIR__ . '/inc/virsus.php';
?>
<div class="kortele">
    <h1>Naršymo imitacija</h1>
    <p>Įveskite interneto svetainės adresą taip, kaip įvestumėte naršyklėje. Sistema patikrins,
       ar mokyklos bevieliame tinkle jums leidžiama ją atidaryti.</p>
    <form method="post" class="adreso-juosta" novalidate>
        <?= csrf_laukas() ?>
        <span class="adreso-ikona">🌐</span>
        <input type="text" name="url" value="<?= e($url) ?>" placeholder="pvz. www.wikipedia.org arba https://www.youtube.com/watch"
               maxlength="500" autofocus<?= $klaida ? ' class="klaidingas"' : '' ?>>
        <button class="mygtukas" type="submit">Eiti</button>
    </form>
    <?php if ($klaida): ?>
        <div class="lauko-klaida"><?= e($klaida) ?></div>
    <?php endif; ?>

    <?php if ($rezultatas): ?>
        <?php if ($rezultatas['leidziama']): ?>
            <div class="rezultatas leidziama">
                <div class="rez-ikona">✔</div>
                <div>
                    <h2>Prieiga leidžiama</h2>
                    <p>Svetainė <strong><?= e($rezultatas['domenas']) ?></strong> gali būti atidaryta.</p>
                    <?php if ($rezultatas['priskirtas']): ?>
                        <p class="pastaba">Adresas priskirtas kategorijai „<?= e($rezultatas['priskirtas']['kategorija']) ?>“,
                            tačiau jūsų grupei ji neuždrausta.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="rezultatas draudziama">
                <div class="rez-ikona">⛔</div>
                <div>
                    <h2>Prieiga draudžiama</h2>
                    <p>Svetainė <strong><?= e($rezultatas['domenas']) ?></strong> priklauso kategorijai
                       „<strong><?= e($rezultatas['draudziantis']['kategorija']) ?></strong>“, kuri uždrausta grupei
                       „<?= e($v['grupes_pavadinimas']) ?>“.</p>
                    <p class="pastaba">Bandymas užregistruotas ir matomas mokyklos saugos įgaliotiniui.</p>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="dvi-kolonos">
    <div class="kortele">
        <h2>Jūsų grupė: <?= e($v['grupes_pavadinimas']) ?></h2>
        <p>Klasė: <strong><?= e($v['klase']) ?></strong></p>
        <p>Uždraustos kategorijos:</p>
        <?php if ($uzdraustos): ?>
            <div class="zymos">
                <?php foreach ($uzdraustos as $k): ?><span class="zyma raudona"><?= e($k['pavadinimas']) ?></span><?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="pastaba">Jūsų grupei draudimų nėra.</p>
        <?php endif; ?>
    </div>
    <div class="kortele">
        <h2>Paskutiniai bandymai</h2>
        <?php if ($paskutiniai): ?>
        <table class="lentele">
            <tr><th>Laikas</th><th>Domenas</th><th>Rezultatas</th></tr>
            <?php foreach ($paskutiniai as $b): ?>
                <tr>
                    <td><?= e(data_laikas($b['laikas'])) ?></td>
                    <td><?= e($b['domenas']) ?></td>
                    <td><?= $b['rezultatas'] === 'leidziama'
                            ? '<span class="zyma zalia">Leidžiama</span>'
                            : '<span class="zyma raudona">Draudžiama</span>' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p><a href="mokinys_istorija.php">Visi mano bandymai →</a></p>
        <?php else: ?>
            <p class="pastaba">Bandymų dar nėra.</p>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
