<?php
// Direktorius nustato, kurios kategorijos uždraustos kiekvienai mokinių grupei
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('direktorius');

$grupes = visi('SELECT * FROM mokiniu_grupes ORDER BY klase_nuo');
$kategorijos = visi(
    'SELECT k.*, (SELECT COUNT(*) FROM url_adresai u WHERE u.kategorijos_id = k.id) AS url_sk
       FROM kategorijos k ORDER BY k.pavadinimas'
);

// Esami draudimai: [grupes_id][kategorijos_id] = true
$esami = [];
foreach (visi('SELECT grupes_id, kategorijos_id FROM draudimai') as $d) {
    $esami[(int)$d['grupes_id']][(int)$d['kategorijos_id']] = true;
}

if (yra_post()) {
    tikrinti_csrf();
    $pazymeti = $_POST['draudimas'] ?? [];
    if (!is_array($pazymeti)) {
        $pazymeti = [];
    }
    $grupiu_id = array_column($grupes, 'id');
    $kat_id = array_column($kategorijos, 'id');
    $nauji = [];
    foreach ($pazymeti as $gid => $kats) {
        if (!in_array((string)$gid, array_map('strval', $grupiu_id), true) || !is_array($kats)) {
            continue;
        }
        foreach ($kats as $kid => $reiksme) {
            if (in_array((string)$kid, array_map('strval', $kat_id), true)) {
                $nauji[(int)$gid][(int)$kid] = true;
            }
        }
    }

    // Skaičiuojami pakeitimai
    $prideta = 0;
    $panaikinta = 0;
    db()->begin_transaction();
    foreach ($grupes as $g) {
        $gid = (int)$g['id'];
        foreach ($kategorijos as $k) {
            $kid = (int)$k['id'];
            $buvo = isset($esami[$gid][$kid]);
            $bus = isset($nauji[$gid][$kid]);
            if ($bus && !$buvo) {
                vykdyti('INSERT INTO draudimai (grupes_id, kategorijos_id, nustate_id, nustatyta) VALUES (?, ?, ?, NOW())', [$gid, $kid, (int)$v['id']]);
                $prideta++;
            } elseif (!$bus && $buvo) {
                vykdyti('DELETE FROM draudimai WHERE grupes_id = ? AND kategorijos_id = ?', [$gid, $kid]);
                $panaikinta++;
            }
        }
    }
    db()->commit();

    if ($prideta + $panaikinta === 0) {
        pranesimas('Pakeitimų nebuvo – draudimai liko tokie patys.', 'info');
    } else {
        pranesimas("Draudimai išsaugoti: nustatyta naujų draudimų – $prideta, panaikinta – $panaikinta.");
    }
    eiti('direktorius_draudimai.php');
}

$paskutinis = vienas(
    'SELECT d.nustatyta, v.vardas, v.pavarde FROM draudimai d LEFT JOIN vartotojai v ON v.id = d.nustate_id
      ORDER BY d.nustatyta DESC LIMIT 1'
);

$GLOBALS['puslapio_pavadinimas'] = 'Draudimai';
require __DIR__ . '/inc/virsus.php';
?>
<div class="kortele">
    <h1>Kategorijų draudimai mokinių grupėms</h1>
    <p>Pažymėkite, kurios URL kategorijos uždraustos kiekvienai mokinių grupei. Mokiniui bandant atidaryti
       svetainę iš uždraustos kategorijos, prieiga bus blokuojama.</p>
    <?php if ($paskutinis): ?>
        <p class="pastaba">Paskutinis pakeitimas: <?= e(data_laikas($paskutinis['nustatyta'])) ?>
            <?= $paskutinis['vardas'] ? '(' . e($paskutinis['vardas'] . ' ' . $paskutinis['pavarde']) . ')' : '' ?></p>
    <?php endif; ?>

    <?php if (!$kategorijos): ?>
        <p class="pranesimas info">Kategorijų dar nėra. Jas sukuria saugos įgaliotinis.</p>
    <?php else: ?>
    <form method="post">
        <?= csrf_laukas() ?>
        <table class="lentele matrica">
            <tr>
                <th>Kategorija</th>
                <th class="sk">URL</th>
                <?php foreach ($grupes as $g): ?>
                    <th class="centras"><?= e($g['pavadinimas']) ?><br>
                        <small><?= (int)$g['klase_nuo'] ?>–<?= (int)$g['klase_iki'] ?> kl.</small></th>
                <?php endforeach; ?>
            </tr>
            <?php foreach ($kategorijos as $k): ?>
            <tr>
                <td><strong><?= e($k['pavadinimas']) ?></strong>
                    <?php if ($k['aprasymas']): ?><br><small class="pastaba"><?= e($k['aprasymas']) ?></small><?php endif; ?></td>
                <td class="sk"><?= (int)$k['url_sk'] ?></td>
                <?php foreach ($grupes as $g): ?>
                    <?php $pazymeta = isset($esami[(int)$g['id']][(int)$k['id']]); ?>
                    <td class="centras">
                        <label class="jungiklis">
                            <input type="checkbox" name="draudimas[<?= (int)$g['id'] ?>][<?= (int)$k['id'] ?>]" value="1"<?= $pazymeta ? ' checked' : '' ?>>
                            <span>Uždrausta</span>
                        </label>
                    </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </table>
        <button class="mygtukas" type="submit">Išsaugoti draudimus</button>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
