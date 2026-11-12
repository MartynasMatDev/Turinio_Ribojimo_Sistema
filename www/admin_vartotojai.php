<?php
// Administratorius: darbuotojų (administratorių, saugos įgaliotinių, direktorių) paskyrų valdymas
require_once __DIR__ . '/inc/auth.php';
$v = reikalauti_roles('admin');

$darbuotoju_roles = ['admin' => 'Administratorius', 'saugos' => 'Saugos įgaliotinis', 'direktorius' => 'Direktorius'];
$klaidos = [];
$redaguojamas = null;
$forma = ['prisijungimo_vardas' => '', 'vardas' => '', 'pavarde' => '', 'role' => '', 'aktyvus' => 1];

if (get('redaguoti') !== '') {
    $redaguojamas = vienas("SELECT * FROM vartotojai WHERE id = ? AND role <> 'mokinys'", [(int)get('redaguoti')]);
    if (!$redaguojamas) {
        pranesimas('Vartotojas nerastas.', 'klaida');
        eiti('admin_vartotojai.php');
    }
    $forma = $redaguojamas;
}

if (yra_post()) {
    tikrinti_csrf();
    $veiksmas = post('veiksmas');
    $id = (int)post('id');

    if ($veiksmas === 'salinti') {
        $d = vienas("SELECT * FROM vartotojai WHERE id = ? AND role <> 'mokinys'", [$id]);
        if (!$d) {
            pranesimas('Vartotojas nerastas.', 'klaida');
        } elseif ($id === (int)$v['id']) {
            pranesimas('Negalima pašalinti savo paties paskyros.', 'klaida');
        } elseif ($d['role'] === 'admin' && (int)reiksme("SELECT COUNT(*) FROM vartotojai WHERE role = 'admin' AND aktyvus = 1") <= 1) {
            pranesimas('Negalima pašalinti paskutinio aktyvaus administratoriaus.', 'klaida');
        } else {
            vykdyti('DELETE FROM vartotojai WHERE id = ?', [$id]);
            pranesimas("Vartotojas {$d['prisijungimo_vardas']} pašalintas.");
        }
        eiti('admin_vartotojai.php');
    }

    // Kūrimas / redagavimas
    $forma = [
        'prisijungimo_vardas' => mb_strtolower(post('prisijungimo_vardas')),
        'vardas'  => post('vardas'),
        'pavarde' => post('pavarde'),
        'role'    => post('role'),
        'aktyvus' => !empty($_POST['aktyvus']) ? 1 : 0,
    ];
    $slapt = (string)($_POST['slaptazodis'] ?? '');
    $slapt2 = (string)($_POST['slaptazodis2'] ?? '');

    if (!$id) {
        if (!tinkamas_prisijungimo_vardas($forma['prisijungimo_vardas'])) {
            $klaidos['prisijungimo_vardas'] = 'Prisijungimo vardas: 3–40 simbolių, mažosios lotyniškos raidės, skaičiai, . _ -';
        } elseif (reiksme('SELECT COUNT(*) FROM vartotojai WHERE prisijungimo_vardas = ?', [$forma['prisijungimo_vardas']])) {
            $klaidos['prisijungimo_vardas'] = 'Toks prisijungimo vardas jau užimtas.';
        }
    }
    if (!tinkamas_vardas($forma['vardas'])) {
        $klaidos['vardas'] = 'Vardas turi būti 2–50 raidžių.';
    }
    if (!tinkamas_vardas($forma['pavarde'])) {
        $klaidos['pavarde'] = 'Pavardė turi būti 2–50 raidžių.';
    }
    if (!isset($darbuotoju_roles[$forma['role']])) {
        $klaidos['role'] = 'Pasirinkite rolę.';
    }
    if (!$id || $slapt !== '') {
        if ($k = slaptazodzio_klaida($slapt)) {
            $klaidos['slaptazodis'] = $k;
        } elseif ($slapt !== $slapt2) {
            $klaidos['slaptazodis2'] = 'Slaptažodžiai nesutampa.';
        }
    }
    if ($id === (int)$v['id']) {
        if ($forma['role'] !== 'admin') {
            $klaidos['role'] = 'Negalima pakeisti savo paties rolės.';
        }
        if (!$forma['aktyvus']) {
            $klaidos['aktyvus'] = 'Negalima užblokuoti savo paties paskyros.';
        }
    }

    if (!$klaidos) {
        if ($id) {
            vykdyti("UPDATE vartotojai SET vardas = ?, pavarde = ?, role = ?, aktyvus = ? WHERE id = ? AND role <> 'mokinys'",
                [$forma['vardas'], $forma['pavarde'], $forma['role'], $forma['aktyvus'], $id]);
            if ($slapt !== '') {
                vykdyti('UPDATE vartotojai SET slaptazodis = ? WHERE id = ?', [password_hash($slapt, PASSWORD_DEFAULT), $id]);
            }
            pranesimas("Vartotojo {$forma['vardas']} {$forma['pavarde']} duomenys išsaugoti.");
        } else {
            vykdyti('INSERT INTO vartotojai (prisijungimo_vardas, slaptazodis, vardas, pavarde, role, aktyvus) VALUES (?, ?, ?, ?, ?, ?)',
                [$forma['prisijungimo_vardas'], password_hash($slapt, PASSWORD_DEFAULT), $forma['vardas'], $forma['pavarde'], $forma['role'], $forma['aktyvus']]);
            pranesimas("Sukurta paskyra {$forma['prisijungimo_vardas']} (" . $darbuotoju_roles[$forma['role']] . ').');
        }
        eiti('admin_vartotojai.php');
    }
    if ($id) {
        $redaguojamas = vienas('SELECT * FROM vartotojai WHERE id = ?', [$id]);
        $forma['prisijungimo_vardas'] = $redaguojamas['prisijungimo_vardas'];
    }
}

