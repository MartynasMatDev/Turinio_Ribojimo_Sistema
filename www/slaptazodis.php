<?php
// Savo slaptažodžio keitimas (visoms rolėms)
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles(['admin', 'saugos', 'direktorius', 'mokinys']);

$klaidos = [];
if (yra_post()) {
    tikrinti_csrf();
    $dabartinis = $_POST['dabartinis'] ?? '';
    $naujas = $_POST['naujas'] ?? '';
    $pakartotas = $_POST['pakartotas'] ?? '';

    if (!password_verify((string)$dabartinis, $v['slaptazodis'])) {
        $klaidos['dabartinis'] = 'Neteisingas dabartinis slaptažodis.';
    }
    $k = slaptazodzio_klaida((string)$naujas);
    if ($k) {
        $klaidos['naujas'] = $k;
    } elseif ($naujas !== $pakartotas) {
        $klaidos['pakartotas'] = 'Slaptažodžiai nesutampa.';
    } elseif ($naujas === $dabartinis) {
        $klaidos['naujas'] = 'Naujas slaptažodis turi skirtis nuo dabartinio.';
    }

    if (!$klaidos) {
        vykdyti('UPDATE vartotojai SET slaptazodis = ? WHERE id = ?', [password_hash($naujas, PASSWORD_DEFAULT), (int)$v['id']]);
        pranesimas('Slaptažodis sėkmingai pakeistas.');
        eiti('slaptazodis.php');
    }
}

$GLOBALS['puslapio_pavadinimas'] = 'Slaptažodžio keitimas';
require __DIR__ . '/inc/virsus.php';
?>
<div class="kortele siaura">
    <h1>Slaptažodžio keitimas</h1>
    <form method="post" novalidate>
        <?= csrf_laukas() ?>
        <label>Dabartinis slaptažodis</label>
        <input type="password" name="dabartinis"<?= klaidos_klase($klaidos, 'dabartinis') ?>>
        <?= lauko_klaida($klaidos, 'dabartinis') ?>
        <label>Naujas slaptažodis</label>
        <input type="password" name="naujas"<?= klaidos_klase($klaidos, 'naujas') ?>>
        <?= lauko_klaida($klaidos, 'naujas') ?>
        <div class="uzuomina">Bent 8 simboliai, bent viena raidė ir vienas skaitmuo.</div>
        <label>Pakartokite naują slaptažodį</label>
        <input type="password" name="pakartotas"<?= klaidos_klase($klaidos, 'pakartotas') ?>>
        <?= lauko_klaida($klaidos, 'pakartotas') ?>
        <button class="mygtukas" type="submit">Pakeisti slaptažodį</button>
    </form>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
