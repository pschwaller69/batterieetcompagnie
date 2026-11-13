<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $path = config('db_path', APP_ROOT . '/data/mailing.sqlite');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 10000');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $version = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    $migrations = [
        1 => <<<SQL
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    name TEXT NOT NULL DEFAULT '',
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT
);
CREATE TABLE contacts (
    id INTEGER PRIMARY KEY,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    first_name TEXT NOT NULL DEFAULT '',
    last_name TEXT NOT NULL DEFAULT '',
    phone TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'subscribed', -- subscribed | unsubscribed | bounced
    token TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    unsubscribed_at TEXT
);
CREATE TABLE lists (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE COLLATE NOCASE,
    description TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE contact_list (
    contact_id INTEGER NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
    list_id INTEGER NOT NULL REFERENCES lists(id) ON DELETE CASCADE,
    PRIMARY KEY (contact_id, list_id)
);
CREATE TABLE campaigns (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    subject TEXT NOT NULL DEFAULT '',
    preheader TEXT NOT NULL DEFAULT '',
    from_name TEXT NOT NULL DEFAULT '',
    reply_to TEXT NOT NULL DEFAULT '',
    html TEXT NOT NULL DEFAULT '',
    target_all INTEGER NOT NULL DEFAULT 0,
    track_opens INTEGER NOT NULL DEFAULT 1,
    status TEXT NOT NULL DEFAULT 'draft', -- draft | scheduled | sending | paused | sent | cancelled
    scheduled_at TEXT,
    queued_at TEXT,
    finished_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE campaign_lists (
    campaign_id INTEGER NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
    list_id INTEGER NOT NULL REFERENCES lists(id) ON DELETE CASCADE,
    PRIMARY KEY (campaign_id, list_id)
);
CREATE TABLE messages (
    id INTEGER PRIMARY KEY,
    campaign_id INTEGER NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
    contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL,
    email TEXT NOT NULL,
    token TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'pending', -- pending | sent | failed | skipped
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT,
    sent_at TEXT,
    opened_at TEXT,
    open_count INTEGER NOT NULL DEFAULT 0,
    UNIQUE (campaign_id, email)
);
CREATE INDEX idx_messages_pending ON messages(status, campaign_id);
CREATE TABLE send_log (
    id INTEGER PRIMARY KEY,
    sent_at INTEGER NOT NULL, -- timestamp unix
    kind TEXT NOT NULL DEFAULT 'campaign'
);
CREATE INDEX idx_send_log_time ON send_log(sent_at);
CREATE TABLE activity (
    id INTEGER PRIMARY KEY,
    at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    level TEXT NOT NULL DEFAULT 'info',
    message TEXT NOT NULL
);
SQL,
    ];
    foreach ($migrations as $v => $sql) {
        if ($version < $v) {
            $pdo->beginTransaction();
            $pdo->exec($sql);
            $pdo->exec('PRAGMA user_version = ' . $v);
            $pdo->commit();
        }
    }
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function q_val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function log_activity(string $message, string $level = 'info'): void
{
    q('INSERT INTO activity (level, message) VALUES (?, ?)', [$level, $message]);
    q("DELETE FROM activity WHERE id <= (SELECT MAX(id) - 2000 FROM activity)");
}
