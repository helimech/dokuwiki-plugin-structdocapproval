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
$lang['admin_override'] = 'Administrator workflow override';
$lang['override_target'] = 'Target status';
$lang['override_reason'] = 'Reason (required)';
$lang['transition_saved'] = 'Document approval workflow updated.';
$lang['notification_failed'] = 'The workflow was updated, but the notification email could not be sent: %s';

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
$lang['actions'] = 'Actions';
$lang['add_rule'] = 'Add Rule';
$lang['save_rules'] = 'Save Rules';
$lang['remove_rule'] = 'Remove Rule';
$lang['move_up'] = 'Move rule up';
$lang['move_down'] = 'Move rule down';
$lang['sync_rule'] = 'Save & Sync';
$lang['rules_help'] = 'Pattern may be an exact page ID, namespace wildcard (for example policy:**), or a full regular expression. Blank role cells inherit earlier matching values. Comma-separated users/@groups are allowed. For Review, groups are expanded to actual users when a page is submitted.';
$lang['invalid_pattern'] = 'Invalid or empty assignment pattern';
$lang['rule_added'] = 'Rule added. Sync the rule to initialize/update existing matching pages.';
$lang['rules_saved'] = 'Rules saved. Sync affected rules or run Reconcile Existing Pages.';
$lang['rule_moved'] = 'Rule order changed. Run Reconcile Existing Pages if the new order changes existing assignments.';
$lang['rule_not_found'] = 'Assignment rule not found.';

$lang['reconcile_heading'] = 'Reconcile Existing Pages';
$lang['reconcile_intro'] = 'Re-evaluate all existing wiki pages against the ordered rules. Newly controlled existing pages are initialized as Published. An actively controlled page will not be excluded while it is Draft or in review/training/publication; that conflict is reported instead.';
$lang['reconcile_run'] = 'Reconcile Existing Pages';
$lang['sync_done'] = 'Rule saved and synchronized: %d pages matched; %d initialized as Published; %d assignments updated; %d excluded; %d active-workflow conflicts; %d errors.';
$lang['remove_done'] = 'Rule removed and affected pages recalculated: %d pages matched; %d initialized as Published; %d assignments updated; %d excluded; %d active-workflow conflicts; %d errors.';
$lang['reconcile_done'] = 'Reconcile complete: %d pages scanned; %d initialized as Published; %d assignments updated; %d excluded; %d active-workflow conflicts; %d errors.';
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
$lang['email_action'] = 'Action';
$lang['email_status'] = 'Current status';
$lang['email_actor'] = 'Action by';
$lang['email_comment'] = 'Note';
$lang['email_training'] = 'Training disposition';
$lang['email_training_note'] = 'Training note';
$lang['email_view_page'] = 'View page';
$lang['email_view_diff'] = 'View changes since the previous published revision';
$lang['email_closing'] = 'Thank you,';

// DW2PDF integration
$lang['dw2pdf_working'] = 'WORKING DRAFT - NOT APPROVED';
$lang['dw2pdf_published'] = 'Published';
