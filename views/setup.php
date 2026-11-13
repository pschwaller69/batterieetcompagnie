<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation — <?= h(APP_NAME) ?></title><link rel="stylesheet" href="assets/app.css?v=1"></head>
<body class="center">
<form method="post" class="card narrow">
    <h1>Bienvenue</h1>
    <p class="muted">Créez le premier compte administrateur de l'outil de mailing.</p>
    <?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Nom<input name="name" value="<?= h($_POST['name'] ?? '') ?>"></label>
    <label>E-mail<input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"></label>
    <label>Mot de passe (10 caractères min.)<input type="password" name="password" required minlength="10"></label>
    <button class="btn primary">Créer le compte</button>
</form>
</body></html>
