<?php
declare(strict_types=1);

/**
 * Lit un fichier CSV ou XLSX et renvoie un tableau de lignes (tableaux de chaînes).
 */
function read_table_file(string $path, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        return read_xlsx($path);
    }
    if (in_array($ext, ['csv', 'txt', 'tsv'], true)) {
        return read_csv_auto($path);
    }
    throw new RuntimeException('Format non pris en charge (CSV ou XLSX attendu).');
}

function read_csv_auto(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Fichier illisible.');
    }
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // BOM UTF-8
    if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
    } elseif (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'); // export Excel français
    }
    $firstLine = strtok($raw, "\n") ?: '';
    $counts = [];
    foreach ([';', ',', "\t", '|'] as $d) {
        $counts[$d] = substr_count($firstLine, $d);
    }
    arsort($counts);
    $delim = array_key_first($counts);

    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    $rows = [];
    while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
        if ($r === [null] || (count($r) === 1 && trim((string)$r[0]) === '')) {
            continue;
        }
        $rows[] = array_map(fn($v) => trim((string)$v), $r);
    }
    fclose($fh);
    return $rows;
}

function read_xlsx(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException("L'extension PHP zip est absente : enregistrez le fichier en CSV.");
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Fichier XLSX illisible.');
    }
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($xml);
        foreach ($sx->si as $si) {
            if (isset($si->t)) {
                $shared[] = (string)$si->t;
            } else {
                $s = '';
                foreach ($si->r as $r) {
                    $s .= (string)$r->t;
                }
                $shared[] = $s;
            }
        }
    }
    // Première feuille
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if (($wb = $zip->getFromName('xl/workbook.xml')) !== false && ($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
        $wbx = simplexml_load_string($wb);
        $wbx->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheets = $wbx->xpath('//m:sheets/m:sheet');
        if ($sheets) {
            $rid = (string)$sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $rx = simplexml_load_string($rels);
            foreach ($rx->Relationship as $rel) {
                if ((string)$rel['Id'] === $rid) {
                    $t = ltrim((string)$rel['Target'], '/');
                    $sheetPath = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
                }
            }
        }
    }
    $xml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($xml === false) {
        throw new RuntimeException('Feuille introuvable dans le fichier XLSX.');
    }
    $sx = simplexml_load_string($xml);
    $rows = [];
    foreach ($sx->sheetData->row as $row) {
        $line = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $col = col_index(preg_replace('/\d+/', '', $ref));
            $type = (string)$c['t'];
            if ($type === 's') {
                $v = $shared[(int)$c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $v = (string)$c->is->t;
            } else {
                $v = (string)$c->v;
            }
            $line[$col] = trim($v);
        }
        if (!$line) {
            continue;
        }
        $max = max(array_keys($line));
        $full = [];
        for ($i = 0; $i <= $max; $i++) {
            $full[] = $line[$i] ?? '';
        }
        if (implode('', $full) !== '') {
            $rows[] = $full;
        }
    }
    return $rows;
}

function col_index(string $letters): int
{
    $n = 0;
    foreach (str_split(strtoupper($letters)) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return $n - 1;
}

/**
 * Devine quelle colonne correspond à quel champ d'après l'en-tête.
 */
function guess_mapping(array $header): array
{
    $map = ['email' => null, 'first_name' => null, 'last_name' => null, 'phone' => null];
    foreach ($header as $i => $label) {
        $l = mb_strtolower(trim($label));
        $l = strtr($l, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ô' => 'o', 'î' => 'i', '-' => ' ', '_' => ' ', '.' => ' ']);
        if ($map['email'] === null && preg_match('/(e ?mail|courriel|adresse electronique)/', $l)) {
            $map['email'] = $i;
        } elseif ($map['first_name'] === null && preg_match('/(prenom|first ?name|given)/', $l)) {
            $map['first_name'] = $i;
        } elseif ($map['last_name'] === null && preg_match('/^(nom|nom de famille|last ?name|surname|family)/', $l)) {
            $map['last_name'] = $i;
        } elseif ($map['phone'] === null && preg_match('/(tel|phone|portable|mobile)/', $l)) {
            $map['phone'] = $i;
        }
    }
    return $map;
}

/**
 * Importe des lignes selon un mapping.
 * @return array{created:int, updated:int, invalid:int, duplicates:int, skipped_unsub:int}
 */
function import_rows(array $rows, array $map, array $listIds, bool $updateExisting): array
{
    $stats = ['created' => 0, 'updated' => 0, 'invalid' => 0, 'duplicates' => 0, 'skipped_unsub' => 0];
    $seen = [];
    $pdo = db();
    $pdo->beginTransaction();
    $link = $pdo->prepare('INSERT OR IGNORE INTO contact_list (contact_id, list_id) VALUES (?, ?)');
    foreach ($rows as $r) {
        $email = normalize_email((string)($r[$map['email']] ?? ''));
        // Certaines exports mettent plusieurs adresses dans la même cellule
        if (preg_match('/[;,\s]/', $email)) {
            $email = normalize_email(preg_split('/[;,\s]+/', $email)[0]);
        }
        if (!valid_email($email)) {
            $stats['invalid']++;
            continue;
        }
        if (isset($seen[$email])) {
            $stats['duplicates']++;
            continue;
        }
        $seen[$email] = true;
        $fn = $map['first_name'] !== null ? (string)($r[$map['first_name']] ?? '') : '';
        $ln = $map['last_name'] !== null ? (string)($r[$map['last_name']] ?? '') : '';
        $ph = $map['phone'] !== null ? (string)($r[$map['phone']] ?? '') : '';

        $existing = q_one('SELECT id, status FROM contacts WHERE email = ?', [$email]);
        if ($existing) {
            $id = (int)$existing['id'];
            if ($existing['status'] === 'unsubscribed') {
                $stats['skipped_unsub']++; // on respecte la désinscription
            }
            if ($updateExisting) {
                q("UPDATE contacts SET
                    first_name = CASE WHEN ? <> '' THEN ? ELSE first_name END,
                    last_name  = CASE WHEN ? <> '' THEN ? ELSE last_name END,
                    phone      = CASE WHEN ? <> '' THEN ? ELSE phone END,
                    updated_at = ? WHERE id = ?", [$fn, $fn, $ln, $ln, $ph, $ph, now(), $id]);
                $stats['updated']++;
            }
        } else {
            q('INSERT INTO contacts (email, first_name, last_name, phone, token) VALUES (?, ?, ?, ?, ?)',
                [$email, $fn, $ln, $ph, random_token(18)]);
            $id = (int)$pdo->lastInsertId();
            $stats['created']++;
        }
        foreach ($listIds as $lid) {
            $link->execute([$id, (int)$lid]);
        }
    }
    $pdo->commit();
    return $stats;
}
