<?php $s = $stats; $isDraft = $c['status'] === 'draft'; ?>
<div class="row-between">
    <div>
        <h1><?= h($c['name']) ?> <span class="badge <?= h($c['status']) ?>"><?= status_label($c['status']) ?></span></h1>
        <p class="muted">Objet : <strong><?= h($c['subject']) ?></strong>
            · <?= $c['target_all'] ? 'Tous les abonnés' : ('Listes : ' . h(implode(', ', $listNames) ?: 'aucune')) ?></p>
    </div>
    <div class="actions">
        <?php if ($isDraft): ?><a class="btn" href="<?= url('campaign_edit', ['id' => $c['id']]) ?>">Modifier</a><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><button class="btn">Dupliquer</button></form>
        <?php if (!in_array($c['status'], ['sending', 'scheduled'], true)): ?>
        <form method="post" data-confirm="Supprimer définitivement cette campagne et ses statistiques ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn danger">Supprimer</button></form>
        <?php endif; ?>
    </div>
</div>

<div class="grid2 wide-left">
<section class="card preview">
    <iframe src="<?= url('preview', ['id' => $c['id']]) ?>" title="Aperçu"></iframe>
</section>

<div>
    <?php if ($isDraft): ?>
    <section class="card">
        <h2>1. Tester</h2>
        <form method="post" class="mini">
            <?= csrf_field() ?><input type="hidden" name="action" value="test">
            <input type="email" name="to" value="<?= h($testTo) ?>" required>
            <button class="btn">Envoyer un test</button>
        </form>
        <p class="small muted">Vérifiez le rendu sur ordinateur et téléphone, et que le mail n'arrive pas en spam (Gmail, Outlook, Orange…).</p>
    </section>

    <section class="card">
        <h2>2. Envoyer</h2>
        <p><strong><?= $recipients ?></strong> destinataire(s) abonné(s).</p>
        <?php if ($recipients > 0): ?>
        <p class="small muted">Plafond : <?= (int)setting('hourly_limit') ?> mails/heure → durée estimée ≈ <?= max(1, (int)ceil($recipients / max(1, (int)setting('hourly_limit')))) ?> h. Encore <?= $remaining ?> possible(s) dans l'heure.</p>
        <form method="post" data-confirm="Lancer l'envoi à <?= $recipients ?> destinataire(s) ?">
            <?= csrf_field() ?><input type="hidden" name="action" value="send">
            <button class="btn primary big">Envoyer maintenant</button>
        </form>
        <form method="post" class="mini" data-confirm="Programmer l'envoi ?">
            <?= csrf_field() ?><input type="hidden" name="action" value="schedule">
            <input type="datetime-local" name="scheduled_at" required min="<?= date('Y-m-d\TH:i') ?>">
            <button class="btn">Programmer</button>
        </form>
        <?php endif; ?>
    </section>

    <?php else: ?>
    <section class="card">
        <h2>Suivi</h2>
        <div class="tiles small">
            <div class="tile"><b><?= $s['sent'] ?></b><span>envoyés</span></div>
            <div class="tile"><b><?= $s['pending'] ?></b><span>en attente</span></div>
            <div class="tile"><b><?= $s['failed'] ?></b><span>échecs</span></div>
            <div class="tile"><b><?= $c['track_opens'] ? pct($s['opened'], $s['sent']) : '—' ?></b><span>ouvertures*</span></div>
        </div>
        <?php if ($s['total']): ?><div class="progress"><div style="width:<?= round(($s['sent'] + $s['failed'] + $s['skipped']) * 100 / $s['total']) ?>%"></div></div><?php endif; ?>
        <p class="small muted">
            <?php if ($c['status'] === 'scheduled'): ?>Programmée pour le <?= fmt_date($c['scheduled_at']) ?>.<?php endif; ?>
            Mise en file : <?= fmt_date($c['queued_at']) ?><?= $c['finished_at'] ? ' · terminée : ' . fmt_date($c['finished_at']) : '' ?>
        </p>
        <div class="actions wrap">
            <?php if ($c['status'] === 'sending'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="process"><button class="btn">Envoyer un lot maintenant</button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pause"><button class="btn">Pause</button></form>
            <?php endif; ?>
            <?php if ($c['status'] === 'paused'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resume"><button class="btn primary">Reprendre</button></form>
            <?php endif; ?>
            <?php if ($c['status'] === 'scheduled'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unschedule"><button class="btn">Annuler la programmation</button></form>
            <?php endif; ?>
            <?php if (in_array($c['status'], ['sending', 'paused'], true)): ?>
                <form method="post" data-confirm="Annuler les envois restants ?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn danger">Annuler l'envoi</button></form>
            <?php endif; ?>
            <?php if ($s['failed'] && in_array($c['status'], ['sent', 'sending', 'paused'], true)): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="retry"><button class="btn">Relancer les échecs</button></form>
            <?php endif; ?>
        </div>
        <p class="small muted">* Indicatif : de nombreux clients mail bloquent les images, les ouvertures réelles sont plus nombreuses.</p>
    </section>

    <section class="card">
        <div class="row-between">
            <h2>Destinataires</h2>
            <div class="tabs small">
                <?php foreach (['' => 'Tous', 'sent' => 'Envoyés', 'pending' => 'En attente', 'failed' => 'Échecs', 'opened' => 'Ouverts', 'skipped' => 'Ignorés'] as $k => $lab): ?>
                <a class="<?= $filter === $k ? 'on' : '' ?>" href="<?= url('campaign', array_filter(['id' => $c['id'], 'f' => $k])) ?>"><?= $lab ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="scroll tall">
        <table class="tbl small">
            <?php foreach ($messages as $m): ?>
            <tr>
                <td><?= $m['contact_id'] ? '<a href="' . url('contact', ['id' => $m['contact_id']]) . '">' . h($m['email']) . '</a>' : h($m['email']) ?></td>
                <td><span class="badge <?= h($m['status']) ?>"><?= status_label($m['status']) ?></span><?= $m['opened_at'] ? ' <span class="badge opened">ouvert</span>' : '' ?></td>
                <td class="muted"><?= $m['last_error'] ? h(mb_strimwidth($m['last_error'], 0, 90, '…')) : fmt_date($m['sent_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </section>
    <?php endif; ?>
</div>
</div>
