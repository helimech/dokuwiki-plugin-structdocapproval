CREATE TABLE IF NOT EXISTS struct_docapproval_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sort_order INTEGER NOT NULL,
    include_rule INTEGER NOT NULL DEFAULT 1,
    pattern TEXT NOT NULL,
    reviewer TEXT NOT NULL DEFAULT '',
    training TEXT NOT NULL DEFAULT '',
    publisher TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_rules_order
    ON struct_docapproval_rules(sort_order, id);

CREATE TABLE IF NOT EXISTS struct_docapproval_pages (
    pid TEXT PRIMARY KEY,
    controlled INTEGER NOT NULL DEFAULT 0,
    reviewer TEXT NOT NULL DEFAULT '',
    training TEXT NOT NULL DEFAULT '',
    publisher TEXT NOT NULL DEFAULT '',
    resolved_at INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_struct_docapproval_pages_controlled
    ON struct_docapproval_pages(controlled, pid);
