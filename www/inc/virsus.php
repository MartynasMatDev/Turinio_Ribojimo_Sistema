<?php
// Puslapio viršus: antraštė, meniu, pranešimai
$v = vartotojas();
$pavadinimas = $GLOBALS['puslapio_pavadinimas'] ?? '';
$dabartinis = basename($_SERVER['SCRIPT_NAME']);

$meniu = [];
if ($v) {
    switch ($v['role']) {
        case 'admin':
            $meniu = [
                'admin_apzvalga.php'   => 'Apžvalga',
                'admin_importas.php'   => 'Mokinių importas',
                'admin_mokiniai.php'   => 'Mokiniai',
                'admin_vartotojai.php' => 'Darbuotojai',
            ];
            break;
        case 'saugos':
            $meniu = [
                'saugos_pazeidejai.php'  => 'Pažeidėjai',
                'saugos_bandymai.php'    => 'Bandymų žurnalas',
                'saugos_kategorijos.php' => 'Kategorijos',
                'saugos_url.php'         => 'URL sąrašai',
            ];
            break;
        case 'direktorius':
            $meniu = [
                'direktorius_draudimai.php'  => 'Draudimai',
                'direktorius_statistika.php' => 'Statistika',
            ];
            break;
        case 'mokinys':
            $meniu = [
                'mokinys_tikrinti.php' => 'Naršyti',
                'mokinys_istorija.php' => 'Mano bandymai',
            ];
            break;
    }
    $meniu['slaptazodis.php'] = 'Slaptažodis';
}
?><!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pavadinimas ? $pavadinimas . ' – ' : '') . SISTEMOS_PAVADINIMAS) ?></title>
    <link rel="stylesheet" href="css/stilius.css">
</head>
<body>
<header class="virsus">
    <div class="virsus-vidus">
        <a class="logotipas" href="<?= $v ? 'pradzia.php' : 'index.php' ?>">
            <span class="logo-zenklas">🛡</span>
            <span>
                <strong><?= e(SISTEMOS_PAVADINIMAS) ?></strong>
                <small>Autorius: <?= e(AUTORIUS) ?></small>
            </span>
        </a>
        <?php if ($v): ?>
        <div class="vartotojo-info">
            <span><?= e($v['vardas'] . ' ' . $v['pavarde']) ?></span>
            <span class="zenklelis role-<?= e($v['role']) ?>"><?= e(roles_pavadinimas($v['role'])) ?>
                <?= $v['role'] === 'mokinys' ? ' · ' . e($v['klase']) . ' kl.' : '' ?></span>
            <a class="atsijungti" href="atsijungti.php">Atsijungti</a>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($meniu): ?>
    <nav class="meniu">
        <?php foreach ($meniu as $failas => $tekstas): ?>
            <a href="<?= e($failas) ?>"<?= $failas === $dabartinis ? ' class="aktyvus"' : '' ?>><?= e($tekstas) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>
</header>
<main class="turinys">
<?php foreach (paimti_pranesimus() as $p): ?>
    <div class="pranesimas <?= e($p['tipas']) ?>"><?= e($p['tekstas']) ?></div>
<?php endforeach; ?>
