<?php $u = current_user(); $p = $GLOBALS['page'] ?? ''; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>
<header class="top">
    <a class="brand" href="<?= url('dashboard') ?>"><span class="logo">B&amp;C</span> Mailing</a>
    <nav>
        <?php foreach ([
            'dashboard' => 'Accueil', 'campaigns' => 'Campagnes', 'contacts' => 'Contacts',
            'lists' => 'Listes', 'import' => 'Importer', 'settings' => 'Réglages',
        ] as $k => $label):
            $active = $p === $k || ($k === 'campaigns' && in_array($p, ['campaign', 'campaign_edit'], true)) || ($k === 'contacts' && $p === 'contact'); ?>
            <a href="<?= url($k) ?>" class="<?= $active ? 'on' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($u): ?>
    <div class="me"><?= h($u['name'] ?: $u['email']) ?> · <a href="<?= url('logout') ?>">Déconnexion</a></div>
    <?php endif; ?>
</header>
<main>
<?php foreach (take_flashes() as [$type, $msg]): ?>
    <div class="flash <?= h($type) ?>"><?= h($msg) ?></div>
<?php endforeach; ?>
<?= $content ?>
</main>
<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="assets/app.js?v=1"></script>
</body>
</html>
