<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class action_plugin_structdocapproval_workflowreminder extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleReminder');
    }

    public function handleReminder(Event $event): void
    {
        if ($event->data !== 'show') return;

        global $INPUT, $ID;
        $in = $INPUT->arr('structdocapproval_workflow_reminder');
        if (($in['action'] ?? '') !== 'send') return;
        if (!checkSecurityToken()) return;

        $redirectPage = cleanID((string)($in['return_page'] ?? $ID));
        if ($redirectPage === '') $redirectPage = $ID;
        $redirect = wl($redirectPage, ['docapproval_dash_view' => 'documents'], true, '&');

        if (!auth_ismanager()) {
            msg($this->getLang('workflow_reminder_denied'), -1);
            send_redirect($redirect);
            return;
        }

        $pid = cleanID((string)($in['pid'] ?? ''));
        $status = trim((string)($in['status'] ?? ''));
        $allowed = [
            Constants::STATUS_AWAITING_REVIEW,
            Constants::STATUS_AWAITING_TRAINING,
            Constants::STATUS_READY_TO_PUBLISH,
        ];

        try {
            if ($pid === '' || !in_array($status, $allowed, true)) {
                throw new RuntimeException($this->getLang('workflow_reminder_invalid'));
            }
            if (auth_quickaclcheck($pid) < AUTH_READ) {
                throw new RuntimeException($this->getLang('workflow_reminder_denied'));
            }

            /** @var helper_plugin_structdocapproval_db $dbHelper */
            $dbHelper = plugin_load('helper', 'structdocapproval_db');
            if (!$dbHelper || !$dbHelper->isControlled($pid)) {
                throw new RuntimeException($this->getLang('workflow_reminder_stale'));
            }

            $record = WorkflowRecord::latest($pid);
            if (!$record || (string)$record->get('status') !== $status) {
                throw new RuntimeException($this->getLang('workflow_reminder_stale'));
            }

            /** @var helper_plugin_structdocapproval_notify $notify */
            $notify = plugin_load('helper', 'structdocapproval_notify');
            if (!$notify) throw new RuntimeException('Notification helper is unavailable.');

            $count = $notify->sendWorkflowStatusReminder($pid, $status);
            msg(sprintf($this->getLang('workflow_reminder_sent'), $count), 1);
        } catch (Throwable $e) {
            msg($e->getMessage(), -1);
        }

        send_redirect($redirect);
    }
}
