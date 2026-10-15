<?php

$lang['menu'] = 'Struct DocApproval';
$lang['admin_intro'] = 'Ordered rules are processed from top to bottom. A + rule includes pages in the workflow and nonblank role fields override earlier values. A - rule excludes matching pages. Sync Rule saves that row and then recalculates affected existing pages.';

// workflow status
$lang['status_draft'] = 'Draft';
$lang['status_awaiting_review'] = 'Awaiting Review';
$lang['status_awaiting_training'] = 'Awaiting Training Review';
$lang['status_ready_to_publish'] = 'Ready to Publish';
$lang['status_published'] = 'Published';

$lang['headline_draft'] = 'Working Revision — Draft';
$lang['headline_awaiting_review'] = 'Working Revision — Awaiting Review';
$lang['headline_awaiting_training'] = 'Working Revision — Awaiting Training Review';
$lang['headline_ready_to_publish'] = 'Working Revision — Ready to Publish';
$lang['headline_published'] = 'Published Revision';

// actions
$lang['action_edit'] = 'Edited';
$lang['action_minor_edit'] = 'Minor Edit';
$lang['action_initialize'] = 'Initialized as Published';
$lang['action_ready_for_review'] = 'Ready for Review';
$lang['action_review_approved'] = 'Approve';
$lang['action_review_returned'] = 'Return for Changes';
$lang['action_training_reviewed'] = 'Complete Training Review';
$lang['action_publish'] = 'Publish';
$lang['action_return_training'] = 'Return to Training';
$lang['action_return_draft'] = 'Return to Draft';
$lang['action_admin_override'] = 'Admin Override';

// banner / stages
$lang['stage_draft'] = 'Draft';
$lang['stage_submitted'] = 'Submitted';
$lang['stage_review'] = 'Review';
$lang['stage_training'] = 'Training';
$lang['stage_publication'] = 'Publication';
$lang['awaiting_action'] = 'Awaiting action';
$lang['published_context'] = 'Published as version <span class="plugin-structdocapproval-version">{version}</span> by {user} on {date}.';
$lang['published_reader'] = 'Published Revision: {version} {date}';
$lang['previous_published'] = 'Previous published version %s by %s on %s.';
$lang['no_previous_published'] = 'No previous published revision exists. This page will remain unavailable to ordinary readers until it is published.';
$lang['view_changes'] = 'View changes since Published';
$lang['version_unset'] = 'initial';
$lang['return_note'] = 'Workflow note';
$lang['reviewer'] = 'Reviewer';
$lang['no_reviewer'] = 'No eligible reviewer is configured for this page.';
$lang['note_optional'] = 'Note (optional)';
$lang['comment_optional_return_required'] = 'Note (required when returning)';
$lang['return_comment'] = 'Note (required when returning)';
$lang['training_impact'] = 'Training Impact';
$lang['training_note'] = 'Training Note';
$lang['new_version'] = 'New Version';
$lang['release_audience'] = 'Release Audience';
$lang['notify_on_publish'] = 'Send publication notification';
$lang['require_acknowledgement'] = 'Require acknowledgement';
$lang['notify_publish_help'] = 'Informational only. Users, @groups, or literal email addresses; comma-separated.';
$lang['ack_publish_help'] = 'Action required. Authenticated users and @groups only; comma-separated. The resolved users are snapshotted with this publication.';
$lang['change_summary'] = 'Change Summary';
$lang['change_summary_help'] = 'Plain-language summary of what changed in this working revision.';
$lang['change_summary_placeholder'] = 'Briefly describe the changes in this revision…';
$lang['change_summary_empty'] = 'No change summary entered.';
$lang['save_change_summary'] = 'Save Summary';
$lang['change_summary_saved'] = 'Change Summary saved.';
$lang['change_summary_required_for_release'] = 'Enter a Change Summary before publishing when Notify or Acknowledge is enabled.';
$lang['admin_override'] = 'Administrator workflow override';
$lang['override_target'] = 'Target status';
$lang['override_reason'] = 'Reason (required)';
$lang['transition_saved'] = 'Document approval workflow updated.';
$lang['notification_failed'] = 'The workflow was updated, but the notification email could not be sent: %s';

$lang['directive_change_denied'] = 'Only an administrator or the assigned Publisher may add or remove ~~NOREVISIONCONTROL~~.';
$lang['directive_active_blocked'] = '~~NOREVISIONCONTROL~~ cannot be added while this page has an active Draft/Review/Training/Publication workflow. Publish or otherwise resolve the workflow first.';

