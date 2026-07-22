-- WABridge v1a/v1b şema. Idempotent: Migrator her satırı IF NOT EXISTS ile çalıştırır.
-- KVKK notu: hiçbir tabloda ham export/mesaj metni saklanmaz. `digests.payload_json`
-- yalnızca DigestBuilder'ın ÜRETTİĞİ özet JSON'unu tutar (bkz. src/Digest/DigestRepository.php).

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

-- token_hash = sha256(token); plaintext token asla DB'ye yazılmaz (sadece link/e-postada geçer).
CREATE TABLE IF NOT EXISTS magic_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    used_at TEXT,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

CREATE TABLE IF NOT EXISTS groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

-- payload_json = DigestBuilder::build() çıktısının json_encode'u, BİREBİR. Ham mesaj/gönderen
-- adı burada YOKTUR (parser/pipeline hiçbir zaman ham metni bu katmana sızdırmaz).
CREATE TABLE IF NOT EXISTS digests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
    week_label TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    noise_count INTEGER NOT NULL,
    source_message_count INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
    iyzico_ref TEXT,
    plan_code TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    trial_ends_at TEXT,
    current_period_end TEXT,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

-- iyzico_event_id UNIQUE: aynı webhook event'i iki kez gelirse ikinci INSERT başarısız olur (idempotency).
CREATE TABLE IF NOT EXISTS payment_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subscription_id INTEGER REFERENCES subscriptions(id) ON DELETE SET NULL,
    iyzico_event_id TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL,
    raw_payload_json TEXT NOT NULL,
    received_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    processed_at TEXT
);

CREATE INDEX IF NOT EXISTS idx_digests_group_id ON digests(group_id);
CREATE INDEX IF NOT EXISTS idx_groups_owner ON groups(owner_user_id);
CREATE INDEX IF NOT EXISTS idx_magic_links_user ON magic_links(user_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_group ON subscriptions(group_id);
