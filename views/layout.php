<?php $u = current_user(); $p = $GLOBALS['page'] ?? ''; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= h(APP_NAME) ?></title>
<?php require APP_ROOT . '/views/pwa_head.php'; ?>
<link rel="stylesheet" href="assets/app.css?v=<?= h(APP_VERSION) ?>">
</head>
<body>
<header class="top">
    <a class="brand" href="<?= url('dashboard') ?>"><span class="logo">B&amp;C</span> Mailing</a>
    <nav>
        <?php $nav = ['dashboard' => 'Accueil', 'campaigns' => 'Campagnes', 'contacts' => 'Contacts', 'lists' => 'Listes', 'import' => 'Importer'];
        if ($u && is_admin($u)) {
            $nav += ['m365' => 'Microsoft 365', 'settings' => 'Réglages'];
        }
        foreach ($nav as $k => $label):
            $active = $p === $k || ($k === 'campaigns' && in_array($p, ['campaign', 'campaign_edit'], true)) || ($k === 'contacts' && $p === 'contact'); ?>
            <a href="<?= url($k) ?>" class="<?= $active ? 'on' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <button type="button" id="install-btn" class="btn small primary" hidden>Installer l'application</button>
    <?php if ($u): ?>
    <div class="me"><a href="<?= url('account') ?>" title="Mon compte"><?= h($u['name'] ?: $u['email']) ?></a><?= is_admin($u) ? '' : ' <span class="badge">Rédacteur</span>' ?> · <a href="<?= url('logout') ?>">Déconnexion</a></div>
    <?php endif; ?>
</header>
<main>
<?php foreach (take_flashes() as [$type, $msg]): ?>
    <div class="flash <?= h($type) ?>"><?= h($msg) ?></div>
<?php endforeach; ?>
<?= $content ?>
</main>
<footer class="foot">
    <?= h(APP_NAME) ?> · <a href="<?= url('settings') ?>#version" title="Détails de la version"><?= h(app_version_label()) ?></a>
</footer>
<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="assets/app.js?v=<?= h(APP_VERSION) ?>"></script>
</body>
</html>