// training dispositions
$lang['training_na'] = 'N/A – No training impact';
$lang['training_existing_adequate'] = 'Existing training adequate';
$lang['training_update_existing'] = 'Update existing training';
$lang['training_new_training'] = 'New training required';
$lang['training_awareness'] = 'Awareness / notification only';

// assignment admin
$lang['pattern'] = 'Pattern';
$lang['training'] = 'Training';
$lang['publisher'] = 'Publisher';
$lang['notify'] = 'Default Notify';
$lang['acknowledge'] = 'Default Acknowledge';
$lang['actions'] = 'Actions';
$lang['add_rule'] = 'Add Rule';
$lang['save_rules'] = 'Save Rules';
$lang['remove_rule'] = 'Remove Rule';
$lang['move_up'] = 'Move rule up';
$lang['move_down'] = 'Move rule down';
$lang['sync_rule'] = 'Save & Sync';
$lang['rules_help'] = 'Pattern may be an exact page ID, namespace wildcard (for example policy:**), or a full regular expression. Blank role cells inherit earlier matching values. Comma-separated users/@groups are allowed. Notify may also contain literal email addresses. Acknowledge is for authenticated users/@groups because acknowledgements are recorded by user account. One-off page exclusions may use ~~NOREVISIONCONTROL~~ on its own line; only an administrator or assigned Publisher may add or remove that directive.';
$lang['invalid_pattern'] = 'Invalid or empty assignment pattern';
$lang['rule_added'] = 'Rule added. Sync the rule to initialize/update existing matching pages.';
$lang['rules_saved'] = 'Rules saved. Sync affected rules or run Reconcile Existing Pages.';
$lang['rule_moved'] = 'Rule order changed. Run Reconcile Existing Pages if the new order changes existing assignments.';
$lang['rule_not_found'] = 'Assignment rule not found.';

$lang['reconcile_heading'] = 'Reconcile Existing Pages';
$lang['reconcile_intro'] = 'Re-evaluate all existing wiki pages against the ordered rules and the page-local ~~NOREVISIONCONTROL~~ directive. Newly controlled existing pages are initialized as Published. An actively controlled page will not be excluded while it is Draft or in review/training/publication; that conflict is reported instead.';
$lang['reconcile_run'] = 'Reconcile Existing Pages';
$lang['sync_done'] = 'Rule saved and synchronized: %d pages matched; %d initialized as Published; %d assignments updated; %d excluded by rules; %d excluded by page directive; %d active-workflow conflicts; %d errors.';
$lang['remove_done'] = 'Rule removed and affected pages recalculated: %d pages matched; %d initialized as Published; %d assignments updated; %d excluded by rules; %d excluded by page directive; %d active-workflow conflicts; %d errors.';
$lang['reconcile_done'] = 'Reconcile complete: %d pages scanned; %d initialized as Published; %d assignments updated; %d excluded by rules; %d excluded by page directive; %d active-workflow conflicts; %d errors.';
$lang['sync_page_error'] = 'Could not synchronize page %s: %s';

// current / history tables
$lang['page'] = 'Page';
$lang['status'] = 'Status';
$lang['assigned_to'] = 'Assigned To';
$lang['submitted_by'] = 'Submitted By';
$lang['reviewed_by'] = 'Reviewed By';
$lang['version'] = 'Version';
$lang['updated'] = 'Updated';
$lang['history'] = 'History';
$lang['view_history'] = 'History';
$lang['date'] = 'Date/Time';
$lang['action'] = 'Action';
$lang['actor'] = 'Actor';
$lang['revision'] = 'Wiki Revision';
$lang['assigned_reviewer'] = 'Assigned Reviewer';
$lang['comment'] = 'Note';
$lang['select_page'] = 'Page';
$lang['select_page_prompt'] = 'Select a page…';
$lang['show_history'] = 'Show History';
$lang['history_denied'] = 'You do not have permission to view workflow history for this page.';
$lang['working_history_restricted'] = 'Working revision history is restricted; showing the latest Published revision.';

