<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class helper_plugin_structdocapproval_tasks extends Plugin
{
    protected function db()
    {
        /** @var helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        return $helper ? $helper->getDB() : null;
    }

    /**
     * Return only tasks belonging to the currently logged-in user.
     *
     * This intentionally does not accept an arbitrary username from page syntax.
     * A single dashboard page can therefore be shared by the whole organization
     * without allowing a reader to request somebody else's personal work queue.
     */
    public function getCurrentUserTasks(): array
    {
        global $INPUT, $USERINFO;

        $user = trim($INPUT->server->str('REMOTE_USER'));
        if ($user === '') {
            return [
                'user' => '',
                'acknowledgements' => [],
                'reviews' => [],
                'training' => [],
                'publish' => [],
                'recent_acknowledgements' => [],
            ];
        }

        $workflow = $this->getWorkflowTasks($user, (array)($USERINFO['grps'] ?? []));

        return [
            'user' => $user,
            'acknowledgements' => $this->getPendingAcknowledgements($user),
            'reviews' => $workflow['reviews'],
            'training' => $workflow['training'],
            'publish' => $workflow['publish'],
            'recent_acknowledgements' => $this->getRecentAcknowledgements($user, 10),
        ];
    }

    protected function pageTitle(string $pid): string
    {
        $title = '';
        if (function_exists('p_get_first_heading')) {
            $title = trim((string)p_get_first_heading($pid));
        }
        return $title !== '' ? $title : $pid;
    }

    protected function getPendingAcknowledgements(string $user): array
    {
        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        /** @var helper_plugin_structdocapproval_db $dbHelper */
        $dbHelper = plugin_load('helper', 'structdocapproval_db');
        if (!$publication || !$dbHelper) return [];

        $out = [];
        foreach ($publication->getPendingForUser($user) as $row) {
            $pid = cleanID((string)($row['pid'] ?? ''));
            $publicationRevision = (int)($row['publication_revision'] ?? 0);
            if ($pid === '' || !$publicationRevision) continue;
            if (auth_quickaclcheck($pid) < AUTH_READ) continue;
            if (!$dbHelper->isControlled($pid)) continue;

            $snapshot = $publication->getSnapshot($pid, $publicationRevision);
            if (!$snapshot) continue;

            // A Published-preserving Minor Change remains in the same formal
            // acknowledgement cycle, but the acknowledgement must be made against
            // the actual current Published content revision.
            $published = WorkflowRecord::latestPublished($pid);
            if (!$published) continue;

            $out[] = [
                'pid' => $pid,
                'title' => $this->pageTitle($pid),
                'publication_revision' => $publicationRevision,
                'content_revision' => (int)$published->get('revision'),
                'version' => (string)($snapshot['version'] ?? $row['version'] ?? ''),
                'published_at' => (int)($snapshot['published_at'] ?? $row['published_at'] ?? 0),
                'ack_due_at' => (int)($snapshot['ack_due_at'] ?? $row['ack_due_at'] ?? 0),
                'change_summary' => (string)($snapshot['change_summary'] ?? ''),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $cmp = ((int)$a['published_at']) <=> ((int)$b['published_at']);
            if ($cmp !== 0) return $cmp; // oldest outstanding first
            return strnatcasecmp((string)$a['pid'], (string)$b['pid']);
        });

        return $out;
    }

    protected function getWorkflowTasks(string $user, array $groups): array
    {
        $out = ['reviews' => [], 'training' => [], 'publish' => []];
        $db = $this->db();
        if (!$db) return $out;

        $rows = $db->queryAll(
            'SELECT d.pid,d.col1 AS status,d.col4 AS updated,d.col5 AS revision,' .
            'd.col9 AS assigned_reviewer,p.training AS rule_training,p.publisher AS rule_publisher,' .
            'COALESCE(cs.summary, \'\') AS change_summary ' .
            'FROM data_struct_docapproval d ' .
            'JOIN struct_docapproval_pages p ON p.pid=d.pid ' .
            'LEFT JOIN struct_docapproval_change_summary cs ON cs.pid=d.pid ' .
            'WHERE d.latest=1 AND p.controlled=1 AND d.col1 IN (?,?,?) ' .
            'ORDER BY d.col4 ASC,d.pid ASC',
            [
                Constants::STATUS_AWAITING_REVIEW,
                Constants::STATUS_AWAITING_TRAINING,
                Constants::STATUS_READY_TO_PUBLISH,
            ]
        ) ?: [];

        $assignments = Assignments::getInstance();

        foreach ($rows as $row) {
            $pid = cleanID((string)($row['pid'] ?? ''));
            if ($pid === '' || auth_quickaclcheck($pid) < AUTH_READ) continue;

            $task = [
                'pid' => $pid,
                'title' => $this->pageTitle($pid),
                'updated' => (string)($row['updated'] ?? ''),
                'revision' => (int)($row['revision'] ?? 0),
                'change_summary' => trim((string)($row['change_summary'] ?? '')),
            ];

            switch ((string)($row['status'] ?? '')) {
                case Constants::STATUS_AWAITING_REVIEW:
                    // Reviewer selection is frozen to one login for this review cycle.
                    if (trim((string)($row['assigned_reviewer'] ?? '')) === $user) {
                        $out['reviews'][] = $task;
                    }
                    break;

                case Constants::STATUS_AWAITING_TRAINING:
                    // Training remains assignment-rule based and may be a user/@group list.
                    if ($assignments->userMatchesSpec(
                        (string)($row['rule_training'] ?? ''),
                        $user,
                        $groups
                    )) {
                        $out['training'][] = $task;
                    }
                    break;

                case Constants::STATUS_READY_TO_PUBLISH:
                    // Publisher remains assignment-rule based and may be a user/@group list.
                    if ($assignments->userMatchesSpec(
                        (string)($row['rule_publisher'] ?? ''),
                        $user,
                        $groups
                    )) {
                        $out['publish'][] = $task;
                    }
                    break;
            }
        }

        return $out;
    }

    protected function getRecentAcknowledgements(string $user, int $limit): array
    {
        $db = $this->db();
        if (!$db || $limit < 1) return [];

        // Pull extra rows before ACL filtering so an inaccessible historical page
        // does not unnecessarily shorten the visible recent list.
        $scanLimit = max($limit * 3, $limit);
        $rows = $db->queryAll(
            'SELECT a.pid,a.publication_revision,a.content_revision,a.version,a.acknowledged_at,' .
            'p.published_at,p.change_summary ' .
            'FROM struct_docapproval_acknowledgements a ' .
            'LEFT JOIN struct_docapproval_publications p ' .
            'ON p.pid=a.pid AND p.publication_revision=a.publication_revision ' .
            'WHERE a.user=? ORDER BY a.acknowledged_at DESC LIMIT ' . (int)$scanLimit,
            [$user]
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $pid = cleanID((string)($row['pid'] ?? ''));
            if ($pid === '' || auth_quickaclcheck($pid) < AUTH_READ) continue;

            $out[] = [
                'pid' => $pid,
                'title' => $this->pageTitle($pid),
                'publication_revision' => (int)($row['publication_revision'] ?? 0),
                'content_revision' => (int)($row['content_revision'] ?? 0),
                'version' => (string)($row['version'] ?? ''),
                'published_at' => (int)($row['published_at'] ?? 0),
                'acknowledged_at' => (int)($row['acknowledged_at'] ?? 0),
                'change_summary' => (string)($row['change_summary'] ?? ''),
            ];

            if (count($out) >= $limit) break;
        }

        return $out;
    }
}
