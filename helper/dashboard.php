<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class helper_plugin_structdocapproval_dashboard extends Plugin
{
    protected function db()
    {
        /** @var helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        return $helper ? $helper->getDB() : null;
    }

    public function getDocuments(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $db = $this->db();

        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        if (!$db || !$publication) {
            return ['filters' => $filters, 'rows' => [], 'counts' => $this->emptyCounts()];
        }

        $rows = $db->queryAll(
            'SELECT d.*,p.reviewer AS rule_reviewer,p.training AS rule_training,' .
            'p.publisher AS rule_publisher,COALESCE(cs.summary, \'\') AS working_summary ' .
            'FROM data_struct_docapproval d ' .
            'JOIN struct_docapproval_pages p ON p.pid=d.pid ' .
            'LEFT JOIN struct_docapproval_change_summary cs ON cs.pid=d.pid ' .
            'WHERE d.latest=1 AND p.controlled=1 ORDER BY d.pid'
        ) ?: [];

        $assignments = Assignments::getInstance();
        $assigneeGroups = $filters['assignee'] !== '' ? $this->userGroups($filters['assignee']) : [];
        $assignedSearchCache = [];
        $titleCache = [];
        $out = [];

        foreach ($rows as $row) {
            $pid = cleanID((string)($row['pid'] ?? ''));
            if ($pid === '' || auth_quickaclcheck($pid) < AUTH_READ) continue;

            $status = (string)($row['col1'] ?? '');
            if (!array_key_exists($pid, $titleCache)) {
                $titleCache[$pid] = $this->pageTitle($pid);
            }
            $title = $titleCache[$pid];

            if (
                $filters['page'] !== '' &&
                stripos($pid, $filters['page']) === false &&
                stripos($title, $filters['page']) === false
            ) {
                continue;
            }
            if ($filters['status'] !== 'all' && $status !== $filters['status']) continue;

            $assignedSpec = '';
            if ($status === Constants::STATUS_AWAITING_REVIEW) {
                $assignedSpec = trim((string)($row['col9'] ?? ''));
            } elseif ($status === Constants::STATUS_AWAITING_TRAINING) {
                $assignedSpec = trim((string)($row['rule_training'] ?? ''));
            } elseif ($status === Constants::STATUS_READY_TO_PUBLISH) {
                $assignedSpec = trim((string)($row['rule_publisher'] ?? ''));
            }

            if ($filters['assignee'] !== '') {
                $matches = false;
                if ($status === Constants::STATUS_AWAITING_REVIEW) {
                    $matches = $assignedSpec === $filters['assignee'];
                } elseif ($assignedSpec !== '') {
                    $matches = $assignments->userMatchesSpec(
                        $assignedSpec,
                        $filters['assignee'],
                        $assigneeGroups
                    );
                }
                if (!$matches) continue;
            }

            $latestPublished = WorkflowRecord::latestPublished($pid);
            $publishedVersion = $latestPublished
                ? Constants::formatVersion((string)$latestPublished->get('version'))
                : '';
            $publishedRevision = $latestPublished ? (int)$latestPublished->get('revision') : 0;
            $publishedAt = $latestPublished
                ? (string)($latestPublished->get('published_at') ?: $latestPublished->get('datetime'))
                : '';
            $publishedBy = $latestPublished ? (string)$latestPublished->get('published_by') : '';

            $formalRevision = $publication->getCurrentPublicationRevision($pid);
            $snapshot = $formalRevision ? $publication->getSnapshot($pid, $formalRevision) : null;
            $compliance = $snapshot
                ? $publication->getComplianceForPage($pid, $formalRevision)
                : null;

            $required = $compliance ? count($compliance['required_users']) : 0;
            $acknowledged = $compliance ? count($compliance['acknowledged_users']) : 0;
            $outstanding = $compliance ? count($compliance['outstanding_users']) : 0;
            $ackDueAt = $snapshot ? (int)($snapshot['ack_due_at'] ?? 0) : 0;
            $ackOverdue = $ackDueAt > 0 && time() > $ackDueAt && $outstanding > 0;

            $ackState = 'none';
            if ($snapshot) {
                if ($required === 0) {
                    $ackState = 'not_required';
                } elseif ($outstanding > 0) {
                    $ackState = 'outstanding';
                } else {
                    $ackState = 'complete';
                }
            }

            if ($filters['ack'] !== 'all') {
                if ($filters['ack'] === 'none_required') {
                    if (!in_array($ackState, ['none', 'not_required'], true)) continue;
                } elseif ($ackState !== $filters['ack']) {
                    continue;
                }
            }

            $summary = $status === Constants::STATUS_PUBLISHED && $snapshot
                ? trim((string)($snapshot['change_summary'] ?? ''))
                : trim((string)($row['working_summary'] ?? ''));

            // Keep the visible assignment compact, but expose resolved users as
            // row metadata so the browser can live-filter an @group assignment by
            // either a member's login or configured full name.
            if (!array_key_exists($assignedSpec, $assignedSearchCache)) {
                $assignedSearchParts = [];
                if ($assignedSpec !== '') {
                    $assignedSearchParts[] = $assignedSpec;
                    foreach ($assignments->resolveUsers($assignedSpec) as $assignedUser) {
                        $assignedSearchParts[] = $assignedUser;
                        $assignedName = $publication->getUserDisplayName($assignedUser);
                        if ($assignedName !== $assignedUser) $assignedSearchParts[] = $assignedName;
                    }
                }
                $assignedSearchCache[$assignedSpec] = implode(
                    ' ',
                    array_values(array_unique($assignedSearchParts))
                );
            }
            $assignedSearch = $assignedSearchCache[$assignedSpec];

            $out[] = [
                'pid' => $pid,
                'title' => $title,
                'status' => $status,
                'updated' => (string)($row['col4'] ?? ''),
                'revision' => (int)($row['col5'] ?? 0),
                'assigned_spec' => $assignedSpec,
                'assigned_search' => $assignedSearch,
                'submitted_by' => (string)($row['col7'] ?? ''),
                'submitted_at' => (string)($row['col8'] ?? ''),
                'assigned_reviewer' => (string)($row['col9'] ?? ''),
                'reviewed_by' => (string)($row['col10'] ?? ''),
                'reviewed_at' => (string)($row['col11'] ?? ''),
                'training_by' => (string)($row['col12'] ?? ''),
                'training_at' => (string)($row['col13'] ?? ''),
                'training_disposition' => (string)($row['col14'] ?? ''),
                'training_note' => (string)($row['col15'] ?? ''),
                'comment' => (string)($row['col18'] ?? ''),
                'change_summary' => $summary,
                'published_version' => $publishedVersion,
                'published_revision' => $publishedRevision,
                'published_at' => $publishedAt,
                'published_by' => $publishedBy,
                'formal_publication_revision' => $formalRevision,
                'ack_state' => $ackState,
                'ack_required' => $required,
                'acknowledged' => $acknowledged,
                'ack_outstanding' => $outstanding,
                'ack_due_at' => $ackDueAt,
                'ack_overdue' => $ackOverdue,
                'acknowledged_users' => $compliance ? (array)$compliance['acknowledged_users'] : [],
                'outstanding_users' => $compliance ? (array)$compliance['outstanding_users'] : [],
                'acknowledge_spec' => $snapshot ? (string)($snapshot['acknowledge_spec'] ?? '') : '',
            ];
        }

        $historyMap = $this->loadPublicationHistory($db, array_column($out, 'pid'), 5);
        foreach ($out as &$item) {
            $item['publication_history'] = $historyMap[$item['pid']] ?? [];
        }
        unset($item);

        return [
            'filters' => $filters,
            'rows' => $out,
            'counts' => $this->countRows($out),
        ];
    }

    protected function loadPublicationHistory($db, array $pids, int $limit): array
    {
        if (!$pids || $limit < 1) return [];

        $allowed = array_fill_keys($pids, true);
        $rows = $db->queryAll(
            'SELECT p.pid,p.publication_revision,p.content_revision,p.version,p.published_at,p.ack_due_at,' .
            'COUNT(DISTINCT r.user) AS required_count,' .
            'COUNT(DISTINCT a.user) AS acknowledged_count ' .
            'FROM struct_docapproval_publications p ' .
            'LEFT JOIN struct_docapproval_requirements r ' .
            'ON r.pid=p.pid AND r.publication_revision=p.publication_revision ' .
            'LEFT JOIN struct_docapproval_acknowledgements a ' .
            'ON a.pid=r.pid AND a.publication_revision=r.publication_revision AND a.user=r.user ' .
            'GROUP BY p.pid,p.publication_revision,p.content_revision,p.version,p.published_at,p.ack_due_at ' .
            'ORDER BY p.pid,p.published_at DESC,p.publication_revision DESC'
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $pid = cleanID((string)($row['pid'] ?? ''));
            if (!isset($allowed[$pid])) continue;
            if (isset($out[$pid]) && count($out[$pid]) >= $limit) continue;

            $out[$pid][] = [
                'publication_revision' => (int)$row['publication_revision'],
                'content_revision' => (int)$row['content_revision'],
                'version' => (string)$row['version'],
                'published_at' => (int)$row['published_at'],
                'ack_due_at' => (int)($row['ack_due_at'] ?? 0),
                'required' => (int)$row['required_count'],
                'acknowledged' => (int)$row['acknowledged_count'],
            ];
        }
        return $out;
    }

    public function getOverview(): array
    {
        $report = $this->getDocuments([]);
        return $report['counts'];
    }

    public function getAssigneeSuggestions(): array
    {
        global $auth;

        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        if (!$auth || !$publication || !$auth->canDo('getUsers')) return [];

        $users = $auth->retrieveUsers(0, 5000, []) ?: [];
        $out = [];
        foreach ($users as $login => $data) {
            if (is_int($login) && is_array($data) && isset($data['user'])) $login = $data['user'];
            if (!is_string($login) || trim($login) === '') continue;

            $out[] = [
                'user' => $login,
                'name' => $publication->getUserDisplayName($login),
            ];
        }

        usort($out, static function (array $a, array $b): int {
            return strnatcasecmp((string)$a['name'], (string)$b['name']);
        });
        return $out;
    }

    protected function pageTitle(string $pid): string
    {
        $title = '';
        if (function_exists('p_get_first_heading')) {
            $title = trim((string)p_get_first_heading($pid));
        }
        return $title !== '' ? $title : $pid;
    }

    protected function normalizeFilters(array $filters): array
    {
        $status = (string)($filters['status'] ?? 'all');
        if (!in_array($status, array_merge(['all'], Constants::statuses()), true)) $status = 'all';

        $ack = (string)($filters['ack'] ?? 'all');
        if (!in_array($ack, ['all', 'outstanding', 'complete', 'none_required'], true)) $ack = 'all';

        return [
            'page' => trim((string)($filters['page'] ?? '')),
            'status' => $status,
            'assignee' => trim((string)($filters['assignee'] ?? '')),
            'ack' => $ack,
        ];
    }

    protected function userGroups(string $user): array
    {
        global $auth;
        if (!$auth || $user === '') return [];
        $data = $auth->getUserData($user);
        return $data ? (array)($data['grps'] ?? []) : [];
    }

    protected function emptyCounts(): array
    {
        return [
            'documents' => 0,
            Constants::STATUS_DRAFT => 0,
            Constants::STATUS_AWAITING_REVIEW => 0,
            Constants::STATUS_AWAITING_TRAINING => 0,
            Constants::STATUS_READY_TO_PUBLISH => 0,
            Constants::STATUS_PUBLISHED => 0,
            'ack_outstanding' => 0,
        ];
    }

    protected function countRows(array $rows): array
    {
        $counts = $this->emptyCounts();
        $counts['documents'] = count($rows);

        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if (array_key_exists($status, $counts)) $counts[$status]++;
            $counts['ack_outstanding'] += (int)($row['ack_outstanding'] ?? 0);
        }
        return $counts;
    }
}
