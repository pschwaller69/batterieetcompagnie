<h1>Journal d'activité</h1>
<section class="card">
    <ul class="log">
    <?php foreach ($rows as $a): ?>
        <li class="<?= h($a['level']) ?>"><time><?= fmt_date($a['at']) ?></time> <?= h($a['message']) ?></li>
    <?php endforeach; ?>
    <?php if (!$rows): ?><li class="muted">Rien pour l'instant.</li><?php endif; ?>
    </ul>
</section>
