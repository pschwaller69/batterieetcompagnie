<h1>Listes</h1>
<p class="muted">Les listes regroupent les contacts (adhérents, bénévoles, public, partenaires…). Un contact peut appartenir à plusieurs listes ; à l'envoi, il ne reçoit qu'un seul exemplaire.</p>

<div class="grid2">
<section class="card">
    <table class="tbl">
        <thead><tr><th>Liste</th><th class="num">Abonnés</th><th class="num">Total</th><th></th></tr></thead>
        <?php if (!$lists): ?><tr><td colspan="4" class="muted">Aucune liste.</td></tr><?php endif; ?>
        <?php foreach ($lists as $l): ?>
        <tr>
            <td>
                <details>
                    <summary><strong><?= h($l['name']) ?></strong><?= $l['description'] !== '' ? '<br><small class="muted">' . h($l['description']) . '</small>' : '' ?></summary>
                    <form method="post" class="mini">
                        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $l['id'] ?>">
                        <input name="name" value="<?= h($l['name']) ?>" required>
                        <input name="description" value="<?= h($l['description']) ?>" placeholder="Description">
                        <button class="btn">Renommer</button>
                    </form>
                </details>
            </td>
            <td class="num"><a href="<?= url('contacts', ['list' => $l['id'], 'status' => 'subscribed']) ?>"><?= $l['n_sub'] ?></a></td>
            <td class="num"><a href="<?= url('contacts', ['list' => $l['id']]) ?>"><?= $l['n'] ?></a></td>
            <td class="num">
                <form method="post" data-confirm="Supprimer la liste « <?= h($l['name']) ?> » ? Les contacts sont conservés.">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $l['id'] ?>">
                    <button class="btn danger small">Supprimer</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</section>

<form method="post" class="card">
    <h2>Nouvelle liste</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label>Nom<input name="name" required placeholder="ex. Adhérents 2026-2027"></label>
    <label>Description<input name="description"></label>
    <button class="btn primary">Créer</button>
</form>
</div>
