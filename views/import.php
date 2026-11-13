<h1>Importer des contacts</h1>

<?php if ($step === 1): ?>
<form method="post" enctype="multipart/form-data" class="card narrow-l">
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="1">
    <p>Fichier <strong>CSV</strong> (séparateur ; ou ,) ou <strong>Excel .xlsx</strong> — par exemple l'export des adhérents depuis Yapla.
       La première feuille est lue ; l'encodage (UTF-8 ou Windows) est détecté automatiquement.</p>
    <label>Fichier<input type="file" name="file" accept=".csv,.txt,.xlsx" required></label>
    <button class="btn primary">Continuer</button>
</form>

<?php else: $cols = $data['header']; ?>
<form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="step" value="2">
    <p><strong><?= h($data['name']) ?></strong> — <?= $data['count'] ?> ligne(s) lue(s).</p>

    <label class="inline"><input type="checkbox" name="has_header" value="1" checked> La première ligne contient les titres de colonnes</label>

    <h2>Correspondance des colonnes</h2>
    <div class="cols4">
    <?php foreach (['email' => 'E-mail *', 'first_name' => 'Prénom', 'last_name' => 'Nom', 'phone' => 'Téléphone'] as $k => $label): ?>
        <label><?= $label ?>
            <select name="map_<?= $k ?>" <?= $k === 'email' ? 'required' : '' ?>>
                <option value="">— ignorer —</option>
                <?php foreach ($cols as $i => $col): ?>
                <option value="<?= $i ?>" <?= $data['guess'][$k] === $i ? 'selected' : '' ?>><?= h($col !== '' ? $col : 'Colonne ' . ($i + 1)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endforeach; ?>
    </div>

    <h2>Aperçu</h2>
    <div class="scroll">
    <table class="tbl small">
        <thead><tr><?php foreach ($cols as $col): ?><th><?= h($col) ?></th><?php endforeach; ?></tr></thead>
        <?php foreach ($data['sample'] as $r): ?>
        <tr><?php foreach ($cols as $i => $_): ?><td><?= h($r[$i] ?? '') ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
    </table>
    </div>

    <h2>Ajouter aux listes</h2>
    <?php foreach ($lists as $l): ?>
        <label class="inline"><input type="checkbox" name="lists[]" value="<?= $l['id'] ?>"> <?= h($l['name']) ?></label>
    <?php endforeach; ?>
    <label>… ou créer une nouvelle liste<input name="new_list" placeholder="ex. Adhérents 2026-2027"></label>

    <label class="inline"><input type="checkbox" name="update" value="1" checked> Mettre à jour le nom/téléphone des contacts déjà connus</label>
    <p class="small muted">Les contacts désinscrits restent désinscrits, même s'ils figurent dans le fichier.</p>

    <div class="actions">
        <a class="btn" href="<?= url('import') ?>">Annuler</a>
        <button class="btn primary">Importer</button>
    </div>
</form>
<?php endif; ?>
