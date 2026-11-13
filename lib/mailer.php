<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Remplace les variables {{prenom}}, {{nom}}, {{email}}, {{desinscription}}.
 */
function personalize(string $text, array $vars, bool $html): string
{
    return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($vars, $html) {
        $k = strtolower($m[1]);
        if (!array_key_exists($k, $vars)) {
            return $m[0];
        }
        return $html ? h($vars[$k]) : (string)$vars[$k];
    }, $text);
}

/**
 * Ajoute des styles en ligne au HTML produit par l'éditeur (les clients mail ignorent les <style>).
 */
function inline_content_styles(string $html, string $brand): string
{
    $html = preg_replace('/<img(?![^>]*style=)/i', '<img style="max-width:100%;height:auto;display:block;margin:8px 0;"', $html);
    $html = preg_replace('/<a(?![^>]*style=)/i', '<a style="color:' . h($brand) . ';"', $html);
    $base = [
        'p' => 'margin:0 0 12px 0;',
        'h1' => 'font-size:24px;margin:0 0 14px 0;',
        'h2' => 'font-size:20px;margin:0 0 12px 0;',
        'h3' => 'font-size:17px;margin:0 0 10px 0;',
        'ul' => 'margin:0 0 12px 0;padding-left:24px;',
        'ol' => 'margin:0 0 12px 0;padding-left:24px;',
    ];
    foreach ($base as $tag => $css) {
        // balise déjà stylée (ex. alignement) : on préfixe nos styles
        $html = preg_replace('/<' . $tag . '(\s[^>]*?)style="/i', '<' . $tag . '$1style="' . $css, $html);
        // balise sans style
        $html = preg_replace('/<' . $tag . '(?=[\s>])(?![^>]*style=)/i', '<' . $tag . ' style="' . $css . '"', $html);
    }
    $html = preg_replace('/<blockquote(?![^>]*style=)/i', '<blockquote style="border-left:4px solid #ddd;margin:0 0 12px 0;padding:4px 12px;color:#555;"', $html);
    // Quill : paragraphes vides
    $html = preg_replace('#<p style="margin:0 0 12px 0;">\s*(<br\s*/?>)?\s*</p>#i', '<p style="margin:0 0 12px 0;">&nbsp;</p>', $html);
    return $html;
}

