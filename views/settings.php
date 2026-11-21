<h1>Réglages</h1>

<form method="post" class="grid2">
<?= csrf_field() ?><input type="hidden" name="action" value="save">

<section class="card">
    <h2>Serveur d'envoi (SMTP o2switch)</h2>
    <p class="small muted">Dans cPanel → Comptes de messagerie → « Connect Devices » de l'adresse d'envoi : utilisez le serveur sortant indiqué, port 465 (SSL).</p>
    <label>Serveur SMTP<input name="smtp_host" value="<?= h(setting('smtp_host')) ?>" placeholder="xxxxx.o2switch.net"></label>
    <div class="cols2">
        <label>Port<input name="smtp_port" type="number" value="<?= h(setting('smtp_port')) ?>"></label>
        <label>Chiffrement
            <select name="smtp_secure">
                <?php foreach (['ssl' => 'SSL/TLS (465)', 'tls' => 'STARTTLS (587)', 'none' => 'Aucun'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= setting('smtp_secure') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <label>Identifiant<input name="smtp_user" value="<?= h(setting('smtp_user')) ?>" autocomplete="off"></label>
    <label>Mot de passe <small class="muted"><?= setting('smtp_pass') !== '' ? '(enregistré — laisser vide pour le conserver)' : '(non renseigné)' ?></small>
        <input name="smtp_pass" type="password" autocomplete="new-password"></label>

    <h2>Expéditeur</h2>
    <label>Adresse d'envoi (From)<input name="from_email" type="email" value="<?= h(setting('from_email')) ?>"></label>
    <label>Nom affiché par défaut<input name="from_name" value="<?= h(setting('from_name')) ?>"></label>
    <label>Adresse de réponse par défaut<input name="reply_to" type="email" value="<?= h(setting('reply_to')) ?>"></label>

    <h2>Boîte de réception (rebonds et réponses)</h2>
    <input type="hidden" name="inbox_enabled" value="0">
    <label class="inline"><input type="checkbox" name="inbox_enabled" value="1" <?= setting('inbox_enabled') === '1' ? 'checked' : '' ?>>
        Relever automatiquement la boîte d'envoi (à chaque passage du cron)</label>
    <label>Transférer les vrais messages à<input name="inbox_forward_to" type="email" value="<?= h(setting('inbox_forward_to')) ?>"></label>
    <input type="hidden" name="inbox_archive" value="0">
    <label class="inline"><input type="checkbox" name="inbox_archive" value="1" <?= setting('inbox_archive') === '1' ? 'checked' : '' ?>>
        Ranger les messages traités dans le dossier « <?= INBOX_ARCHIVE_FOLDER ?> »</label>
    <details class="small"><summary>Serveur IMAP (par défaut : celui du SMTP, port 993 SSL)</summary>
        <div class="cols3">
            <label>Serveur<input name="inbox_host" value="<?= h(setting('inbox_host')) ?>" placeholder="<?= h(setting('smtp_host') ?: 'comme le SMTP') ?>"></label>
            <label>Port<input name="inbox_port" type="number" value="<?= h(setting('inbox_port')) ?>"></label>
            <label>Chiffrement<select name="inbox_secure">
                <?php foreach (['ssl' => 'SSL/TLS (993)', 'tls' => 'STARTTLS (143)', 'none' => 'Aucun'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= setting('inbox_secure') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select></label>
        </div>
        <p class="muted">Identifiant et mot de passe : ceux du SMTP ci-dessus.</p>
    </details>

    <h2>Cadence d'envoi</h2>
    <div class="cols3">
        <label>Max / heure<input name="hourly_limit" type="number" min="1" value="<?= h(setting('hourly_limit')) ?>"></label>
        <label>Max / passage<input name="per_run" type="number" min="1" value="<?= h(setting('per_run')) ?>"></label>
        <label>Pause (ms)<input name="delay_ms" type="number" min="0" value="<?= h(setting('delay_ms')) ?>"></label>
    </div>
    <p class="small muted">Le plafond horaire est une fenêtre glissante qui inclut les tests. Faites confirmer par le support o2switch la limite d'envoi applicable à votre offre et restez en dessous.
       Avec un cron toutes les 5 minutes : 12 passages/h × « max / passage » doit dépasser le plafond horaire.</p>
</section>

<section class="card">
    <h2>Association</h2>
    <label>Nom<input name="org_name" value="<?= h(setting('org_name')) ?>"></label>
    <label>Adresse postale <small class="muted">(affichée en pied de mail)</small><textarea name="org_address" rows="2"><?= h(setting('org_address')) ?></textarea></label>
    <h2>Liens en pied de mail</h2>
    <label>Site web<input name="link_website" type="url" value="<?= h(setting('link_website')) ?>" placeholder="https://…"></label>
    <label>Facebook<input name="link_facebook" type="url" value="<?= h(setting('link_facebook')) ?>" placeholder="https://www.facebook.com/…"></label>
    <label>Instagram<input name="link_instagram" type="url" value="<?= h(setting('link_instagram')) ?>" placeholder="https://www.instagram.com/…"></label>
    <p class="small muted">Laisser un champ vide pour masquer le lien.</p>
    <label>Texte libre du pied de mail <small class="muted">(HTML autorisé : site web, réseaux…)</small><textarea name="footer_html" rows="3"><?= h(setting('footer_html')) ?></textarea></label>

    <h2>Apparence</h2>
    <div class="cols2">
        <label>Couleur principale<input name="brand_color" type="color" value="<?= h(setting('brand_color')) ?>"></label>
        <label>URL du logo<input name="logo_url" value="<?= h(setting('logo_url')) ?>" placeholder="https://…/logo.png"></label>
    </div>

    <h2>Adresse publique de l'outil</h2>
    <label>URL de base <small class="muted">(utilisée pour les liens de désinscription et les images)</small>
        <input name="base_url" value="<?= h(setting('base_url')) ?>" placeholder="https://mailing.batterieetcompagnie.fr"></label>

    <div class="actions"><button class="btn primary">Enregistrer les réglages</button></div>
</section>
</form>

<div class="grid2">
<form method="post" class="card">
    <h2>Tester la connexion SMTP</h2>
    <?= csrf_field() ?><input type="hidden" name="action" value="smtp_test">
    <div class="mini"><input type="email" name="to" value="<?= h($user['email']) ?>"><button class="btn">Envoyer un mail de test</button></div>
</form>

<section class="card">
    <h2>Tâche planifiée (cron)</h2>
    <p class="small">Dans cPanel → <strong>Tâches Cron</strong>, toutes les 5 minutes :</p>
    <pre class="code">/usr/local/bin/php <?= h($cronPath) ?> &gt;/dev/null 2&gt;&amp;1</pre>
    <p class="small muted">Dernier passage : <?= setting('last_cron_run') ? fmt_date(setting('last_cron_run')) : 'jamais' ?></p>
    <p class="small"><a href="<?= url('users') ?>">Gérer les utilisateurs</a> · <a href="<?= url('activity') ?>">Journal d'activité</a></p>
</section>
</div>

<?php $b = app_build(); $repo = 'https://github.com/pschwaller69/batterieetcompagnie'; ?>
<section class="card" id="version">
    <h2>Version installée</h2>
    <table class="tbl small">
        <tr><td>Version</td><td><strong>v<?= h(APP_VERSION) ?></strong> — <a href="<?= $repo ?>/blob/main/CHANGELOG.md" target="_blank" rel="noopener">voir les nouveautés</a></td></tr>
        <tr><td>Commit déployé</td><td><?= $b ? '<a href="' . $repo . '/commit/' . h($b['hash']) . '" target="_blank" rel="noopener"><code>' . h($b['short']) . '</code></a>' : '<span class="muted">inconnu (dossier .git absent)</span>' ?></td></tr>
        <?php if ($b && $b['date']): ?><tr><td>Dernière mise à jour</td><td><?= h($b['date']) ?></td></tr><?php endif; ?>
        <tr><td>Base de données</td><td>schéma n° <?= (int)db()->query('PRAGMA user_version')->fetchColumn() ?></td></tr>
        <tr><td>PHP</td><td><?= h(PHP_VERSION) ?><?= function_exists('curl_init') ? '' : ' — <span class="badge failed">extension curl absente</span>' ?></td></tr>
    </table>
    <p class="small muted">Pour vérifier que vous êtes à jour : le commit ci-dessus doit être le même que le dernier commit affiché sur
        <a href="<?= $repo ?>/commits/main" target="_blank" rel="noopener">GitHub</a>. Sinon : cPanel → Git Version Control → Gérer → « Update from Remote ».</p>
</section>
