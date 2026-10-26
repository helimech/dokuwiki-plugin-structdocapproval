<?php

use dokuwiki\Extension\Plugin;

class helper_plugin_structdocapproval_reviewcycle extends Plugin
{
    protected function db()
    {
        /** @var helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        return $helper ? $helper->getDB() : null;
    }

    public function start(
        string $pid,
        int $workflowRevision,
        string $reviewer1,
        string $reviewer2,
        string $startedBy
    ): int {
        $db = $this->db();
        if (!$db) throw new RuntimeException('DocApproval database is unavailable.');

        $pid = cleanID($pid);
        $db->query(
            'UPDATE struct_docapproval_review_cycles SET active=0 WHERE pid=? AND active=1',
            [$pid]
        );
        $db->query(
            'INSERT INTO struct_docapproval_review_cycles ' .
            '(pid,workflow_revision,reviewer1,reviewer2,started_by,started_at,active) ' .
            'VALUES (?,?,?,?,?,?,1)',
            [$pid, $workflowRevision, $reviewer1, $reviewer2, $startedBy, time()]
        );
        return (int)$db->getPdo()->lastInsertId();
    }

    public function remove(int $id): void
    {
        $db = $this->db();
        if (!$db || !$id) return;
        $db->query('DELETE FROM struct_docapproval_review_cycles WHERE id=?', [$id]);
    }

    public function deactivate(string $pid): void
    {
        $db = $this->db();
        if (!$db) return;
        $db->query(
            'UPDATE struct_docapproval_review_cycles SET active=0 WHERE pid=? AND active=1',
            [cleanID($pid)]
        );
    }

    public function getActiveForRevision(string $pid, int $workflowRevision): ?array
    {
        $db = $this->db();
        if (!$db || !$workflowRevision) return null;

        $rows = $db->queryAll(
            'SELECT * FROM struct_docapproval_review_cycles ' .
            'WHERE pid=? AND workflow_revision=? AND active=1 ORDER BY id DESC LIMIT 1',
            [cleanID($pid), $workflowRevision]
        ) ?: [];

        return $rows ? $rows[0] : null;
    }
}
