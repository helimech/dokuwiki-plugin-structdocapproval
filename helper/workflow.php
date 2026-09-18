<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class helper_plugin_structdocapproval_workflow extends Plugin
{
    /** @var helper_plugin_structdocapproval_db */
    protected $dbHelper;

    public function __construct()
    {
        $this->dbHelper = plugin_load('helper', 'structdocapproval_db');
    }

    public function transition(string $pid, string $action, array $params = []): WorkflowRecord
    {
        global $INPUT;
        $pid = cleanID($pid);
        if (!$this->dbHelper->isControlled($pid)) {
            throw new RuntimeException('Page is not controlled by Struct DocApproval.');
        }

        $actor = $INPUT->server->str('REMOTE_USER');
        if ($actor === '') $actor = 'unknown';
        $current = WorkflowRecord::latest($pid);
        if (!$current) throw new RuntimeException('No workflow record exists for this page.');

        $revision = (int)@filemtime(wikiFN($pid));
        if (!$revision) $revision = (int)$current->get('revision');
        $next = $current->copyForRevision($revision)->stamp($action, $actor);
        $now = $next->get('datetime');

        switch ($action) {
            case Constants::ACTION_READY_FOR_REVIEW:
                $this->requireStatus($current, Constants::STATUS_DRAFT);
                if (!$this->dbHelper->canSubmit($pid)) throw new RuntimeException('You may not submit this page for review.');
                $eligible = Assignments::getInstance()->getEligibleReviewers($pid);
                if (!$eligible) throw new RuntimeException('No eligible reviewers are configured for this page.');
                $reviewer = trim((string)($params['reviewer'] ?? ''));
                if (count($eligible) === 1) $reviewer = $eligible[0];
                if ($reviewer === '' || !in_array($reviewer, $eligible, true)) {
                    throw new RuntimeException('Select an eligible reviewer.');
                }
                $next->set('status', Constants::STATUS_AWAITING_REVIEW)
                    ->set('submitted_by', $actor)
                    ->set('submitted_at', $now)
                    ->set('assigned_reviewer', $reviewer)
                    ->clear(['reviewed_by','reviewed_at','training_by','training_at','training_disposition','training_note','published_by','published_at','version','comment']);
                break;

            case Constants::ACTION_REVIEW_APPROVED:
                $this->requireStatus($current, Constants::STATUS_AWAITING_REVIEW);
                if (!$this->dbHelper->canReview($pid, (string)$current->get('assigned_reviewer'))) {
                    throw new RuntimeException('Only the assigned reviewer or an administrator may approve this review.');
                }
                $next->set('status', Constants::STATUS_AWAITING_TRAINING)
                    ->set('reviewed_by', $actor)
                    ->set('reviewed_at', $now)
                    ->set('comment', trim((string)($params['comment'] ?? '')))
                    ->clear(['training_by','training_at','training_disposition','training_note','published_by','published_at','version']);
                break;

            case Constants::ACTION_REVIEW_RETURNED:
                $this->requireStatus($current, Constants::STATUS_AWAITING_REVIEW);
                if (!$this->dbHelper->canReview($pid, (string)$current->get('assigned_reviewer'))) {
                    throw new RuntimeException('Only the assigned reviewer or an administrator may return this page.');
                }
                $comment = $this->requiredComment($params);
                $next->set('status', Constants::STATUS_DRAFT)
                    ->set('comment', $comment)
                    ->clear(['reviewed_by','reviewed_at','training_by','training_at','training_disposition','training_note','published_by','published_at','version']);
                break;

            case Constants::ACTION_TRAINING_REVIEWED:
                $this->requireStatus($current, Constants::STATUS_AWAITING_TRAINING);
                if (!$this->dbHelper->canTrain($pid)) throw new RuntimeException('You may not complete the training review for this page.');
                $disposition = trim((string)($params['training_disposition'] ?? ''));
                if (!in_array($disposition, Constants::trainingDispositions(), true)) {
                    throw new RuntimeException('Select a valid training disposition.');
                }
                $note = trim((string)($params['training_note'] ?? ''));
                if (Constants::trainingNoteRequired($disposition) && $note === '') {
                    throw new RuntimeException('A training note is required for this disposition.');
                }
                $next->set('status', Constants::STATUS_READY_TO_PUBLISH)
                    ->set('training_by', $actor)
                    ->set('training_at', $now)
                    ->set('training_disposition', $disposition)
                    ->set('training_note', $note)
                    ->set('comment', '')
                    ->clear(['published_by','published_at','version']);
                break;

            case Constants::ACTION_PUBLISH:
                $this->requireStatus($current, Constants::STATUS_READY_TO_PUBLISH);
                if (!$this->dbHelper->canPublish($pid)) throw new RuntimeException('You may not publish this page.');
                $version = Constants::formatVersion((string)($params['version'] ?? ''));
                if ($version === '') throw new RuntimeException('Enter the document version before publishing.');
                $next->set('status', Constants::STATUS_PUBLISHED)
                    ->set('version', $version)
                    ->set('published_by', $actor)
                    ->set('published_at', $now)
                    ->set('comment', '');
                break;

            case Constants::ACTION_RETURN_TRAINING:
                $this->requireStatus($current, Constants::STATUS_READY_TO_PUBLISH);
                if (!$this->dbHelper->canPublish($pid)) throw new RuntimeException('You may not return this page from publication.');
                $next->set('status', Constants::STATUS_AWAITING_TRAINING)
                    ->set('comment', $this->requiredComment($params))
                    ->clear(['training_by','training_at','training_disposition','training_note','published_by','published_at','version']);
                break;

            case Constants::ACTION_RETURN_DRAFT:
                $this->requireStatus($current, Constants::STATUS_READY_TO_PUBLISH);
                if (!$this->dbHelper->canPublish($pid)) throw new RuntimeException('You may not return this page from publication.');
                $next->set('status', Constants::STATUS_DRAFT)
                    ->set('comment', $this->requiredComment($params))
                    ->clear(['reviewed_by','reviewed_at','training_by','training_at','training_disposition','training_note','published_by','published_at','version']);
                break;

            case Constants::ACTION_ADMIN_OVERRIDE:
                if (!$this->getConf('admin_override_enable')) throw new RuntimeException('Administrator workflow override is disabled in the plugin configuration.');
                if (!auth_isadmin()) throw new RuntimeException('Only administrators may use workflow override.');
                $target = trim((string)($params['target_status'] ?? ''));
                if (!in_array($target, Constants::statuses(), true)) throw new RuntimeException('Invalid override target status.');
                $next->set('status', $target)->set('comment', $this->requiredComment($params));
                $this->applyOverrideClears($next, $target);
                if ($target === Constants::STATUS_PUBLISHED) {
                    $version = Constants::formatVersion((string)($params['version'] ?? $current->get('version')));
                    if ($version === '') throw new RuntimeException('A version is required when overriding to Published.');
                    $next->set('version', $version)->set('published_by', $actor)->set('published_at', $now);
                }
                break;

            default:
                throw new RuntimeException('Unknown workflow action.');
        }

        $next->save();
        if ($next->get('status') === Constants::STATUS_PUBLISHED) {
            /** @var helper_plugin_structdocapproval_publish $publish */
            $publish = plugin_load('helper', 'structdocapproval_publish');
            $publish->publishStructData($pid, (int)$next->get('revision'));
        }

        $eventData = ['pid' => $pid, 'action' => $action, 'record' => $next, 'previous' => $current];
        Event::createAndTrigger('STRUCTDOCAPPROVAL_TRANSITION', $eventData);
        return $next;
    }

    protected function requireStatus(WorkflowRecord $record, string $status): void
    {
        if ($record->get('status') !== $status) {
            throw new RuntimeException('This action is not valid from the current workflow stage.');
        }
    }

    protected function requiredComment(array $params): string
    {
        $comment = trim((string)($params['comment'] ?? ''));
        if ($comment === '') throw new RuntimeException('A comment is required when returning or overriding a page.');
        return $comment;
    }

    protected function applyOverrideClears(WorkflowRecord $record, string $target): void
    {
        if ($target === Constants::STATUS_DRAFT) {
            $record->clear(['reviewed_by','reviewed_at','training_by','training_at','training_disposition','training_note','published_by','published_at','version']);
        } elseif ($target === Constants::STATUS_AWAITING_REVIEW) {
            $record->clear(['reviewed_by','reviewed_at','training_by','training_at','training_disposition','training_note','published_by','published_at','version']);
        } elseif ($target === Constants::STATUS_AWAITING_TRAINING) {
            $record->clear(['training_by','training_at','training_disposition','training_note','published_by','published_at','version']);
        } elseif ($target === Constants::STATUS_READY_TO_PUBLISH) {
            $record->clear(['published_by','published_at','version']);
        }
    }
}
