<div class="row-between">
    <h1>Contacts <small class="muted">(<?= $total ?>)</small></h1>
    <div>
        <a class="btn" href="<?= url('export', array_filter(['list' => get('list')])) ?>">Exporter CSV</a>
        <a class="btn" href="<?= url('import') ?>">Importer</a>
        <a class="btn primary" href="<?= url('contact') ?>">+ Contact</a>
    </div>
</div>

<form class="filters" method="get">
    <input type="hidden" name="p" value="contacts">
    <input type="search" name="q" placeholder="Nom, prénom ou e-mail…" value="<?= h(get('q')) ?>">
    <select name="list">
        <option value="">Toutes les listes</option>
        <?php foreach ($lists as $l): ?>
        <option value="<?= $l['id'] ?>" <?= (int)get('list') === (int)$l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">Tous statuts</option>
        <?php foreach (['subscribed', 'unsubscribed', 'bounced'] as $s): ?>
        <option value="<?= $s ?>" <?= get('status') === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn">Filtrer</button>
</form>

<form method="post" action="<?= h(url('contacts', array_filter(['q' => get('q'), 'list' => get('list'), 'status' => get('status')]))) ?>" id="bulkform">
<?= csrf_field() ?>
<div class="bulk">
    <span class="muted"><span id="selcount">0</span> sélectionné(s)</span>
    <select name="bulk" id="bulkaction">
        <option value="">Action…</option>
        <option value="add_list">Ajouter à la liste</option>
        <option value="remove_list">Retirer de la liste</option>
        <option value="unsubscribe">Désinscrire</option>
        <option value="resubscribe">Réabonner</option>
        <option value="delete">Supprimer définitivement</option>
    </select>
    <select name="list_id">
        <option value="">— liste —</option>
        <?php foreach ($lists as $l): ?><option value="<?= $l['id'] ?>"><?= h($l['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" data-confirm-bulk>Appliquer</button>
</div>

<table class="tbl">
<thead><tr>
    <th class="chk"><input type="checkbox" data-checkall></th>
    <th>Nom</th><th>E-mail</th><th>Listes</th><th>Statut</th>
</tr></thead>
<tbody>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">Aucun contact. <a href="<?= url('import') ?>">Importer un fichier</a></td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr>
    <td class="chk"><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>"></td>
    <td><a href="<?= url('contact', ['id' => $r['id']]) ?>"><?= h(trim($r['last_name'] . ' ' . $r['first_name'])) ?: '<em class="muted">(sans nom)</em>' ?></a></td>
    <td><?= h($r['email']) ?><?= ($r['source'] ?? '') === 'm365' ? ' <span class="badge" title="Importé depuis Microsoft 365">M365</span>' : '' ?></td>
    <td class="small"><?= h($r['list_names'] ?? '') ?></td>
    <td><span class="badge <?= h($r['status']) ?>"><?= status_label($r['status']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</form>

<?php if ($pages > 1): ?>
<div class="pager">
<?php for ($i = 1; $i <= $pages; $i++): ?>
    <a class="<?= $i === $page ? 'on' : '' ?>" href="<?= url('contacts', array_filter(['q' => get('q'), 'list' => get('list'), 'status' => get('status'), 'page' => $i])) ?>"><?= $i ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
