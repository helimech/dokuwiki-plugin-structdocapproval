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

        $pid = $record->getPid();
        $assignments = Assignments::getInstance();

        // Publication has two audiences: informational Notify recipients and people
        // who must Acknowledge the newly Published revision. Acknowledge implies an
        // email; recipients present in both lists receive only the action-required mail.
        if ($action === Constants::ACTION_PUBLISH) {
            $this->sendPublicationAudience($record);
            return;
        }

        $specs = [];
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

    protected function sendPublicationAudience(WorkflowRecord $record): void
    {
        $pid = $record->getPid();
        $publicationRevision = (int)$record->get('revision');

        /** @var helper_plugin_structdocapproval_publication $publication */
        $publication = plugin_load('helper', 'structdocapproval_publication');
        $snapshot = $publication ? $publication->getSnapshot($pid, $publicationRevision) : null;

        // Acknowledge email recipients come from the exact user snapshot created at
        // publication time, not from current group membership.
        $requiredUsers = $publication
            ? $publication->getRequiredUsers($pid, $publicationRevision)
            : [];
        $ackRecipients = $this->resolveRecipients($requiredUsers, false);

        $notifySpecs = [];
        if ($snapshot && trim((string)$snapshot['notify_spec']) !== '') {
            $notifySpecs[] = (string)$snapshot['notify_spec'];
        }
        if (trim((string)$this->getConf('publish_notify')) !== '') {
            $notifySpecs[] = (string)$this->getConf('publish_notify');
        }
        $notifyRecipients = $this->resolveRecipients($notifySpecs, true);

        $ackKeys = [];
        foreach ($ackRecipients as $email) $ackKeys[strtolower($email)] = true;
        $notifyRecipients = array_values(array_filter($notifyRecipients, static function ($email) use ($ackKeys) {
            return !isset($ackKeys[strtolower($email)]);
        }));

        if ($ackRecipients) {
            $this->sendPublicationMail($record, $ackRecipients, true);
        }
        if ($notifyRecipients) {
            $this->sendPublicationMail($record, $notifyRecipients, false);
        }
    }

    protected function sendPublicationMail(WorkflowRecord $record, array $recipients, bool $ackRequired): void
    {
        $pid = $record->getPid();
        $title = trim((string)p_get_first_heading($pid));
        if ($title === '') $title = $pid;
        $version = Constants::formatVersion((string)$record->get('version'));
        if ($version === '') $version = $this->getLang('version_unset');
        $publishedAt = $this->formatDate((string)($record->get('published_at') ?: $record->get('datetime')));
        $pageUrl = wl($pid, '', true, '&');

        $subject = sprintf(
            $ackRequired ? $this->getLang('email_ack_subject') : $this->getLang('email_notify_subject'),
            $title,
            $version
        );
        $intro = $ackRequired ? $this->getLang('email_ack_intro') : $this->getLang('email_notify_intro');

        $text = [
            $intro,
            '',
            $this->textField($this->getLang('email_page'), $title),
            $this->textField($this->getLang('version'), $version),
            $this->textField($this->getLang('ack_published'), $publishedAt),
            $this->textField($this->getLang('email_view_page'), $pageUrl),
            '',
            $ackRequired ? $this->getLang('email_ack_instruction') : $this->getLang('email_notify_no_action'),
        ];

        if (trim((string)$record->get('comment')) !== '') {
            $text[] = '';
            $text[] = $this->textField($this->getLang('email_comment'), (string)$record->get('comment'));
        }

        $html = '<p>' . hsc($intro) . '</p><p>' .
            $this->htmlField($this->getLang('email_page'), $title) .
            $this->htmlField($this->getLang('version'), $version) .
            $this->htmlField($this->getLang('ack_published'), $publishedAt) .
            '<strong>' . hsc($this->getLang('email_view_page')) . ':</strong>&nbsp;&nbsp;' .
            '<a href="' . hsc($pageUrl) . '">' . hsc($pageUrl) . '</a></p>';

        $html .= '<p><strong>' . hsc(
            $ackRequired ? $this->getLang('email_ack_instruction') : $this->getLang('email_notify_no_action')
        ) . '</strong></p>';

        if (trim((string)$record->get('comment')) !== '') {
            $html .= '<p>' . $this->htmlField($this->getLang('email_comment'), (string)$record->get('comment')) . '</p>';
        }

        $mailer = new Mailer();
        $mailer->bcc($recipients);
        $mailer->subject($subject);
        $mailer->setBody(implode("\n", $text) . "\n", null, null, $html);
        if (!$mailer->send()) {
            throw new RuntimeException('DokuWiki Mailer reported that the publication message was not sent.');
        }
    }

    public function resolveRecipients(array $specs, bool $allowLiteralEmails = true): array
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
                continue;
            }

            // Prefer a real DokuWiki account when one exists, even if its login
            // happens to look like an email address.
            global $auth;
            $userData = $auth ? $auth->getUserData($token) : false;
            if ($userData) {
                if (!empty($userData['mail'])) $emails[$userData['mail']] = true;
                continue;
            }

            if (strpos($token, '@') !== false && $allowLiteralEmails) {
                $emails[$token] = true;
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
            $this->textField($this->getLang('email_page'), $title) .
                '    ' . $this->textField($this->getLang('email_status'), $statusLabel),
            $this->textField($this->getLang('email_view_page'), $pageUrl),
            '',
            $this->textField($this->getLang('email_action'), $actionLabel),
            $this->textField($this->getLang('email_actor'), $actorName),
        ];

        $html = '<p>' . hsc($this->getLang('email_intro')) . '</p>';
        $html .= '<p>';
        $html .= $this->htmlFieldInline($this->getLang('email_page'), $title);
        $html .= '&nbsp;&nbsp;&nbsp;&nbsp;';
        $html .= $this->htmlFieldInline($this->getLang('email_status'), $statusLabel);
        $html .= '<br>';
        $html .= '<strong>' . hsc($this->getLang('email_view_page')) . ':</strong>&nbsp;&nbsp;' .
            '<a href="' . hsc($pageUrl) . '">' . hsc($pageUrl) . '</a>';
        $html .= '</p>';

        $html .= '<p>';
        $html .= $this->htmlField($this->getLang('email_action'), $actionLabel);
        $html .= $this->htmlField($this->getLang('email_actor'), $actorName);

        if (trim((string)$record->get('comment')) !== '') {
            $note = (string)$record->get('comment');
            $text[] = $this->textField($this->getLang('email_comment'), $note);
            $html .= $this->htmlField($this->getLang('email_comment'), $note);
        }
        if (trim((string)$record->get('training_disposition')) !== '') {
            $training = $this->getLang('training_' . $record->get('training_disposition'));
            $text[] = $this->textField($this->getLang('email_training'), $training);
            $html .= $this->htmlField($this->getLang('email_training'), $training);
        }
        if (trim((string)$record->get('training_note')) !== '') {
            $trainingNote = (string)$record->get('training_note');
            $text[] = $this->textField($this->getLang('email_training_note'), $trainingNote);
            $html .= $this->htmlField($this->getLang('email_training_note'), $trainingNote);
        }
        $html .= '</p>';

        $previous = WorkflowRecord::latestPublished($pid, (int)$record->get('revision'));
        if ($previous) {
            $diff = wl($pid, [
                'do' => 'diff',
                'rev2[0]' => $previous->get('revision'),
                'rev2[1]' => $record->get('revision'),
            ], true, '&');

            $text[] = '';
            $text[] = $this->getLang('email_view_diff') . ':';
            $text[] = $diff;

            $html .= '<p><strong>' . hsc($this->getLang('email_view_diff')) . ':</strong><br>' .
                '<a href="' . hsc($diff) . '">' . hsc($diff) . '</a></p>';
        }

        $text[] = '';
        $text[] = $this->getLang('email_closing');
        $html .= '<p><em>' . hsc($this->getLang('email_closing')) . '</em></p>';

        return ['text' => implode("\n", $text) . "\n", 'html' => $html];
    }

    protected function htmlField(string $label, string $value): string
    {
        return '<strong>' . hsc($label) . ':</strong>&nbsp;&nbsp;' . hsc($value) . '<br>';
    }

    protected function htmlFieldInline(string $label, string $value): string
    {
        return '<strong>' . hsc($label) . ':</strong>&nbsp;&nbsp;' . hsc($value);
    }

    protected function textField(string $label, string $value): string
    {
        return $label . ':  ' . $value;
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

    protected function formatDate(string $iso): string
    {
        if ($iso === '') return '';
        $ts = strtotime($iso);
        return $ts ? dformat($ts) : $iso;
    }
}