function html_to_text(string $html): string
{
    $t = preg_replace('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $html);
    $t = preg_replace('/<(br|\/p|\/h[1-6]|\/li|\/div|\/tr)\s*\/?>/i', "\n", $t);
    $t = preg_replace('/<li[^>]*>/i', '- ', $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/", ' ', $t);
    $t = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $t);
    return trim($t);
}

/**
 * Construit le mail final pour un destinataire.
 * @return array{subject:string, html:string, text:string, unsubscribe_url:string}
 */
function build_email(array $campaign, array $contact, string $messageToken, bool $isTest = false): array
{
    $base = base_url();
    $unsubUrl = $base . '/u.php?t=' . rawurlencode($contact['token'] ?? 'test');
    $vars = [
        'prenom' => $contact['first_name'] ?? '',
        'nom' => $contact['last_name'] ?? '',
        'email' => $contact['email'] ?? '',
        'desinscription' => $unsubUrl,
    ];
    $brand = setting('brand_color') ?: '#b3261e';
    $subject = personalize($campaign['subject'], $vars, false);
    if ($isTest) {
        $subject = '[TEST] ' . $subject;
    }
    $content = inline_content_styles(personalize($campaign['html'], $vars, true), $brand);

    $org = setting('org_name');
    $logo = setting('logo_url');
    $header = $logo !== ''
        ? '<img src="' . h($logo) . '" alt="' . h($org) . '" style="max-width:220px;max-height:90px;height:auto;display:inline-block;">'
        : '<span style="font-size:22px;font-weight:bold;color:#ffffff;">' . h($org) . '</span>';
    $footerExtra = setting('footer_html');
    $address = setting('org_address');
    $pixel = '';
    if (!$isTest && (int)($campaign['track_opens'] ?? 0) === 1 && $base !== '') {
        $pixel = '<img src="' . h($base . '/t.php?m=' . rawurlencode($messageToken)) . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;">';
    }
    $pre = trim((string)($campaign['preheader'] ?? ''));
    $preHtml = $pre !== ''
        ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . h(personalize($pre, $vars, false)) . str_repeat('&#8199;&#65279;&#847; ', 30) . '</div>'
        : '';

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . h($subject) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f2f2f2;">' . $preHtml
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f2f2;"><tr><td align="center" style="padding:20px 10px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:6px;overflow:hidden;">'
        . '<tr><td align="center" style="background:' . h($brand) . ';padding:18px 24px;font-family:Arial,Helvetica,sans-serif;">' . $header . '</td></tr>'
        . '<tr><td style="padding:24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#222222;">' . $content . '</td></tr>'
        . '<tr><td style="padding:16px 24px;background:#fafafa;border-top:1px solid #eeeeee;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#777777;" align="center">'
        . '<strong>' . h($org) . '</strong>' . ($address !== '' ? '<br>' . nl2br(h($address)) : '')
        . ($footerExtra !== '' ? '<br>' . $footerExtra : '')
        . '<br><br>Vous recevez ce message car vous êtes en contact avec ' . h($org) . '.<br>'
        . '<a href="' . h($unsubUrl) . '" style="color:#777777;">Se désinscrire</a>'
        . '</td></tr></table>' . $pixel
        . '</td></tr></table></body></html>';

    $text = html_to_text($content)
        . "\n\n--\n" . $org . ($address !== '' ? "\n" . $address : '')
        . "\nSe désinscrire : " . $unsubUrl . "\n";

    return ['subject' => $subject, 'html' => $html, 'text' => $text, 'unsubscribe_url' => $unsubUrl];
}

/**
 * Expéditeur SMTP réutilisant une seule connexion pour un lot.
 */
class SmtpSender
{
    private PHPMailer $mail;

    public function __construct()
    {
        $m = new PHPMailer(true);
        $m->isSMTP();
        $m->Host = setting('smtp_host');
        $m->Port = (int)setting('smtp_port');
        $secure = setting('smtp_secure');
        if ($secure === 'ssl') {
            $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $m->SMTPSecure = '';
            $m->SMTPAutoTLS = false;
        }
        $user = setting('smtp_user');
        if ($user !== '') {
            $m->SMTPAuth = true;
            $m->Username = $user;
            $m->Password = setting('smtp_pass');
        }
        $m->SMTPKeepAlive = true;
        $m->Timeout = 20;
        $m->CharSet = PHPMailer::CHARSET_UTF8;
        $m->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $m->XMailer = ' ';
        $this->mail = $m;
    }

    /**
     * @throws MailException
     */
    public function send(string $toEmail, string $toName, array $built, array $campaign): void
    {
        $m = $this->mail;
        $m->clearAllRecipients();
        $m->clearReplyTos();
        $m->clearCustomHeaders();
        $m->clearAttachments();

        $fromEmail = setting('from_email');
        $fromName = trim((string)($campaign['from_name'] ?? '')) ?: setting('from_name');
        $m->setFrom($fromEmail, $fromName, false);
        $m->Sender = $fromEmail;
        $replyTo = trim((string)($campaign['reply_to'] ?? '')) ?: setting('reply_to');
        if ($replyTo !== '' && valid_email($replyTo)) {
            $m->addReplyTo($replyTo, $fromName);
        }
        $m->addAddress($toEmail, $toName);
        $m->Subject = $built['subject'];
        $m->isHTML(true);
        $m->Body = $built['html'];
        $m->AltBody = $built['text'];
        $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $m->MessageID = '<' . bin2hex(random_bytes(12)) . '@' . $domain . '>';

        // Désinscription en un clic (RFC 8058) — exigé par Gmail / Yahoo pour les envois en nombre
        $m->addCustomHeader('List-Unsubscribe', '<' . $built['unsubscribe_url'] . '>, <mailto:' . $fromEmail . '?subject=desinscription>');
        $m->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        if (!empty($campaign['id'])) {
            $m->addCustomHeader('X-Campaign', 'bc-' . (int)$campaign['id']);
        }
        try {
            $m->send();
        } catch (MailException $e) {
            // Ajoute le code SMTP au message (utile pour distinguer erreur définitive / temporaire)
            $smtp = $m->getSMTPInstance();
            $info = $smtp->getError();
            $code = trim(($info['smtp_code'] ?? '') . ' ' . ($info['smtp_code_ex'] ?? ''));
            // Remet la session SMTP dans un état propre pour le destinataire suivant
            if ($smtp->connected()) {
                try {
                    $smtp->reset();
                } catch (\Throwable $ignored) {
                }
            }
            throw new MailException(trim($e->getMessage()) . ($code !== '' ? " [SMTP $code]" : ''), 0, $e);
        }
    }

    public function close(): void
    {
        try {
            $this->mail->smtpClose();
        } catch (\Throwable $e) {
        }
    }
}

/**
 * Envoi immédiat d'un test (compte dans le quota horaire).
 */
function send_test_email(array $campaign, string $to): void
{
    $contact = q_one('SELECT * FROM contacts WHERE email = ?', [$to]) ?? [
        'email' => $to, 'first_name' => 'Prénom', 'last_name' => 'Nom', 'token' => 'test',
    ];
    $built = build_email($campaign, $contact, 'test', true);
    $s = new SmtpSender();
    try {
        $s->send($to, trim($contact['first_name'] . ' ' . $contact['last_name']), $built, $campaign);
    } finally {
        $s->close();
    }
    q('INSERT INTO send_log (sent_at, kind) VALUES (?, ?)', [time(), 'test']);
}
