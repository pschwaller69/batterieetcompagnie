<?php
declare(strict_types=1);

/**
 * Version de l'application. À incrémenter à chaque livraison (voir CHANGELOG.md).
 */
const APP_VERSION = '1.4.0';

/**
 * Commit Git réellement déployé, lu directement dans .git (déploiement via cPanel « Git Version Control »).
 * @return array{hash:string, short:string, date:string}|null
 */
function app_build(): ?array
{
    static $build = false;
    if ($build !== false) {
        return $build;
    }
    $build = null;
    $git = APP_ROOT . '/.git';
    if (!is_dir($git) || !is_file("$git/HEAD")) {
        return null;
    }
    $head = trim((string)@file_get_contents("$git/HEAD"));
    $hash = '';
    if (str_starts_with($head, 'ref: ')) {
        $ref = substr($head, 5);
        if (is_file("$git/$ref")) {
            $hash = trim((string)@file_get_contents("$git/$ref"));
        } elseif (is_file("$git/packed-refs")) {
            foreach (file("$git/packed-refs", FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (str_ends_with($line, ' ' . $ref)) {
                    $hash = strtok($line, ' ');
                    break;
                }
            }
        }
    } else {
        $hash = $head; // HEAD détachée
    }
    if (!preg_match('/^[0-9a-f]{40}$/', $hash)) {
        return null;
    }
    // Date du dernier « pull » = date de modification de la référence
    $date = '';
    foreach (["$git/FETCH_HEAD", "$git/ORIG_HEAD", "$git/HEAD"] as $f) {
        if (is_file($f)) {
            $date = date('d/m/Y H:i', (int)filemtime($f));
            break;
        }
    }
    $build = ['hash' => $hash, 'short' => substr($hash, 0, 7), 'date' => $date];
    return $build;
}

/** Libellé court affiché dans l'interface, ex. « v1.2.0 · 7d25fdd ». */
function app_version_label(): string
{
    $b = app_build();
    return 'v' . APP_VERSION . ($b ? ' · ' . $b['short'] : '');
}
