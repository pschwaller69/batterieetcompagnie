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
