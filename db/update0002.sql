ALTER TABLE struct_docapproval_rules ADD COLUMN notify TEXT NOT NULL DEFAULT '';
ALTER TABLE struct_docapproval_rules ADD COLUMN acknowledge TEXT NOT NULL DEFAULT '';

ALTER TABLE struct_docapproval_pages ADD COLUMN notify TEXT NOT NULL DEFAULT '';
ALTER TABLE struct_docapproval_pages ADD COLUMN acknowledge TEXT NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS struct_docapproval_acknowledgements (
    pid TEXT NOT NULL,
    user TEXT NOT NULL,
    publication_revision INTEGER NOT NULL,
    content_revision INTEGER NOT NULL,
    version TEXT NOT NULL DEFAULT '',
    acknowledged_at INTEGER NOT NULL,
    PRIMARY KEY (pid, user, publication_revision)
);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_ack_pid_rev
    ON struct_docapproval_acknowledgements(pid, publication_revision);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_ack_user
    ON struct_docapproval_acknowledgements(user, acknowledged_at);
