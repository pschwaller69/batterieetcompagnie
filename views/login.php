<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Connexion — <?= h(APP_NAME) ?></title><link rel="stylesheet" href="assets/app.css?v=1"></head>
<body class="center">
<form method="post" class="card narrow">
    <h1><span class="logo">B&amp;C</span> Mailing</h1>
    <?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>E-mail<input type="email" name="email" required autofocus></label>
    <label>Mot de passe<input type="password" name="password" required></label>
    <button class="btn primary">Se connecter</button>
</form>
</body></html>
