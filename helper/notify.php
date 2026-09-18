<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\Extension\AuthPlugin;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class helper_plugin_structdocapproval_notify extends Plugin
{
    public function sendForTransition(string $action, WorkflowRecord $record): void
    {
        if (!$this->getConf('email_enable')) return;

        $specs = [];
        $pid = $record->getPid();
        $assignments = Assignments::getInstance();

        switch ($action) {
            case Constants::ACTION_READY_FOR_REVIEW:
                if ($record->get('assigned_reviewer')) $specs[] = (string)$record->get('assigned_reviewer');
                if (trim((string)$this->getConf('review_queue_cc')) !== '') $specs[] = (string)$this->getConf('review_queue_cc');
                break;
            case Constants::ACTION_REVIEW_RETURNED:
            case Constants::ACTION_RETURN_DRAFT:
                if ($record->get('submitted_by')) $specs[] = (string)$record->get('submitted_by');
                break;
            case Constants::ACTION_REVIEW_APPROVED:
            case Constants::ACTION_RETURN_TRAINING:
                $specs[] = $assignments->getRoleSpec($pid, Constants::ROLE_TRAINING);
                break;
            case Constants::ACTION_TRAINING_REVIEWED:
                $specs[] = $assignments->getRoleSpec($pid, Constants::ROLE_PUBLISHER);
                break;
            case Constants::ACTION_PUBLISH:
                if (trim((string)$this->getConf('publish_notify')) !== '') $specs[] = (string)$this->getConf('publish_notify');
                break;
            default:
                return;
        }

        $recipients = $this->resolveRecipients($specs);
        if (!$recipients) return;

        $mailer = new Mailer();
        $mailer->bcc($recipients);
        $mailer->subject(sprintf($this->getLang('email_subject'), $this->getLang('action_' . $action), $pid));
        $mailer->setBody($this->buildBody($action, $record));
        if (!$mailer->send()) throw new RuntimeException('DokuWiki Mailer reported that the message was not sent.');
    }

    public function resolveRecipients(array $specs): array
    {
        $tokens = [];
        foreach ($specs as $spec) {
            foreach (preg_split('/\s*,\s*/', trim((string)$spec), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $tokens[$token] = true;
            }
        }

        $emails = [];
        foreach (array_keys($tokens) as $token) {
            if ($token === '') continue;
            if ($token[0] === '@') {
                $this->resolveGroup($emails, substr($token, 1));
            } elseif (strpos($token, '@') !== false) {
                $emails[$token] = true;
            } else {
                $this->resolveUser($emails, $token);
            }
        }
        return array_keys($emails);
    }

    protected function resolveGroup(array &$emails, string $group): void
    {
        /** @var AuthPlugin $auth */
        global $auth;
        if (!$auth || !$auth->canDo('getUsers')) return;
        $users = $auth->retrieveUsers(0, 5000, ['grps' => $group]);
        foreach (($users ?: []) as $user) {
            if (!empty($user['mail'])) $emails[$user['mail']] = true;
        }
    }

    protected function resolveUser(array &$emails, string $user): void
    {
        /** @var AuthPlugin $auth */
        global $auth;
        if (!$auth) return;
        $data = $auth->getUserData($user);
        if ($data && !empty($data['mail'])) $emails[$data['mail']] = true;
    }

    protected function buildBody(string $action, WorkflowRecord $record): string
    {
        $pid = $record->getPid();
        $lines = [
            $this->getLang('email_intro'),
            '',
            $this->getLang('email_page') . ': ' . $pid,
            $this->getLang('email_action') . ': ' . $this->getLang('action_' . $action),
            $this->getLang('email_status') . ': ' . $this->getLang('status_' . $record->get('status')),
            $this->getLang('email_actor') . ': ' . $record->get('actor'),
        ];
        if (trim((string)$record->get('comment')) !== '') {
            $lines[] = $this->getLang('email_comment') . ': ' . $record->get('comment');
        }
        if (trim((string)$record->get('training_disposition')) !== '') {
            $lines[] = $this->getLang('email_training') . ': ' . $this->getLang('training_' . $record->get('training_disposition'));
        }
        if (trim((string)$record->get('training_note')) !== '') {
            $lines[] = $this->getLang('email_training_note') . ': ' . $record->get('training_note');
        }

        $lines[] = '';
        $lines[] = $this->getLang('email_view_page') . ': ' . wl($pid, '', true, '&');

        $previous = WorkflowRecord::latestPublished($pid, (int)$record->get('revision'));
        if ($previous) {
            $diff = wl($pid, [
                'do' => 'diff',
                'rev2[0]' => $previous->get('revision'),
                'rev2[1]' => $record->get('revision'),
            ], true, '&');
            $lines[] = $this->getLang('email_view_diff') . ': ' . $diff;
        }
        return implode("\n", $lines) . "\n";
    }
}
