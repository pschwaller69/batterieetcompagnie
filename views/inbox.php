<div class="row-between">
    <h1>Réception <small class="muted">— boîte <?= h(setting('from_email')) ?></small></h1>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="process">
        <button class="btn" <?= inbox_configured() ? '' : 'disabled' ?>>Relever maintenant</button></form>
</div>
<?php if (!inbox_configured()): ?>
<div class="flash warn">La relève est désactivée ou le SMTP n'est pas configuré<?= is_admin() ? ' — <a href="' . url('settings') . '">Réglages</a>' : '' ?>.</div>
<?php endif; ?>
<p class="small muted">Dernière relève : <?= setting('inbox_last_run') ? fmt_date(setting('inbox_last_run')) : 'jamais' ?>
    <?= setting('inbox_last_result') !== '' ? ' — ' . h(setting('inbox_last_result')) : '' ?>
    · Vrais messages transférés à <strong><?= h(setting('inbox_forward_to') ?: '—') ?></strong></p>

<div class="tiles small">
<?php foreach (['bounce_hard', 'bounce_soft', 'complaint', 'unsubscribe', 'autoreply', 'forward'] as $k): ?>
    <a class="tile" style="text-decoration:none;color:inherit" href="<?= url('inbox', ['k' => $k]) ?>"><b><?= (int)($counts[$k] ?? 0) ?></b><span><?= h(inbox_kind_label($k)) ?> <small>(30 j)</small></span></a>
<?php endforeach; ?>
</div>

<section class="card">
    <div class="row-between">
        <h2>Messages traités</h2>
        <div class="tabs small">
            <a class="<?= $filter === '' ? 'on' : '' ?>" href="<?= url('inbox') ?>">Tous</a>
            <?php foreach ($kinds as $k): ?><a class="<?= $filter === $k ? 'on' : '' ?>" href="<?= url('inbox', ['k' => $k]) ?>"><?= h(inbox_kind_label($k)) ?></a><?php endforeach; ?>
        </div>
    </div>
    <table class="tbl small">
        <thead><tr><th>Reçu</th><th>De</th><th>Objet</th><th>Type</th><th>Action</th></tr></thead>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">Aucun message.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r):
            $cls = ['bounce_hard' => 'failed', 'bounce_soft' => 'paused', 'complaint' => 'failed', 'unsubscribe' => 'unsubscribed', 'autoreply' => 'skipped', 'forward' => 'sent'][$r['kind']] ?? ''; ?>
        <tr>
            <td class="muted" style="white-space:nowrap"><?= fmt_date($r['received_at']) ?></td>
            <td><?= h($r['from_addr']) ?></td>
            <td><?= h(mb_strimwidth($r['subject'], 0, 70, '…')) ?></td>
            <td><span class="badge <?= $cls ?>"><?= h(inbox_kind_label($r['kind'])) ?></span></td>
            <td class="muted"><?= h(mb_strimwidth($r['detail'], 0, 140, '…')) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</section>
