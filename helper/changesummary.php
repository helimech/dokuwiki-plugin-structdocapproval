<?php

use dokuwiki\Extension\Plugin;

class helper_plugin_structdocapproval_changesummary extends Plugin
{
    protected function db()
    {
        /** @var helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        return $helper ? $helper->getDB() : null;
    }

    public function get(string $pid): array
    {
        $db = $this->db();
        if (!$db) {
            return [
                'pid' => cleanID($pid),
                'working_revision' => 0,
                'summary' => '',
                'updated_by' => '',
                'updated_at' => 0,
            ];
        }

        $rows = $db->queryAll(
            'SELECT * FROM struct_docapproval_change_summary WHERE pid=? LIMIT 1',
            [cleanID($pid)]
        ) ?: [];

        if ($rows) return $rows[0];

        return [
            'pid' => cleanID($pid),
            'working_revision' => 0,
            'summary' => '',
            'updated_by' => '',
            'updated_at' => 0,
        ];
    }

    public function getSummary(string $pid): string
    {
        return trim((string)$this->get($pid)['summary']);
    }

    public function save(string $pid, string $summary, string $actor, int $workingRevision): void
    {
        $db = $this->db();
        if (!$db) throw new RuntimeException('DocApproval database is unavailable.');

        $pid = cleanID($pid);
        $summary = trim($summary);
        $actor = trim($actor);
        $workingRevision = max(0, $workingRevision);
        $current = $this->get($pid);

        if (
            trim((string)$current['summary']) === $summary &&
            (int)$current['working_revision'] === $workingRevision
        ) {
            return;
        }

        $now = time();
        $db->query(
            'REPLACE INTO struct_docapproval_change_summary ' .
            '(pid,working_revision,summary,updated_by,updated_at) VALUES (?,?,?,?,?)',
            [$pid, $workingRevision, $summary, $actor, $now]
        );

        $db->query(
            'INSERT INTO struct_docapproval_change_summary_history ' .
            '(pid,working_revision,summary,actor,changed_at) VALUES (?,?,?,?,?)',
            [$pid, $workingRevision, $summary, $actor, $now]
        );
    }

    /**
     * Start a new normal document-change cycle after a Published page is edited.
     * The prior publication retains its immutable summary in the publication snapshot.
     */
    public function startCycle(string $pid, int $workingRevision, string $actor): void
    {
        $this->save($pid, '', $actor, $workingRevision);
    }

    /**
     * Carry the current summary forward when the same working cycle receives another
     * content edit and therefore a new DokuWiki revision.
     */
    public function carryForward(string $pid, int $workingRevision, string $actor): void
    {
        $current = $this->get($pid);
        $summary = (string)$current['summary'];

        if ((int)$current['working_revision'] === $workingRevision) return;

        $db = $this->db();
        if (!$db) return;

        // Updating the revision pointer alone is not a semantic summary change, so do
        // not add another history entry.
        $db->query(
            'REPLACE INTO struct_docapproval_change_summary ' .
            '(pid,working_revision,summary,updated_by,updated_at) VALUES (?,?,?,?,?)',
            [
                cleanID($pid),
                $workingRevision,
                $summary,
                (string)($current['updated_by'] ?: $actor),
                (int)($current['updated_at'] ?: time()),
            ]
        );
    }

    public function history(string $pid): array
    {
        $db = $this->db();
        if (!$db) return [];

        return $db->queryAll(
            'SELECT * FROM struct_docapproval_change_summary_history WHERE pid=? ORDER BY id ASC',
            [cleanID($pid)]
        ) ?: [];
    }
}
