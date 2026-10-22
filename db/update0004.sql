ALTER TABLE struct_docapproval_publications
    ADD COLUMN change_summary TEXT NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS struct_docapproval_change_summary (
    pid TEXT PRIMARY KEY,
    working_revision INTEGER NOT NULL DEFAULT 0,
    summary TEXT NOT NULL DEFAULT '',
    updated_by TEXT NOT NULL DEFAULT '',
    updated_at INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS struct_docapproval_change_summary_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pid TEXT NOT NULL,
    working_revision INTEGER NOT NULL DEFAULT 0,
    summary TEXT NOT NULL DEFAULT '',
    actor TEXT NOT NULL DEFAULT '',
    changed_at INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_change_summary_history_pid
    ON struct_docapproval_change_summary_history(pid, id);
