<h1>Utilisateurs</h1>
<div class="grid2">
<section class="card">
    <table class="tbl">
    <?php foreach ($users as $u): ?>
        <tr>
            <td><strong><?= h($u['name'] ?: '—') ?></strong><br><small class="muted"><?= h($u['email']) ?></small></td>
            <td>
                <form method="post" class="mini">
                    <?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <input type="password" name="password" placeholder="Nouveau mot de passe" minlength="10" required>
                    <button class="btn small">Changer</button>
                </form>
            </td>
            <td class="num">
                <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                <form method="post" data-confirm="Supprimer cet utilisateur ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn danger small">Supprimer</button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </table>
</section>
<form method="post" class="card">
    <h2>Ajouter un utilisateur</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label>Nom<input name="name"></label>
    <label>E-mail<input type="email" name="email" required></label>
    <label>Mot de passe (10 caractères min.)<input type="password" name="password" minlength="10" required></label>
    <button class="btn primary">Ajouter</button>
</form>
</div>
