<?php
// Titulinis puslapis ir prisijungimas
require_once __DIR__ . '/inc/auth.php';

if (vartotojas()) {
    eiti('pradzia.php');
}

$klaidos = [];
$prisijungimo_vardas = '';

if (yra_post()) {
    tikrinti_csrf();
    $prisijungimo_vardas = mb_strtolower(post('prisijungimo_vardas'));
    $slaptazodis = $_POST['slaptazodis'] ?? '';

    if ($prisijungimo_vardas === '') {
        $klaidos['prisijungimo_vardas'] = 'Įveskite prisijungimo vardą.';
    }
    if (!is_string($slaptazodis) || $slaptazodis === '') {
        $klaidos['slaptazodis'] = 'Įveskite slaptažodį.';
    }

    if (!$klaidos) {
        $v = vienas('SELECT * FROM vartotojai WHERE prisijungimo_vardas = ?', [$prisijungimo_vardas]);
        if (!$v || !password_verify($slaptazodis, $v['slaptazodis'])) {
            $klaidos['bendra'] = 'Neteisingas prisijungimo vardas arba slaptažodis.';
        } elseif (!$v['aktyvus']) {
            $klaidos['bendra'] = 'Jūsų paskyra užblokuota. Kreipkitės į sistemos administratorių.';
        } else {
            session_regenerate_id(true);
            $_SESSION['vartotojo_id'] = (int)$v['id'];
            vykdyti('UPDATE vartotojai SET paskutinis_prisijungimas = NOW() WHERE id = ?', [(int)$v['id']]);
            pranesimas('Sveiki, ' . $v['vardas'] . '! Prisijungėte kaip: ' . roles_pavadinimas($v['role']) . '.');
            eiti(pradzios_puslapis($v['role']));
        }
    }
}

$GLOBALS['puslapio_pavadinimas'] = 'Prisijungimas';
require __DIR__ . '/inc/virsus.php';
?>
<section class="titulinis">
    <div class="titulinis-tekstas">
        <p class="virsutinis">KTU · T120B145 „Kompiuterių tinklai ir internetinės technologijos“ · IT projektas</p>
        <h1><?= e(SISTEMOS_PAVADINIMAS) ?></h1>
        <p class="autorius">Autorius: <strong><?= e(AUTORIUS) ?></strong></p>
        <p>Sistema leidžia mokyklai valdyti, kokio turinio interneto svetaines mokiniai gali atidaryti
           per mokyklos bevielį tinklą. Svetainės (URL) suskirstytos į kategorijas, o direktorius
           kiekvienai mokinių grupei – pradinukams, pagrindinių klasių mokiniams ir gimnazistams –
           nurodo, kurios kategorijos jiems uždraustos.</p>
        <ul class="savybes">
            <li><strong>Administratorius</strong> importuoja mokinių sąrašą ir valdo paskyras</li>
            <li><strong>Saugos įgaliotinis</strong> tvarko kategorijas ir URL sąrašus, stebi pažeidimus</li>
            <li><strong>Direktorius</strong> nustato, kurios kategorijos draudžiamos kiekvienai grupei</li>
            <li><strong>Mokinys</strong> imituoja bandymą atidaryti svetainę ir sužino, ar tai leidžiama</li>
        </ul>
    </div>
    <div class="kortele prisijungimas">
        <h2>Prisijungimas</h2>
        <?php if (isset($klaidos['bendra'])): ?>
            <div class="pranesimas klaida"><?= e($klaidos['bendra']) ?></div>
        <?php endif; ?>
        <form method="post" action="index.php" novalidate>
            <?= csrf_laukas() ?>
            <label for="pv">Prisijungimo vardas</label>
            <input id="pv" type="text" name="prisijungimo_vardas" maxlength="40" autofocus
                   value="<?= e($prisijungimo_vardas) ?>"<?= klaidos_klase($klaidos, 'prisijungimo_vardas') ?>>
            <?= lauko_klaida($klaidos, 'prisijungimo_vardas') ?>

            <label for="sl">Slaptažodis</label>
            <input id="sl" type="password" name="slaptazodis" maxlength="72"<?= klaidos_klase($klaidos, 'slaptazodis') ?>>
            <?= lauko_klaida($klaidos, 'slaptazodis') ?>

            <button type="submit" class="mygtukas pilnas">Prisijungti</button>
        </form>
        <p class="pastaba">Prisijungimo duomenis mokiniams išduoda mokyklos administratorius.</p>
    </div>
</section>
<?php require __DIR__ . '/inc/apacia.php'; ?>
