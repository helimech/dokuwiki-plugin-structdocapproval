<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_structdocapproval_workflow extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleAction');
    }

    public function handleAction(Event $event): void
    {
        if ($event->data !== 'show') return;
        global $INPUT, $ID;
        $in = $INPUT->arr('structdocapproval');
        $action = trim((string)($in['action'] ?? ''));
        if ($action === '') return;
        if (!checkSecurityToken()) return;

        try {
            /** @var helper_plugin_structdocapproval_workflow $workflow */
            $workflow = plugin_load('helper', 'structdocapproval_workflow');
            $record = $workflow->transition($ID, $action, $in);
            msg($this->getLang('transition_saved'), 1);
        } catch (Throwable $e) {
            msg($e->getMessage(), -1);
            send_redirect(wl($ID, '', true, '&'));
            return;
        }

        // Notification failure must never make a successful workflow transition look as
        // though it failed. The audit record is authoritative; email is a follow-up service.
        try {
            /** @var helper_plugin_structdocapproval_notify $notify */
            $notify = plugin_load('helper', 'structdocapproval_notify');
            if ($notify) $notify->sendForTransition($action, $record);
        } catch (Throwable $e) {
            msg(sprintf($this->getLang('notification_failed'), $e->getMessage()), 0);
        }

        send_redirect(wl($ID, '', true, '&'));
    }
}
