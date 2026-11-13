<?php
/**
 * Copier ce fichier en config.php (à la racine du projet, HORS du dossier public/)
 * puis adapter les valeurs. config.php n'est jamais versionné (.gitignore).
 */
return [
    // Clé secrète aléatoire (64 caractères hex). Sert à chiffrer le mot de passe SMTP
    // en base et à signer les jetons. Générer avec :  php -r "echo bin2hex(random_bytes(32));"
    'app_secret' => 'A_REMPLACER',

    // Chemin de la base SQLite (doit rester hors du dossier public/)
    'db_path' => __DIR__ . '/data/mailing.sqlite',

    // Fuseau horaire
    'timezone' => 'Europe/Paris',

    // Clé autorisant le déclenchement de l'envoi par URL (public/cron.php?key=...)
    // Laisser vide pour n'autoriser que le cron en ligne de commande.
    'cron_url_key' => '',
];
