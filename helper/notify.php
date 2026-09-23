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
        $body = $this->buildBody($action, $record);
        $mailer->setBody($body['text'], null, null, $body['html']);
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

    protected function buildBody(string $action, WorkflowRecord $record): array
    {
        $pid = $record->getPid();
        $title = trim((string)p_get_first_heading($pid));
        if ($title === '') $title = $pid;

        $actionLabel = $this->getLang('action_' . $action);
        $statusLabel = $this->getLang('status_' . $record->get('status'));
        $actorName = $this->displayName((string)$record->get('actor'));
        $pageUrl = wl($pid, '', true, '&');

        $text = [
            $this->getLang('email_intro'),
            '',
            $this->getLang('email_page') . ': ' . $title,
            $this->getLang('email_action') . ': ' . $actionLabel,
            $this->getLang('email_status') . ': ' . $statusLabel,
            $this->getLang('email_actor') . ': ' . $actorName,
        ];

        $html = '<p>' . hsc($this->getLang('email_intro')) . '</p>';
        $html .= '<p>';
        $html .= $this->htmlField($this->getLang('email_page'), $title);
        $html .= $this->htmlField($this->getLang('email_action'), $actionLabel);
        $html .= $this->htmlField($this->getLang('email_status'), $statusLabel);
        $html .= $this->htmlField($this->getLang('email_actor'), $actorName);

        if (trim((string)$record->get('comment')) !== '') {
            $note = (string)$record->get('comment');
            $text[] = $this->getLang('email_comment') . ': ' . $note;
            $html .= $this->htmlField($this->getLang('email_comment'), $note);
        }
        if (trim((string)$record->get('training_disposition')) !== '') {
            $training = $this->getLang('training_' . $record->get('training_disposition'));
            $text[] = $this->getLang('email_training') . ': ' . $training;
            $html .= $this->htmlField($this->getLang('email_training'), $training);
        }
        if (trim((string)$record->get('training_note')) !== '') {
            $trainingNote = (string)$record->get('training_note');
            $text[] = $this->getLang('email_training_note') . ': ' . $trainingNote;
            $html .= $this->htmlField($this->getLang('email_training_note'), $trainingNote);
        }
        $html .= '</p>';

        $text[] = '';
        $text[] = $this->getLang('email_view_page') . ': ' . $pageUrl;

        $html .= '<p><strong>' . hsc($this->getLang('email_view_page')) . ':</strong> ' .
            '<a href="' . hsc($pageUrl) . '">' . hsc($pageUrl) . '</a><br>';

        $previous = WorkflowRecord::latestPublished($pid, (int)$record->get('revision'));
        if ($previous) {
            $diff = wl($pid, [
                'do' => 'diff',
                'rev2[0]' => $previous->get('revision'),
                'rev2[1]' => $record->get('revision'),
            ], true, '&');
            $text[] = $this->getLang('email_view_diff') . ': ' . $diff;
            $html .= '<strong>' . hsc($this->getLang('email_view_diff')) . ':</strong> ' .
                '<a href="' . hsc($diff) . '">' . hsc($diff) . '</a><br>';
        }
        $html .= '</p>';

        $text[] = '';
        $text[] = $this->getLang('email_closing');
        $html .= '<p><em>' . hsc($this->getLang('email_closing')) . '</em></p>';

        return [
            'text' => implode("\n", $text) . "\n",
            'html' => $html,
        ];
    }

    protected function htmlField(string $label, string $value): string
    {
        return '<strong>' . hsc($label) . ':</strong> ' . hsc($value) . '<br>';
    }

    protected function displayName(string $user): string
    {
        if ($user === '') return '';

        /** @var AuthPlugin $auth */
        global $auth;
        if ($auth) {
            $data = $auth->getUserData($user);
            if ($data && !empty($data['name'])) return (string)$data['name'];
        }

        return $user;
    }

}