$darbuotojai = visi("SELECT * FROM vartotojai WHERE role <> 'mokinys' ORDER BY FIELD(role, 'admin', 'direktorius', 'saugos'), pavarde");

$GLOBALS['puslapio_pavadinimas'] = 'Darbuotojai';
require __DIR__ . '/inc/virsus.php';
?>
<div class="dvi-kolonos platesne-kaire">
    <div class="kortele">
        <h1>Darbuotojų paskyros</h1>
        <table class="lentele">
            <tr><th>Vardas, pavardė</th><th>Prisijungimo vardas</th><th>Rolė</th><th>Paskutinį kartą prisijungė</th><th>Būsena</th><th></th></tr>
            <?php foreach ($darbuotojai as $d): ?>
            <tr<?= $d['aktyvus'] ? '' : ' class="neaktyvus"' ?>>
                <td><strong><?= e($d['vardas'] . ' ' . $d['pavarde']) ?></strong></td>
                <td><?= e($d['prisijungimo_vardas']) ?></td>
                <td><span class="zenklelis role-<?= e($d['role']) ?>"><?= e(roles_pavadinimas($d['role'])) ?></span></td>
                <td><?= e(data_laikas($d['paskutinis_prisijungimas'])) ?></td>
                <td><?= $d['aktyvus'] ? '<span class="zyma zalia">Aktyvus</span>' : '<span class="zyma pilka">Užblokuotas</span>' ?></td>
                <td class="veiksmai">
                    <a class="mygtukas mazas antrinis" href="?redaguoti=<?= (int)$d['id'] ?>">Redaguoti</a>
                    <?php if ((int)$d['id'] !== (int)$v['id']): ?>
                    <form method="post" onsubmit="return confirm('Pašalinti vartotoją?');">
                        <?= csrf_laukas() ?>
                        <input type="hidden" name="veiksmas" value="salinti">
                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                        <button class="mygtukas mazas pavojingas" type="submit">Šalinti</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <div class="kortele">
        <h2><?= $redaguojamas ? 'Redaguoti paskyrą' : 'Nauja darbuotojo paskyra' ?></h2>
        <form method="post" novalidate>
            <?= csrf_laukas() ?>
            <input type="hidden" name="id" value="<?= $redaguojamas ? (int)$redaguojamas['id'] : 0 ?>">
            <label>Prisijungimo vardas *</label>
            <input type="text" name="prisijungimo_vardas" maxlength="40" value="<?= e($forma['prisijungimo_vardas']) ?>"<?= $redaguojamas ? ' disabled' : '' ?><?= klaidos_klase($klaidos, 'prisijungimo_vardas') ?>>
            <?= lauko_klaida($klaidos, 'prisijungimo_vardas') ?>
            <label>Vardas *</label>
            <input type="text" name="vardas" maxlength="50" value="<?= e($forma['vardas']) ?>"<?= klaidos_klase($klaidos, 'vardas') ?>><?= lauko_klaida($klaidos, 'vardas') ?>
            <label>Pavardė *</label>
            <input type="text" name="pavarde" maxlength="50" value="<?= e($forma['pavarde']) ?>"<?= klaidos_klase($klaidos, 'pavarde') ?>><?= lauko_klaida($klaidos, 'pavarde') ?>
            <label>Rolė *</label>
            <select name="role"<?= klaidos_klase($klaidos, 'role') ?>><option value="">– pasirinkite –</option>
                <?php foreach ($darbuotoju_roles as $r => $pav): ?><option value="<?= $r ?>"<?= $forma['role'] === $r ? ' selected' : '' ?>><?= e($pav) ?></option><?php endforeach; ?>
            </select><?= lauko_klaida($klaidos, 'role') ?>
            <label><?= $redaguojamas ? 'Naujas slaptažodis (palikite tuščią, jei nekeičiate)' : 'Slaptažodis *' ?></label>
            <input type="password" name="slaptazodis" maxlength="72"<?= klaidos_klase($klaidos, 'slaptazodis') ?>><?= lauko_klaida($klaidos, 'slaptazodis') ?>
            <label>Pakartokite slaptažodį</label>
            <input type="password" name="slaptazodis2" maxlength="72"<?= klaidos_klase($klaidos, 'slaptazodis2') ?>><?= lauko_klaida($klaidos, 'slaptazodis2') ?>
            <label class="zymimasis"><input type="checkbox" name="aktyvus" value="1"<?= $forma['aktyvus'] ? ' checked' : '' ?>> Paskyra aktyvi</label>
            <?= lauko_klaida($klaidos, 'aktyvus') ?>
            <button class="mygtukas" type="submit"><?= $redaguojamas ? 'Išsaugoti' : 'Sukurti paskyrą' ?></button>
            <?php if ($redaguojamas): ?><a class="mygtukas antrinis" href="admin_vartotojai.php">Atšaukti</a><?php endif; ?>
        </form>
    </div>
</div>
<?php require __DIR__ . '/inc/apacia.php'; ?>
