<?php
// Administratoriaus pradinis puslapis – sistemos apžvalga
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('admin');

$grupes = visi(
    'SELECT g.*, COUNT(m.vartotojo_id) AS mokiniu,
            GROUP_CONCAT(DISTINCT m.klase ORDER BY m.klases_nr, m.klase SEPARATOR ", ") AS klases
       FROM mokiniu_grupes g LEFT JOIN mokiniai m ON m.grupes_id = g.id
      GROUP BY g.id ORDER BY g.klase_nuo'
);
$skaiciai = [
    'Darbuotojų paskyrų'   => reiksme("SELECT COUNT(*) FROM vartotojai WHERE role <> 'mokinys'"),
    'Mokinių paskyrų'      => reiksme("SELECT COUNT(*) FROM vartotojai WHERE role = 'mokinys'"),
    'URL kategorijų'       => reiksme('SELECT COUNT(*) FROM kategorijos'),
    'Kontroliuojamų URL'   => reiksme('SELECT COUNT(*) FROM url_adresai'),
    'Bandymų šiandien'     => reiksme('SELECT COUNT(*) FROM bandymai WHERE DATE(laikas) = CURDATE()'),
    'Užblokuota per 7 d.'  => reiksme("SELECT COUNT(*) FROM bandymai WHERE rezultatas = 'draudziama' AND laikas >= NOW() - INTERVAL 7 DAY"),
];

$GLOBALS['puslapio_pavadinimas'] = 'Apžvalga';
require __DIR__ . '/inc/virsus.php';
?>
<h1>Sistemos apžvalga</h1>
<div class="statistika">
    <?php foreach ($skaiciai as $pav => $sk): ?>
    <div class="kortele stat"><div class="stat-skaicius"><?= (int)$sk ?></div><div class="pastaba"><?= e($pav) ?></div></div>
    <?php endforeach; ?>
</div>
<div class="kortele">
    <h2>Mokinių grupės</h2>
    <table class="lentele">
        <tr><th>Grupė</th><th>Klasių intervalas</th><th class="sk">Mokinių</th><th>Klasės sistemoje</th></tr>
        <?php foreach ($grupes as $g): ?>
        <tr><td><strong><?= e($g['pavadinimas']) ?></strong></td><td><?= (int)$g['klase_nuo'] ?>–<?= (int)$g['klase_iki'] ?></td>
            <td class="sk"><a href="admin_mokiniai.php?grupe=<?= (int)$g['id'] ?>"><?= (int)$g['mokiniu'] ?></a></td><td><?= e($g['klases'] ?? '–') ?></td></tr>
        <?php endforeach; ?>
    </table>
    <p><a class="mygtukas" href="admin_importas.php">Importuoti mokinių sąrašą</a>
       <a class="mygtukas antrinis" href="admin_vartotojai.php">Valdyti darbuotojų paskyras</a></p>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
