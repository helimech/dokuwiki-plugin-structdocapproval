ALTER TABLE struct_docapproval_publications
    ADD COLUMN ack_due_at INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS struct_docapproval_reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id TEXT NOT NULL,
    pid TEXT NOT NULL,
    publication_revision INTEGER NOT NULL,
    user TEXT NOT NULL,
    email TEXT NOT NULL DEFAULT '',
    sent_by TEXT NOT NULL DEFAULT '',
    sent_at INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_reminders_pub
    ON struct_docapproval_reminders(pid, publication_revision, sent_at);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_reminders_user
    ON struct_docapproval_reminders(user, sent_at);
