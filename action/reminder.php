<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_structdocapproval_reminder extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handleReminder');
    }

    public function handleReminder(Event $event): void
    {
        if ($event->data !== 'show') return;

        global $INPUT, $ID;
        $in = $INPUT->arr('structdocapproval_reminder');
        if (($in['action'] ?? '') !== 'send') return;
        if (!checkSecurityToken()) return;

        $redirect = $this->returnUrl($ID, $in);

        if (!auth_ismanager()) {
            msg($this->getLang('reminder_denied'), -1);
            send_redirect($redirect);
            return;
        }

        $pid = cleanID((string)($in['pid'] ?? ''));
        $publicationRevision = (int)($in['publication_revision'] ?? 0);

        try {
            if ($pid === '' || !$publicationRevision) {
                throw new RuntimeException('Invalid publication reminder request.');
            }
            if (auth_quickaclcheck($pid) < AUTH_READ) {
                throw new RuntimeException($this->getLang('reminder_denied'));
            }

            /** @var helper_plugin_structdocapproval_db $dbHelper */
            $dbHelper = plugin_load('helper', 'structdocapproval_db');
            if (!$dbHelper || !$dbHelper->isControlled($pid)) {
                throw new RuntimeException($this->getLang('reminder_not_current'));
            }

            /** @var helper_plugin_structdocapproval_publication $publication */
            $publication = plugin_load('helper', 'structdocapproval_publication');
            if (!$publication) throw new RuntimeException('Publication helper is unavailable.');

            if ($publication->getCurrentPublicationRevision($pid) !== $publicationRevision) {
                throw new RuntimeException($this->getLang('reminder_not_current'));
            }

            $compliance = $publication->getComplianceForPage($pid, $publicationRevision);
            $outstanding = $compliance ? (array)$compliance['outstanding_users'] : [];
            if (!$outstanding) {
                throw new RuntimeException($this->getLang('reminder_none_outstanding'));
            }

            /** @var helper_plugin_structdocapproval_notify $notify */
            $notify = plugin_load('helper', 'structdocapproval_notify');
            if (!$notify) throw new RuntimeException('Notification helper is unavailable.');

            $ccSpec = trim((string)$this->getConf('ack_reminder_cc'));
            $deliveries = $notify->sendAcknowledgementReminder(
                $pid,
                $publicationRevision,
                $outstanding,
                $ccSpec
            );

            $actor = $INPUT->server->str('REMOTE_USER');
            if ($actor === '') $actor = 'unknown';
            $publication->recordReminder(
                $pid,
                $publicationRevision,
                $deliveries,
                $actor,
                'manual',
                $ccSpec
            );

            msg(sprintf(
                $this->getLang('reminder_sent'),
                count($deliveries),
                count($outstanding)
            ), 1);
        } catch (Throwable $e) {
            msg($e->getMessage(), -1);
        }

        send_redirect($redirect);
    }

    protected function returnUrl(string $pageId, array $in): string
    {
        $params = [];

        if (!empty($in['return_dashboard'])) {
            $params['docapproval_dash_view'] = 'compliance';
        }

        $map = [
            'return_scope' => 'docapproval_comp_scope',
            'return_page' => 'docapproval_comp_page',
            'return_user' => 'docapproval_comp_user',
            'return_status' => 'docapproval_comp_status',
            'return_from' => 'docapproval_comp_from',
            'return_to' => 'docapproval_comp_to',
        ];
        foreach ($map as $source => $target) {
            $value = trim((string)($in[$source] ?? ''));
            if ($value !== '') $params[$target] = $value;
        }

        return wl($pageId, $params, true, '&');
    }
}
