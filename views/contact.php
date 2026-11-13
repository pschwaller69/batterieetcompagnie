<h1><?= $c && !empty($c['id']) ? 'Modifier le contact' : 'Nouveau contact' ?></h1>
<?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>

<div class="grid2">
<form method="post" class="card">
    <?= csrf_field() ?>
    <div class="cols2">
        <label>Prénom<input name="first_name" value="<?= h($c['first_name'] ?? '') ?>"></label>
        <label>Nom<input name="last_name" value="<?= h($c['last_name'] ?? '') ?>"></label>
    </div>
    <label>E-mail<input type="email" name="email" required value="<?= h($c['email'] ?? '') ?>"></label>
    <label>Téléphone<input name="phone" value="<?= h($c['phone'] ?? '') ?>"></label>
    <label>Statut
        <select name="status">
            <?php foreach (['subscribed', 'unsubscribed', 'bounced'] as $s): ?>
            <option value="<?= $s ?>" <?= ($c['status'] ?? 'subscribed') === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <fieldset>
        <legend>Listes</legend>
        <?php if (!$lists): ?><p class="muted small">Aucune liste. <a href="<?= url('lists') ?>">En créer une</a>.</p><?php endif; ?>
        <?php foreach ($lists as $l): ?>
        <label class="inline"><input type="checkbox" name="lists[]" value="<?= $l['id'] ?>" <?= in_array((int)$l['id'], array_map('intval', $mine), true) ? 'checked' : '' ?>> <?= h($l['name']) ?></label>
        <?php endforeach; ?>
    </fieldset>
    <label>Notes<textarea name="notes" rows="3"><?= h($c['notes'] ?? '') ?></textarea></label>
    <div class="actions">
        <a class="btn" href="<?= url('contacts') ?>">Annuler</a>
        <button class="btn primary">Enregistrer</button>
    </div>
</form>

<?php if (!empty($c['id'])): ?>
<section class="card">
    <h2>Historique des envois</h2>
    <?php if (!$history): ?><p class="muted">Aucun envoi.</p><?php endif; ?>
    <table class="tbl">
    <?php foreach ($history as $m): ?>
        <tr>
            <td><a href="<?= url('campaign', ['id' => $m['campaign_id']]) ?>"><?= h($m['name']) ?></a></td>
            <td><span class="badge <?= h($m['status']) ?>"><?= status_label($m['status']) ?></span></td>
            <td class="small"><?= fmt_date($m['sent_at']) ?><?= $m['opened_at'] ? ' · ouvert' : '' ?></td>
        </tr>
    <?php endforeach; ?>
    </table>
    <p class="small muted">Inscrit le <?= fmt_date($c['created_at'] ?? null) ?><?= !empty($c['unsubscribed_at']) ? ' · désinscrit le ' . fmt_date($c['unsubscribed_at']) : '' ?></p>
</section>
<?php endif; ?>
</div>
