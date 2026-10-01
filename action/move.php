<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class action_plugin_structdocapproval_move extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        // Run before Move's own AJAX handlers so an active workflow cannot be moved
        // into an uncontrolled destination. Sequence ordering is supported by current
        // DokuWiki and keeps this independent of plugin load order.
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'validateMoveAjax', null, PHP_INT_MIN);

        // Run after Struct's Move integration so workflow history has already followed
        // the page before we recalculate the destination assignment.
        $controller->register_hook('PLUGIN_MOVE_PAGE_RENAME', 'AFTER', $this, 'handleMove', null, PHP_INT_MAX);
    }

    /**
     * Block moves that would allow an active controlled workflow to escape control.
     *
     * This handles both the normal single-page rename dialog and Move's progress
     * runner used for planned/tree/namespace moves.
     */
    public function validateMoveAjax(Event $event): void
    {
        global $INPUT, $conf;

        if ($event->data === 'plugin_move_rename') {
            $old = cleanID($INPUT->str('id'));
            $new = cleanID($INPUT->str('newid'));
            if ($this->shouldBlockMove($old, $new)) {
                $this->sendMoveError($event, $old, $new);
            }
            return;
        }

        if ($event->data !== 'plugin_move_progress') return;

        // Planned/tree moves keep their pending page moves in this file. Inspect the
        // complete list before Move executes the next batch. On the first progress call
        // this prevents a namespace move from becoming partially completed.
        $planFile = $conf['metadir'] . '/__move_pagelist';
        if (!is_file($planFile)) return;

        $lines = file($planFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $parts = explode("\t", trim($line), 2);
            if (count($parts) !== 2) continue;
            $old = cleanID($parts[0]);
            $new = cleanID($parts[1]);
            if (!$this->shouldBlockMove($old, $new)) continue;

            /** @var helper_plugin_move_plan $plan */
            $plan = plugin_load('helper', 'move_plan');
            if ($plan) $plan->abort();

            $this->sendMoveError($event, $old, $new, true);
            return;
        }
    }

    protected function sendMoveError(Event $event, string $old, string $new, bool $progress = false): void
    {
        $message = sprintf($this->getLang('move_blocked_active_uncontrolled'), $old, $new);

        $event->preventDefault();
        $event->stopPropagation();
        header('Content-Type: application/json');

        if ($progress) {
            echo json_encode([
                'error' => $message,
                'complete' => true,
                'progress' => 0,
            ]);
        } else {
            echo json_encode(['error' => $message]);
        }
    }

    protected function shouldBlockMove(string $old, string $new): bool
    {
        if ($old === '' || $new === '' || $old === $new) return false;

        $assignments = Assignments::getInstance(true);
        $source = $assignments->getMaterialized($old);
        if (!$source || !(bool)$source['controlled']) return false;

        $current = WorkflowRecord::latest($old);
        if (!$current || $current->get('status') === Constants::STATUS_PUBLISHED) return false;

        $destination = $this->resolveDestinationAfterMove($old, $new);
        return !$destination['controlled'];
    }

    /**
     * Resolve the destination as it will look after the move.
     *
     * Exact-page rules follow their page, so during the pre-move check we simulate
     * old exact patterns as though they already point at the new page ID. Namespace,
     * wildcard and regex rules are evaluated normally against the destination.
     */
    protected function resolveDestinationAfterMove(string $old, string $new): array
    {
        $old = cleanID($old);
        $new = cleanID($new);
        $assignments = Assignments::getInstance(true);

        /** @var helper_plugin_structdocapproval_assignments $matcher */
        $matcher = plugin_load('helper', 'structdocapproval_assignments');

        $resolved = [
            'controlled' => false,
            Constants::ROLE_REVIEWER => '',
            Constants::ROLE_TRAINING => '',
            Constants::ROLE_PUBLISHER => '',
        ];
        $pns = ':' . getNS($new) . ':';

        foreach ($assignments->getRules() as $rule) {
            $pattern = trim((string)$rule['pattern']);
            if ($this->isExactPagePattern($pattern) && cleanID($pattern) === $old) {
                $pattern = $new;
            }

            if (!$matcher->matchPagePattern($pattern, $new, $pns)) continue;

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

    protected function isExactPagePattern(string $pattern): bool
    {
        return $pattern !== '' && $pattern[0] !== '/' && substr($pattern, -1) !== '*';
    }

    public function handleMove(Event $event): void
    {
        $old = cleanID($event->data['src_id'] ?? '');
        $new = cleanID($event->data['dst_id'] ?? '');
        if ($old === '' || $new === '' || $old === $new) return;

        /** @var helper_plugin_structdocapproval_db $helper */
        $helper = plugin_load('helper', 'structdocapproval_db');
        $db = $helper ? $helper->getDB() : null;
        if (!$db) return;

        // Capture the source's materialized assignment before replacing it with the
        // destination's resolved assignment. Struct has already moved the permanent
        // workflow history to $new by the time this AFTER hook runs.
        $sourceRows = $db->queryAll(
            'SELECT controlled, reviewer, training, publisher, resolved_at FROM struct_docapproval_pages WHERE pid=? LIMIT 1',
            [$old]
        ) ?: [];
        $source = $sourceRows ? $sourceRows[0] : null;
        $wasControlled = $source && (bool)$source['controlled'];

        // Exact page rules follow a page rename. Wildcard and regex rules stay where
        // they are and naturally resolve according to the destination namespace.
        $rows = $db->queryAll('SELECT id,pattern FROM struct_docapproval_rules ORDER BY sort_order ASC, id ASC') ?: [];
        foreach ($rows as $row) {
            $pattern = trim((string)$row['pattern']);
            if (!$this->isExactPagePattern($pattern)) continue;
            if (cleanID($pattern) === $old) {
                $db->query('UPDATE struct_docapproval_rules SET pattern=? WHERE id=?', [$new, (int)$row['id']]);
            }
        }

        $assignments = Assignments::getInstance(true);
        $resolved = $assignments->resolvePage($new);
        $current = WorkflowRecord::latest($new);

        // Fail-safe for non-AJAX/API move paths: never allow an active workflow to
        // become uncontrolled after the move. Normal Move UI paths are blocked before
        // execution, but retaining control here prevents accidental exposure if another
        // caller invokes Move's operation helper directly.
        if (
            $wasControlled &&
            $current &&
            $current->get('status') !== Constants::STATUS_PUBLISHED &&
            !$resolved['controlled']
        ) {
            $db->query(
                'REPLACE INTO struct_docapproval_pages (pid, controlled, reviewer, training, publisher, resolved_at) VALUES (?,?,?,?,?,?)',
                [$new, 1, $source['reviewer'], $source['training'], $source['publisher'], time()]
            );
            $db->query('DELETE FROM struct_docapproval_pages WHERE pid=?', [$old]);
            msg(sprintf($this->getLang('move_safeguard_active_uncontrolled'), hsc($new)), -1);
            return;
        }

        // The destination rules are authoritative. This immediately changes the
        // current Reviewer/Training/Publisher routing when moving between controlled
        // namespaces, or disables control when a Published page moves outside control.
        $assignments->materializePage($new);
        $db->query('DELETE FROM struct_docapproval_pages WHERE pid=?', [$old]);

        // Moving an uncontrolled page into a controlled namespace is not a content
        // change. Initialize the live destination revision as the Published baseline,
        // matching normal "existing page enters control" reconciliation semantics.
        if ($resolved['controlled'] && !$wasControlled) {
            $this->initializePublishedBaselineIfNeeded($new);
        }
    }

    protected function initializePublishedBaselineIfNeeded(string $pid): void
    {
        global $INPUT;

        $rev = (int)@filemtime(wikiFN($pid));
        if (!$rev) return;

        $current = WorkflowRecord::latest($pid);
        if (
            $current &&
            $current->get('status') === Constants::STATUS_PUBLISHED &&
            (int)$current->get('revision') === $rev
        ) {
            return;
        }

        $actor = $INPUT->server->str('REMOTE_USER') ?: 'move';
        $now = date('Y-m-d\TH:i');

        $record = new WorkflowRecord($pid);
        $record->set('status', Constants::STATUS_PUBLISHED)
            ->set('action', Constants::ACTION_INITIALIZE)
            ->set('actor', $actor)
            ->set('datetime', $now)
            ->set('revision', $rev)
            ->set('published_by', $actor)
            ->set('published_at', $now);
        $record->save();

        /** @var helper_plugin_structdocapproval_publish $publish */
        $publish = plugin_load('helper', 'structdocapproval_publish');
        if ($publish) $publish->publishStructData($pid, $rev);
    }
}
