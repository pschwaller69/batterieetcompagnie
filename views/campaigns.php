<div class="row-between">
    <h1>Campagnes</h1>
    <a class="btn primary" href="<?= url('campaign_edit') ?>">+ Nouvelle campagne</a>
</div>

<section class="card">
<table class="tbl">
    <thead><tr><th>Campagne</th><th>Statut</th><th class="num">Envoyés</th><th class="num">Ouverts</th><th class="num">Échecs</th><th>Date</th></tr></thead>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted">Aucune campagne.</td></tr><?php endif; ?>
    <?php foreach ($rows as $c): $s = $c['stats']; ?>
    <tr>
        <td><a href="<?= url($c['status'] === 'draft' ? 'campaign_edit' : 'campaign', ['id' => $c['id']]) ?>"><strong><?= h($c['name']) ?></strong></a><br><small class="muted"><?= h($c['subject']) ?></small></td>
        <td><span class="badge <?= h($c['status']) ?>"><?= status_label($c['status']) ?></span></td>
        <td class="num"><?= $s['sent'] ?> / <?= $s['total'] ?></td>
        <td class="num"><?= $c['track_opens'] ? pct($s['opened'], $s['sent']) : '—' ?></td>
        <td class="num"><?= $s['failed'] ?: '' ?></td>
        <td class="small"><?= fmt_date($c['finished_at'] ?? $c['scheduled_at'] ?? $c['queued_at'] ?? $c['updated_at']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</section>
