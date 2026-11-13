<link href="assets/quill/quill.snow.css" rel="stylesheet">

<h1><?= $id ? 'Modifier la campagne' : 'Nouvelle campagne' ?></h1>

<form method="post" id="campaignform" class="editor-layout">
    <?= csrf_field() ?>
    <input type="hidden" name="html" id="html-input" value="<?= h($c['html']) ?>">
    <input type="hidden" name="next" id="next" value="edit">

    <div class="card">
        <label>Nom interne<input name="name" required value="<?= h($c['name']) ?>" placeholder="ex. Concert de décembre — relance"></label>
        <label>Objet du mail<input name="subject" required value="<?= h($c['subject']) ?>" placeholder="ex. {{prenom}}, on vous attend samedi !"></label>
        <label>Texte d'aperçu <small class="muted">(affiché après l'objet dans la boîte de réception)</small><input name="preheader" value="<?= h($c['preheader']) ?>"></label>

        <div class="toolbar-help small muted">
            Variables : <code data-insert="{{prenom}}">{{prenom}}</code> <code data-insert="{{nom}}">{{nom}}</code> <code data-insert="{{email}}">{{email}}</code>
            — cliquez pour insérer. Le lien de désinscription est ajouté automatiquement en pied de mail.
        </div>
        <div id="editor"></div>
    </div>

    <aside class="card">
        <h2>Destinataires</h2>
        <label class="inline"><input type="radio" name="target" value="lists" <?= !$c['target_all'] ? 'checked' : '' ?>> Listes choisies</label>
        <label class="inline"><input type="radio" name="target" value="all" <?= $c['target_all'] ? 'checked' : '' ?>> Tous les contacts abonnés</label>
        <div class="listpick">
        <?php if (!$lists): ?><p class="muted small">Aucune liste. <a href="<?= url('lists') ?>">Créer une liste</a>.</p><?php endif; ?>
        <?php foreach ($lists as $l): ?>
            <label class="inline"><input type="checkbox" name="lists[]" value="<?= $l['id'] ?>" <?= in_array((int)$l['id'], array_map('intval', $mine), true) ? 'checked' : '' ?>> <?= h($l['name']) ?> <small class="muted">(<?= $l['n'] ?>)</small></label>
        <?php endforeach; ?>
        </div>

        <h2>Expéditeur</h2>
        <label>Nom affiché<input name="from_name" value="<?= h($c['from_name']) ?>"></label>
        <p class="small muted">Adresse d'envoi : <?= h(setting('from_email')) ?></p>
        <label>Répondre à<input type="email" name="reply_to" value="<?= h($c['reply_to']) ?>"></label>
        <label class="inline"><input type="checkbox" name="track_opens" value="1" <?= $c['track_opens'] ? 'checked' : '' ?>> Mesurer les ouvertures</label>

        <div class="actions col">
            <button class="btn" type="submit" onclick="document.getElementById('next').value='edit'">Enregistrer le brouillon</button>
            <button class="btn primary" type="submit" onclick="document.getElementById('next').value='view'">Enregistrer et passer à l'envoi →</button>
            <a class="btn" href="<?= url('campaigns') ?>">Retour</a>
        </div>
    </aside>
</form>

<script src="assets/quill/quill.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () { window.initCampaignEditor && window.initCampaignEditor(); });
</script>
