<h1>Utilisateurs</h1>
<p class="muted">
    <strong>Administrateur</strong> : accès complet.
    <strong>Rédacteur</strong> : contacts, listes, imports et campagnes ; pas d'accès aux Réglages, à Microsoft 365 ni aux Utilisateurs.
</p>
<div class="grid2">
<section class="card">
    <table class="tbl">
    <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?>
        <tr>
            <td><strong><?= h($u['name'] ?: '—') ?></strong><?= $self ? ' <small class="muted">(vous)</small>' : '' ?><br><small class="muted"><?= h($u['email']) ?></small></td>
            <td>
                <?php if ($self): ?>
                    <span class="badge"><?= role_label($u['role']) ?></span>
                <?php else: ?>
                <form method="post" class="mini">
                    <?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <select name="role" onchange="this.form.submit()">
                        <option value="editor" <?= $u['role'] === 'editor' ? 'selected' : '' ?>>Rédacteur</option>
                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                    </select>
                </form>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($self): ?>
                    <a class="small" href="<?= url('account') ?>">Changer mon mot de passe</a>
                <?php else: ?>
                <details>
                    <summary class="small">Mot de passe…</summary>
                    <form method="post" class="mini">
                        <?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                        <input type="password" name="password" placeholder="Nouveau mot de passe" minlength="10" required autocomplete="new-password">
                        <button class="btn small">Changer</button>
                    </form>
                </details>
                <?php endif; ?>
            </td>
            <td class="num">
                <?php if (!$self): ?>
                <form method="post" data-confirm="Supprimer le compte de <?= h($u['email']) ?> ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn danger small">Supprimer</button></form>
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
    <label>E-mail (identifiant de connexion)<input type="email" name="email" required></label>
    <label>Rôle
        <select name="role">
            <option value="editor" selected>Rédacteur — campagnes et contacts</option>
            <option value="admin">Administrateur — accès complet</option>
        </select>
    </label>
    <label>Mot de passe provisoire (10 caractères min.)<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
    <p class="small muted">La personne pourra le changer elle-même dans « Mon compte » (clic sur son nom en haut à droite).</p>
    <button class="btn primary">Ajouter</button>
</form>
</div>
