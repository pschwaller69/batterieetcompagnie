<?php $configured = m365_configured(); ?>
<h1>Contacts Microsoft 365</h1>
<p class="muted">Les contacts Outlook des boîtes de l'association sont lus directement via Microsoft Graph et importés dans l'outil.
   Un contact désinscrit ou en erreur le reste : la synchronisation ne touche jamais à son statut.</p>

<div class="grid2">
<form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <h2>Connexion (application Entra)</h2>
    <p class="small muted">Voir la section « Synchronisation Microsoft 365 » du README pour créer l'application.</p>
    <label>ID de l'annuaire (tenant)<input name="m365_tenant" value="<?= h(setting('m365_tenant')) ?>" placeholder="xxxxxxxx-xxxx-… ou batterieetcompagnie.fr"></label>
    <label>ID d'application (client)<input name="m365_client_id" value="<?= h(setting('m365_client_id')) ?>" placeholder="xxxxxxxx-xxxx-…"></label>
    <label>Secret client <small class="muted"><?= setting('m365_client_secret') !== '' ? '(enregistré — laisser vide pour le conserver)' : '(non renseigné)' ?></small>
        <input name="m365_client_secret" type="password" autocomplete="new-password"></label>

    <h2>Quoi synchroniser</h2>
    <label>Boîte(s) à lire <small class="muted">(séparées par des virgules)</small>
        <input name="m365_mailboxes" value="<?= h(setting('m365_mailboxes')) ?>"></label>
    <label>Dossiers de contacts <small class="muted">(vide = tous ; sinon noms séparés par des virgules)</small>
        <input name="m365_folders" value="<?= h(setting('m365_folders')) ?>" placeholder="ex. Adhérents, Bénévoles"></label>
    <label>Liste de destination<input name="m365_list_name" value="<?= h(setting('m365_list_name')) ?>"></label>
    <label class="inline"><input type="checkbox" name="m365_categories_as_lists" value="1" <?= setting('m365_categories_as_lists') === '1' ? 'checked' : '' ?>>
        Créer une liste par catégorie Outlook (ex. catégorie « Bénévoles » → liste « Outlook · Bénévoles »)</label>
    <label class="inline"><input type="checkbox" name="m365_remove_missing" value="1" <?= setting('m365_remove_missing') === '1' ? 'checked' : '' ?>>
        Retirer des listes synchronisées les contacts supprimés d'Outlook <small class="muted">(le contact reste dans l'outil)</small></label>
    <label>Synchronisation automatique toutes les … heures <small class="muted">(0 = manuelle uniquement ; nécessite le cron)</small>
        <input name="m365_auto_hours" type="number" min="0" value="<?= h(setting('m365_auto_hours')) ?>"></label>
    <div class="actions"><button class="btn primary">Enregistrer</button></div>
</form>

<div>
    <section class="card">
        <h2>Synchroniser</h2>
        <p class="small">Dernière synchro : <strong><?= setting('m365_last_sync') ? fmt_date(setting('m365_last_sync')) : 'jamais' ?></strong></p>
        <?php if (setting('m365_last_result') !== ''): ?><p class="small muted"><?= h(setting('m365_last_result')) ?></p><?php endif; ?>
        <div class="actions wrap">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test"><button class="btn" <?= $configured ? '' : 'disabled' ?>>Tester la connexion</button></form>
            <form method="post" data-confirm="Lancer la synchronisation maintenant ?"><?= csrf_field() ?><input type="hidden" name="action" value="sync"><button class="btn primary" <?= $configured ? '' : 'disabled' ?>>Synchroniser maintenant</button></form>
        </div>
        <?php if ($mainList): ?>
        <p class="small">Liste <a href="<?= url('contacts', ['list' => $mainList['id']]) ?>"><?= h(setting('m365_list_name')) ?></a> : <?= (int)$mainList['n'] ?> contact(s)</p>
        <?php endif; ?>
        <?php if ($catLists): ?>
        <p class="small">Listes issues des catégories :
            <?php foreach ($catLists as $i => $l): ?><?= $i ? ', ' : '' ?><a href="<?= url('contacts', ['list' => $l['id']]) ?>"><?= h($l['name']) ?></a> (<?= (int)$l['n'] ?>)<?php endforeach; ?>
        </p>
        <?php endif; ?>
    </section>

    <?php if ($probe !== null): ?>
    <section class="card">
        <h2>Résultat du test</h2>
        <?php foreach ($probe as $mb => $rows): ?>
            <h3 class="small"><?= h($mb) ?></h3>
            <?php if (isset($rows['error'])): ?>
                <div class="flash err"><?= h($rows['error']) ?></div>
            <?php else: ?>
                <table class="tbl small">
                <?php foreach ($rows as $r): ?>
                    <tr class="<?= $r['selected'] ? '' : 'muted' ?>">
                        <td><?= h($r['path']) ?></td>
                        <td class="num"><?= (int)$r['count'] ?> contact(s)</td>
                        <td><?= $r['selected'] ? '<span class="badge sent">synchronisé</span>' : '<span class="badge">ignoré</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</div>
</div>
