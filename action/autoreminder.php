<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

class action_plugin_structdocapproval_autoreminder extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'runDailySweep');
    }

    public function runDailySweep(Event $event): void
    {
        if (!$this->getConf('email_enable') || !$this->getConf('ack_auto_reminder_enable')) return;

        /** @var helper_plugin_structdocapproval_db $dbHelper */
        $dbHelper = plugin_load('helper', 'structdocapproval_db');
        $db = $dbHelper ? $dbHelper->getDB() : null;
        if (!$db) return;

        // Migration actions share this hook. Do not touch v6 tables until a previous
        // request has completed the migration; the next request will run the sweep.
        $version = (int)$db->queryValue(
            'SELECT val FROM opts WHERE opt=? LIMIT 1',
            ['dbversion_struct_docapproval']
        );
        if ($version < 6) return;

        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        /** @var helper_plugin_structdocapproval_notify $notify */
        $notify = plugin_load('helper', 'structdocapproval_notify');
        if (!$publication || !$notify) return;

        [$token, $runDate] = $publication->claimAutomaticReminderRun();
        if ($token === '') return; // another request already owns/completed today's sweep

        $intervalDays = max(1, (int)$this->getConf('ack_auto_reminder_interval_days'));
        $ccSpec = trim((string)$this->getConf('ack_reminder_cc'));
        $sentPublications = 0;
        $sentDeliveries = 0;
        $errors = [];

        try {
            foreach ($publication->getAutomaticReminderCandidates($intervalDays) as $candidate) {
                try {
                    $deliveries = $notify->sendAcknowledgementReminder(
                        (string)$candidate['pid'],
                        (int)$candidate['publication_revision'],
                        (array)$candidate['outstanding_users'],
                        $ccSpec
                    );
                    if (!$deliveries) continue;

                    $publication->recordReminder(
                        (string)$candidate['pid'],
                        (int)$candidate['publication_revision'],
                        $deliveries,
                        'auto',
                        'auto',
                        $ccSpec
                    );
                    $sentPublications++;
                    $sentDeliveries += count($deliveries);
                } catch (Throwable $e) {
                    $errors[] = (string)$candidate['pid'] . ': ' . $e->getMessage();
                }
            }

            $detail = sprintf(
                'publications=%d; deliveries=%d; errors=%d',
                $sentPublications,
                $sentDeliveries,
                count($errors)
            );
            if ($errors) $detail .= '; ' . implode(' | ', array_slice($errors, 0, 10));

            $publication->finishAutomaticReminderRun(
                $runDate,
                $token,
                $errors ? 'partial' : 'complete',
                $detail
            );
        } catch (Throwable $e) {
            $publication->finishAutomaticReminderRun(
                $runDate,
                $token,
                'failed',
                $e->getMessage()
            );
        }
    }
}
