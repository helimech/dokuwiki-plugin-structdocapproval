<?php

namespace dokuwiki\plugin\structdocapproval\meta;

use dokuwiki\plugin\sqlite\SQLiteDB;

class Assignments
{
    public const NO_REVISION_CONTROL_DIRECTIVE = '~~NOREVISIONCONTROL~~';

    /** @var SQLiteDB */
    protected $sqlite;
    protected $rules = [];
    protected static $instance;

    public static function getInstance(bool $forceReload = false): self
    {
        if (!self::$instance || $forceReload) self::$instance = new self();
        return self::$instance;
    }

    protected function __construct()
    {
        /** @var \helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        $this->sqlite = $helper->getDB();
        $this->loadRules();
    }

    protected function loadRules(): void
    {
        $this->rules = $this->sqlite->queryAll(
            'SELECT * FROM struct_docapproval_rules ORDER BY sort_order ASC, id ASC'
        ) ?: [];
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function addRule(bool $include, string $pattern, string $reviewer = '', string $training = '', string $publisher = ''): int
    {
        $max = (int)$this->sqlite->queryValue('SELECT COALESCE(MAX(sort_order), 0) FROM struct_docapproval_rules');
        $order = $max + 10;
        $this->sqlite->query(
            'INSERT INTO struct_docapproval_rules (sort_order, include_rule, pattern, reviewer, training, publisher) VALUES (?,?,?,?,?,?)',
            [$order, $include ? 1 : 0, trim($pattern), trim($reviewer), trim($training), trim($publisher)]
        );
        $id = (int)$this->sqlite->getPdo()->lastInsertId();
        $this->loadRules();
        return $id;
    }

    public function updateRule(int $id, bool $include, string $pattern, string $reviewer = '', string $training = '', string $publisher = ''): bool
    {
        $ok = (bool)$this->sqlite->query(
            'UPDATE struct_docapproval_rules SET include_rule=?, pattern=?, reviewer=?, training=?, publisher=? WHERE id=?',
            [$include ? 1 : 0, trim($pattern), trim($reviewer), trim($training), trim($publisher), $id]
        );
        $this->loadRules();
        return $ok;
    }

    public function deleteRule(int $id): bool
    {
        $ok = (bool)$this->sqlite->query('DELETE FROM struct_docapproval_rules WHERE id = ?', [$id]);
        $this->normalizeOrder();
        $this->loadRules();
        return $ok;
    }

    public function moveRule(int $id, int $direction): bool
    {
        $rules = $this->getRules();
        $index = null;
        foreach ($rules as $i => $rule) {
            if ((int)$rule['id'] === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) return false;
        $swap = $index + ($direction < 0 ? -1 : 1);
        if (!isset($rules[$swap])) return true;

        $a = (int)$rules[$index]['sort_order'];
        $b = (int)$rules[$swap]['sort_order'];
        $this->sqlite->query('UPDATE struct_docapproval_rules SET sort_order=? WHERE id=?', [$b, $id]);
        $this->sqlite->query('UPDATE struct_docapproval_rules SET sort_order=? WHERE id=?', [$a, (int)$rules[$swap]['id']]);
        $this->loadRules();
        return true;
    }

    protected function normalizeOrder(): void
    {
        $rules = $this->sqlite->queryAll('SELECT id FROM struct_docapproval_rules ORDER BY sort_order ASC, id ASC') ?: [];
        $n = 10;
        foreach ($rules as $rule) {
            $this->sqlite->query('UPDATE struct_docapproval_rules SET sort_order=? WHERE id=?', [$n, (int)$rule['id']]);
            $n += 10;
        }
    }

    public function getRule(int $id): ?array
    {
        foreach ($this->rules as $rule) {
            if ((int)$rule['id'] === $id) return $rule;
        }
        return null;
    }

    /**
     * Resolve only the ordered central assignment rules for a page.
     *
     * Later matching + rules override only nonblank role fields. A - rule toggles
     * control off but preserves accumulated roles, allowing a later + rule to
     * re-include a specific page while inheriting earlier role values.
     */
    public function resolveRulesOnly(string $pid): array
    {
        $pid = cleanID($pid);
        $resolved = [
            'controlled' => false,
            Constants::ROLE_REVIEWER => '',
            Constants::ROLE_TRAINING => '',
            Constants::ROLE_PUBLISHER => '',
            'matched_rules' => [],
            'excluded_by_directive' => false,
        ];

        /** @var \helper_plugin_structdocapproval_assignments $matcher */
        $matcher = plugin_load('helper', 'structdocapproval_assignments');
        $pns = ':' . getNS($pid) . ':';

        foreach ($this->rules as $rule) {
            if (!$matcher->matchPagePattern($rule['pattern'], $pid, $pns)) continue;
            $resolved['matched_rules'][] = (int)$rule['id'];
            if (!(int)$rule['include_rule']) {
                $resolved['controlled'] = false;
                continue;
            }

            $resolved['controlled'] = true;
            foreach ([Constants::ROLE_REVIEWER, Constants::ROLE_TRAINING, Constants::ROLE_PUBLISHER] as $role) {
                if (trim((string)$rule[$role]) !== '') {
                    $resolved[$role] = trim((string)$rule[$role]);
                }
            }
        }

        return $resolved;
    }

    /**
     * Resolve central rules plus the page-local ~~NOREVISIONCONTROL~~ directive.
     *
     * The directive has final precedence over the ordered rules, but the resolved
     * role values are retained in the materialized row for audit/authorization use.
     */
    public function resolvePage(string $pid, ?string $content = null): array
    {
        $pid = cleanID($pid);
        $resolved = $this->resolveRulesOnly($pid);

        if ($content === null) {
            $content = page_exists($pid) ? (string)rawWiki($pid) : '';
        }

        if (self::contentHasNoRevisionControl($content)) {
            $resolved['controlled'] = false;
            $resolved['excluded_by_directive'] = true;
        }

        return $resolved;
    }

    /**
     * The directive must appear by itself on a line. This avoids treating ordinary
     * prose such as documentation mentioning the directive as an opt-out.
     */
    public static function contentHasNoRevisionControl(string $content): bool
    {
        return preg_match('/^[ \t]*~~NOREVISIONCONTROL~~[ \t]*$/mi', $content) === 1;
    }

    public function pageHasNoRevisionControl(string $pid): bool
    {
        $pid = cleanID($pid);
        if (!page_exists($pid)) return false;
        return self::contentHasNoRevisionControl((string)rawWiki($pid));
    }

    /** Materialize current resolved assignment for quick runtime checks. */
    public function materializePage(string $pid, ?string $content = null): array
    {
        $pid = cleanID($pid);
        $resolved = $this->resolvePage($pid, $content);
        $this->sqlite->query(
            'REPLACE INTO struct_docapproval_pages (pid, controlled, reviewer, training, publisher, resolved_at) VALUES (?,?,?,?,?,?)',
            [
                $pid,
                $resolved['controlled'] ? 1 : 0,
                $resolved[Constants::ROLE_REVIEWER],
                $resolved[Constants::ROLE_TRAINING],
                $resolved[Constants::ROLE_PUBLISHER],
                time(),
            ]
        );
        return $resolved;
    }

    public function getMaterialized(string $pid): ?array
    {
        $rows = $this->sqlite->queryAll('SELECT * FROM struct_docapproval_pages WHERE pid=? LIMIT 1', [cleanID($pid)]) ?: [];
        return $rows ? $rows[0] : null;
    }

    public function isControlled(string $pid): bool
    {
        $row = $this->getMaterialized($pid);
        return $row ? (bool)$row['controlled'] : false;
    }

    public function getRoleSpec(string $pid, string $role): string
    {
        if (!in_array($role, [Constants::ROLE_REVIEWER, Constants::ROLE_TRAINING, Constants::ROLE_PUBLISHER], true)) return '';
        $row = $this->getMaterialized($pid);
        return $row && (bool)$row['controlled'] ? (string)$row[$role] : '';
    }

    public function userHasRole(string $pid, string $role, string $userId = '', array $groups = []): bool
    {
        global $INPUT, $USERINFO;
        if ($userId === '') {
            $userId = $INPUT->server->str('REMOTE_USER');
            $groups = $USERINFO['grps'] ?? [];
        }
        $spec = $this->getRoleSpec($pid, $role);
        return $this->userMatchesSpec($spec, $userId, $groups);
    }

    public function userHasAnyRole(string $pid, string $userId = '', array $groups = []): bool
    {
        foreach ([Constants::ROLE_REVIEWER, Constants::ROLE_TRAINING, Constants::ROLE_PUBLISHER] as $role) {
            if ($this->userHasRole($pid, $role, $userId, $groups)) return true;
        }
        return false;
    }

    public function splitSpec(string $spec): array
    {
        $parts = preg_split('/\s*,\s*/', trim($spec), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($parts ?: []));
    }

    public function userMatchesSpec(string $spec, string $userId = '', array $groups = []): bool
    {
        global $INPUT, $USERINFO;

        if ($userId === '') {
            $userId = $INPUT->server->str('REMOTE_USER');
            $groups = $USERINFO['grps'] ?? [];
        }
        if ($userId === '' || trim($spec) === '') return false;

        foreach ($this->splitSpec($spec) as $token) {
            if (auth_isMember($token, $userId, $groups)) return true;
        }
        return false;
    }

    /**
     * Page-level revision-control opt-out is a privileged document-control action.
     *
     * Administrators may always manage it. Otherwise the current materialized
     * Publisher assignment is preferred; if the page is currently uncontrolled,
     * use the Publisher that the central rules would assign without the directive.
     */
    public function canManageNoRevisionControl(string $pid, string $userId = '', array $groups = []): bool
    {
        if (auth_isadmin()) return true;

        global $INPUT, $USERINFO;
        if ($userId === '') {
            $userId = $INPUT->server->str('REMOTE_USER');
            $groups = $USERINFO['grps'] ?? [];
        }

        $pid = cleanID($pid);
        $row = $this->getMaterialized($pid);
        $spec = $row ? trim((string)($row[Constants::ROLE_PUBLISHER] ?? '')) : '';

        if ($spec === '') {
            $rules = $this->resolveRulesOnly($pid);
            $spec = trim((string)$rules[Constants::ROLE_PUBLISHER]);
        }

        return $this->userMatchesSpec($spec, $userId, $groups);
    }

    /**
     * Resolve users and @groups into actual DokuWiki user IDs.
     * Direct user IDs are retained even when the auth backend cannot enumerate groups.
     */
    public function resolveUsers(string $spec): array
    {
        global $auth;
        $out = [];
        foreach ($this->splitSpec($spec) as $token) {
            if ($token[0] !== '@') {
                $out[$token] = $token;
                continue;
            }
            if (!$auth || !$auth->canDo('getUsers')) continue;
            $users = $auth->retrieveUsers(0, 5000, ['grps' => substr($token, 1)]);
            foreach (($users ?: []) as $login => $data) {
                if (is_int($login) && isset($data['user'])) $login = $data['user'];
                if (is_string($login) && $login !== '') $out[$login] = $login;
            }
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($out);
    }

    public function getEligibleReviewers(string $pid): array
    {
        return $this->resolveUsers($this->getRoleSpec($pid, Constants::ROLE_REVIEWER));
    }

    public function getControlledPages(): array
    {
        $rows = $this->sqlite->queryAll('SELECT pid FROM struct_docapproval_pages WHERE controlled=1 ORDER BY pid') ?: [];
        return array_column($rows, 'pid');
    }

    public function getSqlite(): SQLiteDB
    {
        return $this->sqlite;
    }
}
