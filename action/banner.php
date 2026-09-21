<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\plugin\structdocapproval\meta\Assignments;
use dokuwiki\plugin\structdocapproval\meta\Constants;
use dokuwiki\plugin\structdocapproval\meta\WorkflowRecord;

class action_plugin_structdocapproval_banner extends ActionPlugin
{
    /** @var helper_plugin_structdocapproval_db */
    protected $db;

    public function register(EventHandler $controller)
    {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'renderBanner');
    }

    public function renderBanner(Event $event): void
    {
        if ($event->data !== 'show') return;
        global $ID, $INFO, $REV;

        $this->db = plugin_load('helper', 'structdocapproval_db');
        if (!$this->db || !$this->db->isControlled($ID)) return;

        $latest = WorkflowRecord::latest($ID);
        if (!$latest) return;
        $shownRev = (int)($REV ?: $INFO['currentrev']);
        $shown = WorkflowRecord::latestForRevision($ID, $shownRev) ?: $latest;
        $isCurrent = !$REV && (int)$shown->get('revision') === (int)$latest->get('revision') &&
            $shown->getRid() === $latest->getRid();

        // Ordinary readers only need the controlled-document publication marker. Editors,
        // assigned workflow users, and administrators retain the full workflow banner.
        if ($shown->get('status') === Constants::STATUS_PUBLISHED && !$this->db->canViewWorking($ID)) {
            $this->renderReaderPublished($shown);
            return;
        }

        $compact = $this->getConf('compact_view') ? ' compact' : '';
        echo '<div class="plugin-structdocapproval-banner status-' . hsc($shown->get('status')) . $compact . '">';
        echo '<div class="docapproval-headline"><strong>' . hsc($this->getLang('headline_' . $shown->get('status'))) . '</strong></div>';

        $this->renderContext($ID, $shown);
        $this->renderStages($shown);

        if ($shown->get('comment')) {
            echo '<div class="docapproval-return-note"><strong>' . hsc($this->getLang('return_note')) . ':</strong> ' . hsc($shown->get('comment')) . '</div>';
        }

        if ($isCurrent) $this->renderActions($ID, $shown);
        echo '</div>';
    }

    protected function renderReaderPublished(WorkflowRecord $record): void
    {
        $version = Constants::formatVersion((string)$record->get('version'));
        if ($version === '') $version = $this->getLang('version_unset');
        $date = $this->formatDate((string)($record->get('published_at') ?: $record->get('datetime')));

        $text = strtr($this->getLang('published_reader'), [
            '{version}' => '<span class="plugin-structdocapproval-version">' . hsc($version) . '</span>',
            '{date}' => hsc($date),
        ]);
        echo '<div class="plugin-structdocapproval-banner reader status-published">' . $text . '</div>';
    }

    protected function renderContext(string $pid, WorkflowRecord $record): void
    {
        $status = $record->get('status');
        if ($status === Constants::STATUS_PUBLISHED) {
            $version = Constants::formatVersion((string)$record->get('version'));
            $text = $this->getLang('published_context');
            $text = strtr($text, [
                '{version}' => $version !== '' ? hsc($version) : hsc($this->getLang('version_unset')),
                '{user}' => userlink($record->get('published_by') ?: $record->get('actor')),
                '{date}' => hsc($this->formatDate($record->get('published_at') ?: $record->get('datetime'))),
            ]);
            echo '<div class="docapproval-context">' . $text . '</div>';
            return;
        }

        $previous = WorkflowRecord::latestPublished($pid, (int)$record->get('revision'));
        if ($previous) {
            $version = Constants::formatVersion((string)$previous->get('version')) ?: $this->getLang('version_unset');
            echo '<div class="docapproval-context">' . sprintf(
                $this->getLang('previous_published'),
                hsc($version),
                userlink($previous->get('published_by') ?: $previous->get('actor')),
                hsc($this->formatDate($previous->get('published_at') ?: $previous->get('datetime')))
            );
            $diff = wl($pid, ['do' => 'diff', 'rev2[0]' => $previous->get('revision'), 'rev2[1]' => $record->get('revision')]);
            echo ' <a href="' . hsc($diff) . '">' . hsc($this->getLang('view_changes')) . '</a>';
            echo '</div>';
        } else {
            echo '<div class="docapproval-context">' . hsc($this->getLang('no_previous_published')) . '</div>';
        }
    }

    protected function renderStages(WorkflowRecord $r): void
    {
        $status = $r->get('status');
        $submissionDone = $status !== Constants::STATUS_DRAFT;
        $reviewDone = (string)$r->get('reviewed_by') !== '';
        $trainingDone = (string)$r->get('training_by') !== '';
        $published = $status === Constants::STATUS_PUBLISHED;

        echo '<div class="docapproval-stages">';
        $this->stage(
            $submissionDone ? $this->getLang('stage_submitted') : $this->getLang('stage_draft'),
            $submissionDone ? 'done' : 'active',
            $submissionDone ? $r->get('submitted_by') : $r->get('actor'),
            $submissionDone ? $r->get('submitted_at') : $r->get('datetime')
        );
        $this->stage(
            $this->getLang('stage_review'),
            $reviewDone ? 'done' : ($status === Constants::STATUS_AWAITING_REVIEW ? 'active' : 'pending'),
            $reviewDone ? $r->get('reviewed_by') : ($status === Constants::STATUS_AWAITING_REVIEW ? $r->get('assigned_reviewer') : ''),
            $reviewDone ? $r->get('reviewed_at') : ''
        );
        $this->stage(
            $this->getLang('stage_training'),
            $trainingDone ? 'done' : ($status === Constants::STATUS_AWAITING_TRAINING ? 'active' : 'pending'),
            $trainingDone ? $r->get('training_by') : '',
            $trainingDone ? $r->get('training_at') : ''
        );
        $this->stage(
            $this->getLang('stage_publication'),
            $published ? 'done' : ($status === Constants::STATUS_READY_TO_PUBLISH ? 'active' : 'pending'),
            $published ? ($r->get('published_by') ?: $r->get('actor')) : '',
            $published ? ($r->get('published_at') ?: $r->get('datetime')) : ''
        );
        echo '</div>';

        if ($trainingDone && $r->get('training_disposition')) {
            echo '<div class="docapproval-training-summary"><strong>' . hsc($this->getLang('training_impact')) . ':</strong> ' .
                hsc($this->getLang('training_' . $r->get('training_disposition')));
            if ($r->get('training_note')) echo ' — ' . hsc($r->get('training_note'));
            echo '</div>';
        }
    }

    protected function stage(string $label, string $state, string $user = '', string $date = ''): void
    {
        $symbol = $state === 'done' ? '✓' : ($state === 'active' ? '●' : '○');
        echo '<div class="docapproval-stage ' . hsc($state) . '">';
        echo '<div class="stage-title"><span class="stage-symbol">' . $symbol . '</span> ' . hsc($label) . '</div>';
        if ($user !== '') echo '<div class="stage-user">' . userlink($user) . '</div>';
        if ($date !== '') echo '<div class="stage-date">' . hsc($this->formatDate($date)) . '</div>';
        elseif ($state === 'active') echo '<div class="stage-date">' . hsc($this->getLang('awaiting_action')) . '</div>';
        echo '</div>';
    }

    protected function renderActions(string $pid, WorkflowRecord $record): void
    {
        $status = $record->get('status');
        $assignments = Assignments::getInstance();
        echo '<div class="docapproval-actions">';

        if ($status === Constants::STATUS_DRAFT && $this->db->canSubmit($pid)) {
            $reviewers = $assignments->getEligibleReviewers($pid);
            if (!$reviewers) {
                echo '<p class="docapproval-warning">' . hsc($this->getLang('no_reviewer')) . '</p>';
            } else {
                echo $this->formStart();
                if (count($reviewers) > 1) {
                    echo '<label>' . hsc($this->getLang('reviewer')) . ' <select name="structdocapproval[reviewer]">';
                    foreach ($reviewers as $reviewer) echo '<option value="' . hsc($reviewer) . '">' . hsc(userlink($reviewer, true)) . '</option>';
                    echo '</select></label> ';
                } else {
                    echo '<input type="hidden" name="structdocapproval[reviewer]" value="' . hsc($reviewers[0]) . '" />';
                    echo '<span class="assigned-to">' . hsc($this->getLang('reviewer')) . ': ' . userlink($reviewers[0]) . '</span> ';
                }
                echo '<label>' . hsc($this->getLang('note_optional')) . ' <input type="text" class="edit docapproval-comment" name="structdocapproval[comment]" /></label> ';
                echo $this->actionButton(Constants::ACTION_READY_FOR_REVIEW);
                echo '</form>';
            }
        } elseif ($status === Constants::STATUS_AWAITING_REVIEW && $this->db->canReview($pid, (string)$record->get('assigned_reviewer'))) {
            echo $this->formStart();
            echo '<label>' . hsc($this->getLang('comment_optional_return_required')) . ' <input type="text" class="edit docapproval-comment" name="structdocapproval[comment]" /></label> ';
            echo $this->actionButton(Constants::ACTION_REVIEW_APPROVED);
            echo $this->actionButton(Constants::ACTION_REVIEW_RETURNED, 'danger');
            echo '</form>';
        } elseif ($status === Constants::STATUS_AWAITING_TRAINING && $this->db->canTrain($pid)) {
            echo $this->formStart();
            echo '<label>' . hsc($this->getLang('training_impact')) . ' <select name="structdocapproval[training_disposition]">';
            foreach (Constants::trainingDispositions() as $d) {
                echo '<option value="' . hsc($d) . '">' . hsc($this->getLang('training_' . $d)) . '</option>';
            }
            echo '</select></label> ';
            echo '<label>' . hsc($this->getLang('training_note')) . ' <input type="text" class="edit docapproval-note" name="structdocapproval[training_note]" /></label> ';
            echo '<label>' . hsc($this->getLang('note_optional')) . ' <input type="text" class="edit docapproval-comment" name="structdocapproval[comment]" /></label> ';
            echo $this->actionButton(Constants::ACTION_TRAINING_REVIEWED);
            echo '</form>';
        } elseif ($status === Constants::STATUS_READY_TO_PUBLISH && $this->db->canPublish($pid)) {
            $previous = WorkflowRecord::latestPublished($pid, (int)$record->get('revision'));
            $suggested = $previous ? Constants::nextVersion((string)$previous->get('version')) : '001';
            echo $this->formStart();
            echo '<label>' . hsc($this->getLang('new_version')) . ' <input type="text" class="edit docapproval-version" name="structdocapproval[version]" value="' . hsc($suggested) . '" /></label> ';
            echo '<label>' . hsc($this->getLang('comment_optional_return_required')) . ' <input type="text" class="edit docapproval-comment" name="structdocapproval[comment]" /></label> ';
            echo $this->actionButton(Constants::ACTION_PUBLISH);
            echo $this->actionButton(Constants::ACTION_RETURN_TRAINING, 'secondary');
            echo $this->actionButton(Constants::ACTION_RETURN_DRAFT, 'danger');
            echo '</form>';
        }

        if ($this->getConf('admin_override_enable') && auth_isadmin()) {
            echo '<details class="docapproval-admin-override"><summary>' . hsc($this->getLang('admin_override')) . '</summary>';
            echo $this->formStart();
            echo '<label>' . hsc($this->getLang('override_target')) . ' <select name="structdocapproval[target_status]">';
            foreach (Constants::statuses() as $s) echo '<option value="' . hsc($s) . '">' . hsc($this->getLang('status_' . $s)) . '</option>';
            echo '</select></label> ';
            echo '<label>' . hsc($this->getLang('new_version')) . ' <input type="text" class="edit docapproval-version" name="structdocapproval[version]" /></label> ';
            echo '<label>' . hsc($this->getLang('override_reason')) . ' <input type="text" class="edit docapproval-comment" name="structdocapproval[comment]" /></label> ';
            echo $this->actionButton(Constants::ACTION_ADMIN_OVERRIDE, 'danger');
            echo '</form></details>';
        }
        echo '</div>';
    }

    protected function formStart(): string
    {
        return '<form class="docapproval-action-form" method="post">' .
            '<input type="hidden" name="sectok" value="' . hsc(getSecurityToken()) . '" />';
    }

    protected function actionButton(string $action, string $class = ''): string
    {
        $label = $this->getLang('action_' . $action);
        return '<button type="submit" class="' . hsc($class) . '" name="structdocapproval[action]" value="' . hsc($action) . '">' . hsc($label) . '</button> ';
    }

    protected function formatDate(string $iso): string
    {
        if ($iso === '') return '';
        $ts = strtotime($iso);
        return $ts ? dformat($ts) : $iso;
    }

}
