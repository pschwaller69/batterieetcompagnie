<h1>Mon compte</h1>
<div class="grid2">
<form method="post" class="card">
    <h2>Mes informations</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="name">
    <label>Nom affiché<input name="name" value="<?= h($me['name']) ?>"></label>
    <p class="small muted">Identifiant : <?= h($me['email']) ?> · Rôle : <?= role_label($me['role'] ?? 'admin') ?></p>
    <button class="btn">Enregistrer</button>
</form>
<form method="post" class="card">
    <h2>Changer mon mot de passe</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <label>Mot de passe actuel<input type="password" name="current_password" required autocomplete="current-password"></label>
    <label>Nouveau mot de passe (10 caractères min.)<input type="password" name="new_password" minlength="10" required autocomplete="new-password"></label>
    <label>Confirmer<input type="password" name="confirm_password" minlength="10" required autocomplete="new-password"></label>
    <button class="btn primary">Changer le mot de passe</button>
</form>
</div>
