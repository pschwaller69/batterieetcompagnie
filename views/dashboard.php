<h1>Accueil</h1>

<?php if (!$smtpOk): ?>
<div class="flash warn">L'envoi n'est pas encore configuré. <a href="<?= url('settings') ?>">Renseigner le serveur SMTP</a>.</div>
<?php endif; ?>

<div class="tiles">
    <div class="tile"><b><?= (int)$counts['subscribed'] ?></b><span>contacts abonnés</span></div>
    <div class="tile"><b><?= (int)$counts['unsubscribed'] ?></b><span>désinscrits</span></div>
    <div class="tile"><b><?= (int)$counts['bounced'] ?></b><span>adresses en erreur</span></div>
    <div class="tile"><b><?= $lists ?></b><span>listes</span></div>
    <div class="tile"><b><?= $pending ?></b><span>mails en attente</span></div>
    <div class="tile"><b><?= $sentHour ?> / <?= (int)setting('hourly_limit') ?></b><span>envoyés sur 1 h</span></div>
</div>

<div class="grid2">
<section class="card">
    <div class="row-between"><h2>Dernières campagnes</h2><a class="btn primary" href="<?= url('campaign_edit') ?>">+ Nouvelle campagne</a></div>
    <?php if (!$recent): ?><p class="muted">Aucune campagne pour l'instant.</p><?php endif; ?>
    <table class="tbl">
    <?php foreach ($recent as $c): $s = campaign_stats((int)$c['id']); ?>
        <tr>
            <td><a href="<?= url('campaign', ['id' => $c['id']]) ?>"><?= h($c['name']) ?></a><br><small class="muted"><?= h($c['subject']) ?></small></td>
            <td><span class="badge <?= h($c['status']) ?>"><?= status_label($c['status']) ?></span></td>
            <td class="num"><?= $s['sent'] ?>/<?= $s['total'] ?></td>
        </tr>
    <?php endforeach; ?>
    </table>
</section>

<section class="card">
    <div class="row-between">
        <h2>Activité</h2>
        <?php if ($pending): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="process"><button class="btn">Envoyer un lot maintenant</button></form>
        <?php endif; ?>
    </div>
    <p class="muted small">Dernier passage du cron : <?= $lastCron ? fmt_date($lastCron) : '<strong>jamais</strong> — pensez à créer la tâche cron (voir README).' ?></p>
    <ul class="log">
    <?php foreach ($activity as $a): ?>
        <li class="<?= h($a['level']) ?>"><time><?= fmt_date($a['at']) ?></time> <?= h($a['message']) ?></li>
    <?php endforeach; ?>
    </ul>
    <a href="<?= url('activity') ?>" class="small">Tout le journal →</a>
</section>
</div>