// email
$lang['email_subject'] = '[DocApproval] %s — %s';
$lang['email_intro'] = 'A controlled document workflow requires attention.';
$lang['email_page'] = 'Page';
$lang['email_action'] = 'Last Action';
$lang['email_status'] = 'Current status';
$lang['email_actor'] = 'Action by';
$lang['email_comment'] = 'Note';
$lang['email_change_summary'] = 'Change Summary';
$lang['email_training'] = 'Training disposition';
$lang['email_training_note'] = 'Training note';
$lang['email_view_page'] = 'View page';
$lang['email_view_diff'] = 'View changes since the previous published revision';
$lang['email_closing'] = 'Thank you,';
$lang['email_ack_subject'] = '[DocApproval] Acknowledgement Required — %s Rev %s';
$lang['email_notify_subject'] = '[DocApproval] Document Published — %s Rev %s';
$lang['email_ack_intro'] = 'A controlled document has been published and requires your acknowledgement.';
$lang['email_notify_intro'] = 'A controlled document has been published or updated.';
$lang['email_ack_instruction'] = 'Please review the published revision and record your acknowledgement on the page.';
$lang['email_notify_no_action'] = 'This message is informational only; no acknowledgement is required.';

// end-user acknowledgement
$lang['ack_required_title'] = 'Acknowledgement Required';
$lang['ack_required_context'] = 'Published Revision %s on %s requires your acknowledgement.';
$lang['ack_confirm_label'] = 'I confirm that I have read and understood this published revision.';
$lang['ack_button'] = 'Acknowledge';
$lang['ack_saved'] = 'Your acknowledgement has been recorded.';
$lang['ack_confirm_required'] = 'Confirm that you have read and understood the published revision before acknowledging.';
$lang['ack_complete_title'] = 'Acknowledged';
$lang['ack_complete_context'] = 'You acknowledged Published Revision %s on %s.';
$lang['ack_view_changes'] = 'View changes since your previous acknowledgement';
$lang['ack_published'] = 'Published';
$lang['ack_status_heading'] = 'Acknowledgement Status';
$lang['ack_status_intro'] = 'Current formal publications with their snapshotted acknowledgement audience. Outstanding users are based on the audience captured when that revision was Published.';
$lang['ack_status_none'] = 'No current publication audience snapshots exist yet.';
$lang['ack_required_count'] = 'Required';
$lang['ack_complete_count'] = 'Acknowledged';
$lang['ack_outstanding_count'] = 'Outstanding';
$lang['release_notify'] = 'Notify Audience';
$lang['release_acknowledge'] = 'Acknowledge Audience';
$lang['ack_report_title'] = 'Acknowledgement Report';
$lang['ack_report_summary'] = 'Published Revision %s: %d of %d required users have acknowledged; %d outstanding.';
$lang['ack_report_acknowledged_heading'] = 'Users who have acknowledged';
$lang['ack_report_pending_heading'] = 'Users who still need to acknowledge';
$lang['ack_report_none'] = 'None';

// personal task dashboard
$lang['my_tasks_title'] = 'My DocApproval Tasks';
$lang['my_login_required'] = 'You must be logged in to view your DocApproval tasks.';
$lang['my_ack_required'] = 'Acknowledgements Required';
$lang['my_reviews_required'] = 'Reviews Required';
$lang['my_training_required'] = 'Training Reviews Required';
$lang['my_publish_required'] = 'Ready to Publish';
$lang['my_recent_acknowledgements'] = 'Recently Acknowledged';
$lang['my_none_outstanding'] = 'None outstanding.';
$lang['my_published'] = 'Published';
$lang['my_waiting_since'] = 'Waiting Since';
$lang['my_acknowledged_on'] = 'Acknowledged';
$lang['my_open_ack'] = 'Review / acknowledge';
$lang['my_open_review'] = 'Open review';
$lang['my_open_training'] = 'Open training review';
$lang['my_open_publish'] = 'Open publication';
$lang['my_view'] = 'View';

// DW2PDF integration
$lang['dw2pdf_working'] = 'WORKING DRAFT - NOT APPROVED';
$lang['dw2pdf_published'] = 'Published';
$lang['dw2pdf_uncontrolled'] = 'PAGE NOT SUBJECT TO REVISION CONTROL';

$lang['move_blocked_active_uncontrolled'] = 'Move blocked: %s has an active DocApproval workflow and the destination %s is not controlled by any DocApproval rule. Publish the page or move it to another controlled location.';
$lang['move_safeguard_active_uncontrolled'] = 'DocApproval safety hold: %s was moved by a path that bypassed the normal pre-move validation. The page remains controlled because its workflow is still active.';
