<?php
declare(strict_types=1);

/**
 * Client IMAP minimal (sans l'extension PHP imap, retirée de PHP 8.4).
 * Gère : LOGIN, SELECT, UID SEARCH, UID FETCH, UID STORE, CREATE, UID MOVE (ou COPY + suppression).
 */
class ImapClient
{
    /** @var resource|null */
    private $fp = null;
    private int $tag = 0;
    private array $caps = [];
    private string $delim = '.';

    public function __construct(
        private string $host,
        private int $port,
        private string $secure = 'ssl', // ssl | tls | none
        private int $timeout = 20
    ) {
    }

    public function connect(string $user, string $pass): void
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $this->host]]);
        $remote = ($this->secure === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $fp = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("Connexion IMAP impossible à {$this->host}:{$this->port} — $errstr"
                . (stripos($errstr, 'certificate') !== false || stripos($errstr, 'ssl') !== false
                    ? ' (essayez le nom du serveur o2switch, ex. chevre.o2switch.net)' : ''));
        }
        stream_set_timeout($fp, $this->timeout);
        $this->fp = $fp;
        $greet = $this->readLine();
        if (!str_starts_with($greet, '* OK') && !str_starts_with($greet, '* PREAUTH')) {
            throw new RuntimeException('Serveur IMAP inattendu : ' . trim($greet));
        }
        if ($this->secure === 'tls') {
            $this->command('STARTTLS');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS a échoué.');
            }
        }
        try {
            $this->command('LOGIN ' . $this->quote($user) . ' ' . $this->quote($pass));
        } catch (RuntimeException $e) {
            throw new RuntimeException('Identifiants IMAP refusés (même identifiant/mot de passe que la boîte mail).');
        }
        [$lines] = $this->command('CAPABILITY');
        foreach ($lines as $l) {
            if (str_starts_with($l, '* CAPABILITY')) {
                $this->caps = array_map('strtoupper', preg_split('/\s+/', trim(substr($l, 12))));
            }
        }
        [$lines] = $this->command('LIST "" "INBOX"');
        foreach ($lines as $l) {
            if (preg_match('/^\* LIST \([^)]*\) "(.)"/', $l, $m)) {
                $this->delim = $m[1];
            }
        }
    }

    public function select(string $box = 'INBOX'): void
    {
        $this->command('SELECT ' . $this->quote($box));
    }

    /** @return int[] UID des messages non lus */
    public function unseen(): array
    {
        [$lines] = $this->command('UID SEARCH UNSEEN');
        $uids = [];
        foreach ($lines as $l) {
            if (str_starts_with($l, '* SEARCH')) {
                foreach (preg_split('/\s+/', trim(substr($l, 8))) as $n) {
                    if ($n !== '') {
                        $uids[] = (int)$n;
                    }
                }
            }
        }
        sort($uids);
        return $uids;
    }

    /** Message brut, sans le marquer comme lu. */
    public function fetch(int $uid): string
    {
        [, $literals] = $this->command("UID FETCH $uid (BODY.PEEK[])");
        return $literals[0] ?? '';
    }

    public function markSeen(int $uid): void
    {
        $this->command("UID STORE $uid +FLAGS.SILENT (\\Seen)");
    }

    /** Déplace un message dans un sous-dossier de la boîte de réception (créé si besoin). */
    public function moveTo(int $uid, string $folder): void
    {
        $box = 'INBOX' . $this->delim . $folder;
        try {
            $this->command('CREATE ' . $this->quote($box));
            $this->command('SUBSCRIBE ' . $this->quote($box));
        } catch (RuntimeException $e) {
            // existe déjà
        }
        if (in_array('MOVE', $this->caps, true)) {
            $this->command("UID MOVE $uid " . $this->quote($box));
            return;
        }
        $this->command("UID COPY $uid " . $this->quote($box));
        $this->command("UID STORE $uid +FLAGS.SILENT (\\Deleted)");
        $this->command(in_array('UIDPLUS', $this->caps, true) ? "UID EXPUNGE $uid" : 'EXPUNGE');
    }

    public function logout(): void
    {
        if ($this->fp) {
            try {
                $this->command('LOGOUT');
            } catch (\Throwable $e) {
            }
            fclose($this->fp);
            $this->fp = null;
        }
    }

    /* ---------- protocole ---------- */

    private function quote(string $s): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }

    private function readLine(): string
    {
        $line = fgets($this->fp);
        if ($line === false) {
            $meta = stream_get_meta_data($this->fp);
            throw new RuntimeException($meta['timed_out'] ? 'Délai dépassé (serveur IMAP).' : 'Connexion IMAP interrompue.');
        }
        return $line;
    }

    /**
     * Envoie une commande et lit la réponse complète (y compris les littéraux {n}).
     * @return array{0: string[], 1: string[]} lignes non étiquetées, littéraux
     */
    private function command(string $cmd): array
    {
        $tag = 'A' . str_pad((string)++$this->tag, 4, '0', STR_PAD_LEFT);
        fwrite($this->fp, "$tag $cmd\r\n");
        $lines = [];
        $literals = [];
        while (true) {
            $line = $this->readLine();
            // littéral : {123}\r\n suivi de 123 octets
            while (preg_match('/\{(\d+)\}\r\n$/', $line, $m)) {
                $n = (int)$m[1];
                $data = '';
                while (strlen($data) < $n) {
                    $chunk = fread($this->fp, $n - strlen($data));
                    if ($chunk === false || $chunk === '') {
                        throw new RuntimeException('Lecture IMAP incomplète.');
                    }
                    $data .= $chunk;
                }
                $literals[] = $data;
                $line = rtrim($line) . ' ' . $this->readLine();
            }
            if (str_starts_with($line, $tag . ' ')) {
                $status = substr($line, strlen($tag) + 1);
                if (!str_starts_with($status, 'OK')) {
                    throw new RuntimeException('IMAP ' . explode(' ', $cmd)[0] . ' : ' . trim($status));
                }
                return [$lines, $literals];
            }
            if (str_starts_with($line, '+') && $cmd === 'STARTTLS') {
                continue;
            }
            $lines[] = rtrim($line, "\r\n");
        }
    }
}
