<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class action_plugin_structdocapproval_changesummary extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleSave');
    }

    public function handleSave(Event $event): void
    {
        if ($event->data !== 'show') return;

        global $INPUT, $ID;
        $in = $INPUT->arr('structdocapproval_summary');
        if (($in['action'] ?? '') !== 'save') return;
        if (!checkSecurityToken()) return;

        try {
            /** @var helper_plugin_structdocapproval_db $db */
            $db = plugin_load('helper', 'structdocapproval_db');
            if (!$db || !$db->isControlled($ID)) {
                throw new RuntimeException('Page is not controlled by Struct DocApproval.');
            }

            $current = WorkflowRecord::latest($ID);
            if (!$current) throw new RuntimeException('No workflow record exists for this page.');
            if ($current->get('status') === Constants::STATUS_PUBLISHED) {
                throw new RuntimeException('A Published change summary is fixed. Edit the page to begin a new Draft cycle.');
            }

            if (!$db->canEditChangeSummary(
                $ID,
                (string)$current->get('status'),
                (string)$current->get('assigned_reviewer')
            )) {
                throw new RuntimeException('You may not edit the change summary at this workflow stage.');
            }

            $postedRevision = (int)($in['working_revision'] ?? 0);
            $currentRevision = (int)$current->get('revision');
            if (!$postedRevision || $postedRevision !== $currentRevision) {
                throw new RuntimeException('The working revision changed. Reload the page before saving the change summary.');
            }

            $actor = $INPUT->server->str('REMOTE_USER');
            if ($actor === '') $actor = 'unknown';

            /** @var helper_plugin_structdocapproval_changesummary $summary */
            $summary = plugin_load('helper', 'structdocapproval_changesummary');
            if (!$summary) throw new RuntimeException('Change summary helper is unavailable.');

            $summary->save(
                $ID,
                (string)($in['summary'] ?? ''),
                $actor,
                $currentRevision
            );
            msg($this->getLang('change_summary_saved'), 1);
        } catch (Throwable $e) {
            msg($e->getMessage(), -1);
        }

        send_redirect(wl($ID, '', true, '&'));
    }
}
