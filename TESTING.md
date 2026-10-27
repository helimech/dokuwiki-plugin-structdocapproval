# Struct DocApproval — first test build checklist

This is a first test build. Use it on the test wiki before production.

## 1. Install and initialize

1. Disable the old `structpublish` plugin first.
2. Install this plugin in `lib/plugins/structdocapproval/`.
3. Confirm Struct is enabled.
4. Log in as an administrator and open any normal wiki page once. The migration hook should create:
   - internal Struct schema `struct_docapproval`
   - `struct_docapproval_rules`
   - `struct_docapproval_pages`
5. Open **Administration → Struct DocApproval**.

The DokuWiki plugin/folder name is deliberately `structdocapproval` (no underscore). The internal schema/table prefix remains `struct_docapproval`.

## 2. Start with one narrow test rule

For example:

| +/- | Pattern | Reviewer | Training | Publisher |
| --- | --- | --- | --- | --- |
| + | `sandbox:approvaltest:**` | your-user-id | `@admin` | `@admin` |

Use **Save & Sync** on that rule. Existing matching pages should be initialized as **Published**. A brand-new page created later under that pattern should begin as **Draft**.

Also test an exclusion underneath the include rule, for example:

`- sandbox:approvaltest:exclude_me`

A later matching rule should override an earlier one; blank role fields inherit earlier values.

## 3. Normal workflow

With an existing initialized page:

1. Edit and save it normally → **Draft**.
2. As a read-only user, verify the previous Published revision is still displayed.
3. As the editor, click **Ready for Review**.
4. As the assigned reviewer, test:
   - **Return for Changes** with no comment → should be rejected by the plugin.
   - **Return for Changes** with a comment → should return to Draft.
   - resubmit and **Approve** → Awaiting Training Review.
5. As Training:
   - select `Update existing training` without a note → should be rejected.
   - enter a note and **Complete Training Review** → Ready to Publish.
6. As Publisher:
   - test **Return to Training** with a required comment;
   - complete Training again;
   - enter a version and **Publish**.
7. Re-run a minor correction and publish using the same version number. Duplicate document version strings are deliberately allowed.

## 4. Minor-change behaviour

- Draft + Minor Change → remains/returns Draft.
- Awaiting Review/Training/Ready to Publish + Minor Change → Draft.
- Published + Minor Change by an admin or assigned Publisher → remains Published when the configuration option is enabled, with a new workflow audit row and the same version.
- A normal editor's Minor Change to Published content → Draft.

## 5. Rule synchronization

- Edit a rule's pattern and press **Save & Sync**. Both the old and new pattern areas should be recalculated.
- Remove a rule. Pages that matched it should be recalculated immediately.
- If an exclusion would remove a page that currently has an active Draft/Review/Training/Publication workflow, the plugin should report a conflict and keep that page controlled.
- **Reconcile Existing Pages** should safely rescan the whole wiki.

## 6. Aggregations

On a test dashboard page add:

```
---- struct_docapproval ----
----
```

for current status, and:

```
---- struct_docapprovalhistory ----
----
```

for the page-selectable workflow history.

The current table should show only the latest workflow row per page. The history view should retain every transition.

## 7. DokuWiki revision history / reader visibility

- Workflow users should see workflow annotations in DokuWiki Page History.
- A read-only user should not be able to use normal page display, diff, revisions, source/edit view, or export actions to expose an unpublished working revision.
- A workflow user/editor should retain access to working revisions and diffs.
- Search snippets shown to read-only users should be taken from the Published revision. A brand-new Draft with no Published baseline should be removed from ordinary full-text search results.

## 8. Email (enable after the workflow itself is stable)

Leave **Send workflow notification emails** disabled for the first pass. Once the state transitions work, enable it and test:

- Ready for Review → selected reviewer + optional Review Queue CC
- Review Approved → Training role
- Return for Changes → submitter
- Training Reviewed → Publisher role
- Return to Training → Training role
- Return to Draft → submitter
- Published → optional configured publication notification recipients

Emails contain a page link and, when there is a prior Published revision, a diff link from that Published revision to the current working revision.

## Known first-build test focus

This build has been PHP syntax-checked and its plugin-owned SQL/JSON have been statically validated, but it has not been run inside your exact DokuWiki/Struct/auth environment yet. In particular, group expansion for the Reviewer dropdown depends on the authentication backend supporting user enumeration by group, so that is worth testing early.


## Reader banner / admin override

- As a read-only user, open a Published controlled page. Confirm the banner contains only `Published Revision: NNN {date}` and does not show the workflow stages or actor names.
- Publish a numeric revision entered as `2`. Confirm it is stored/displayed as `002`, and the next suggested revision is `003`.
- As an editor/reviewer/training/publisher/admin, confirm the full workflow tracker is still visible.
- Turn **Enable the administrator workflow override tool** off in Configuration Settings. Confirm the Admin Override details section disappears and a crafted override POST is rejected server-side.
- With the override tool off, confirm an administrator can still perform normal Review/Training/Publish actions.


## 9. Workflow notes

- Ready for Review with an optional Note → confirm the note appears in workflow history.
- Review Approve with a Note → confirm the note is retained.
- Review Return for Changes with no Note → must be rejected.
- Training Review with a Training Note and a separate optional Note → confirm both are retained.
- Publish with a Note → confirm the publication row in workflow history contains the note.
- Return to Training / Return to Draft with no Note → must be rejected.

## 10. DW2PDF placeholders

Create or edit a DW2PDF template footer and include:

```html
@ID@ | Revision @DOCREV@ | @DOCSTATUS@ | @DOCDATE@ | Page @PAGE@ of @PAGES@
```

Then test:

- Published controlled page → revision is minimum-two-digit Published revision and status is Published.
- Working Draft exported by an editor/workflow participant → `@DOCREV@` is `DRAFT` and status is `WORKING DRAFT - NOT APPROVED`.
- Ordinary reader exporting while a newer Draft exists → PDF remains based on the Published revision and shows the Published revision metadata.
- Historical Published revision export → footer matches that historical Published revision.
- Uncontrolled page → DocApproval placeholders resolve blank rather than leaking raw token text.


## 11. Notification email formatting

With workflow email enabled:

- Trigger a workflow notification in New Outlook, Outlook on the web, and Gmail if available.
- Confirm Page, Action, Current status, Action by, Note, Training disposition, and Training note labels are bold in HTML-capable clients.
- Confirm Page uses the page's first headline; remove the headline temporarily and confirm it falls back to the page ID.
- Confirm Action by shows the DokuWiki user's configured real name (First Last) and falls back to login only when no real name is available.
- Confirm View page and View changes since the previous published revision are explicit clickable hyperlinks in New Outlook.
- View the message as plain text/source or in a plain-text client and confirm the full URLs remain present in the text alternative.


## 12. Page move / rename

- Move a controlled Published page to a new page ID inside the same controlled namespace. Confirm there is no SQL UNIQUE constraint error and the Published status/history follows the page.
- Move/rename a page where the destination PID already has a materialized DocApproval assignment row. Confirm the source assignment replaces it without an error.
- Rename a page governed by an exact-page DocApproval rule. Confirm the exact rule follows the new page ID.
- Retry a move operation where possible. Confirm the DocApproval move handler does not create duplicate assignment rows or throw a UNIQUE constraint error.


## 13. Notification email layout

- Confirm Page and Current status appear together at the top of the message.
- Confirm View page appears immediately below the Page/Current status summary.
- Confirm Last Action, Action by, Note, and any Training fields appear in the second block.
- Confirm there is visible extra spacing between each bold field label and its value.
- Confirm the previous-published diff heading is on one line and the long clickable URL starts on the following line.


## 14. Admin override / reader protection

- Publish a controlled page and note its Published revision.
- As an administrator, use Admin Override to change the workflow state without editing the page.
- As a read-only user, confirm the page still shows only the compact Published Revision banner and does not expose the full workflow tracker or historical-revision toolbar.
- Confirm the reader still sees the last Published content.
- Confirm Draft is absent from the Admin Override target list.
- Submit a crafted Admin Override request with target status `draft` and confirm the server rejects it.
- Make a normal content edit to the Published page and confirm the workflow enters Draft normally.


## 15. Destination-aware page moves

- Move a Published controlled page from a controlled namespace to another controlled namespace with different Reviewer/Training/Publisher assignments. Confirm the toolbar remains and the destination rule values become current.
- Move a Published controlled page to an uncontrolled namespace. Confirm the DocApproval toolbar disappears immediately without running Reconcile.
- Confirm the full workflow history follows the new page ID even after control is removed.
- Move that uncontrolled page back into a controlled namespace. Confirm the current live revision is initialized as the new Published baseline and older history is still present.
- Move a Draft page to another controlled namespace. Confirm the Draft/status/history are retained and later workflow stages use destination assignments.
- Submit a page for Review, then move it to another controlled namespace. Confirm the already assigned Reviewer for that active review remains the reviewer for that review cycle.
- Attempt to move Draft / Awaiting Review / Awaiting Training / Ready to Publish into an uncontrolled namespace. Confirm Move is blocked before any page content or history is relocated.
- Create a planned/tree namespace move containing at least one active workflow page whose destination is uncontrolled. Confirm the plan is aborted before any pages are moved.
- Rename a page controlled by an exact-page rule. Confirm that exact rule follows the page and the move is allowed.


## 16. DW2PDF composite header metadata

Use the following in a DW2PDF header:

```html
<td style="text-align: right">@TITLE@ @DOCHEADERMETA@</td>
```

Confirm:

- Published controlled page → `-- REV: NNN (Published {date})`.
- Working controlled page → `-- REV: DRAFT (WORKING DRAFT - NOT APPROVED)`.
- Uncontrolled page → `-- PAGE NOT SUBJECT TO REVISION CONTROL`.
- Existing individual placeholders such as `@DOCREV@`, `@DOCSTATUS@`, and `@DOCDATE@` still work exactly as before.
- On an uncontrolled page, the individual DocApproval placeholders remain blank while `@DOCHEADERMETA@` carries the explicit uncontrolled-page notice.


## 17. Page-local ~~NOREVISIONCONTROL~~ exclusion

In a namespace that is normally controlled by an assignment rule:

1. Add `~~NOREVISIONCONTROL~~` on its own line to an otherwise uncontrolled/new page as an administrator. Confirm the directive does not render and the page remains outside DocApproval.
2. On a Published controlled page, add the directive as an administrator or assigned Publisher. Confirm the save succeeds, the DocApproval toolbar disappears immediately, and existing workflow history remains available for later reuse.
3. Attempt the same change as an ordinary editor. Confirm the save is rejected.
4. Put a controlled page into Draft, Awaiting Review, Awaiting Training Review, and Ready to Publish in turn and attempt to add the directive. Confirm each save is rejected.
5. Run Reconcile Existing Pages. Confirm the page remains excluded and the completion message reports it under "excluded by page directive".
6. Remove the directive as an administrator or assigned Publisher. Confirm the page's normal namespace/page rules take effect again and the removal save enters Draft rather than silently publishing new controlled content.
7. Mention `~~NOREVISIONCONTROL~~` in ordinary prose without placing it alone on a line. Confirm the page is not excluded.


## 18. Notify / Acknowledge alpha

Use a narrow controlled test namespace and configure distinct users/groups in Notify and Acknowledge.

1. Upgrade from v0.10 and open a normal page. Confirm migration 2 adds Notify/Acknowledge fields without losing existing assignment rules.
2. In Struct DocApproval admin, confirm the rule table is now `Reviewer | Training | Publisher | Notify | Acknowledge` and Save / Save & Sync retain all five values.
3. Put one user only in Notify, one only in Acknowledge, and one in both. Publish a new revision with workflow email enabled.
4. Confirm Notify-only receives an informational Published email, Acknowledge-only receives Acknowledgement Required, and the overlapping user receives only the Acknowledgement Required email.
5. As the Acknowledge user, open the Published page. Confirm the acknowledgement box appears at the bottom of the page.
6. Submit without checking the confirmation box. Confirm acknowledgement is rejected.
7. Check the confirmation and Acknowledge. Confirm the box changes to a recorded confirmation with timestamp after redirect.
8. Edit the page into Draft. As an ordinary reader, confirm the prior Published revision still displays and its acknowledgement remains current. As a workflow participant viewing the Draft, confirm no Draft acknowledgement box is offered.
9. Publish the Draft as a new revision. Confirm the same assigned user is due again for the new formal publication cycle.
10. Before acknowledging the new publication, confirm View changes since your previous acknowledgement compares the prior acknowledged content revision with the new Published content revision.
11. Move the Published page to a namespace with different Notify/Acknowledge rules. Confirm acknowledgement evidence follows the page ID while current audience membership follows the destination rules.
12. With Published minor-edit preservation enabled, make a permitted Minor Change after a user has acknowledged the current publication. Confirm that acknowledgement remains current and no new acknowledgement requirement is created.
13. Add a new Acknowledge user after that minor edit. Confirm the new user can acknowledge the current displayed Published content while the requirement remains associated with the existing formal publication cycle.
14. Put a literal email address in Notify and confirm it can receive the FYI publication email. Put a literal email address in Acknowledge and confirm it is not treated as an acknowledgement identity.


## 19. Publication-time audience snapshot (v0.12)

Use a narrow controlled test page with at least three test accounts.

1. Upgrade from the v0.11 acknowledgement alpha. Confirm migration 3 completes and the existing workflow/history/acknowledgement evidence remains intact.
2. In Struct DocApproval admin, confirm the assignment headers read `Default Notify` and `Default Acknowledge`. Save & Sync a rule and confirm both defaults persist.
3. Put the page through Review and Training. At Ready to Publish confirm a **Release Audience** section appears with:
   - Send publication notification
   - Require acknowledgement
   - prefilled values from the page defaults.
4. Change both fields before publishing. Confirm the values entered at Publish—not later rule changes—become the release audience.
5. In Acknowledge, use a direct user plus an `@group`. Publish and confirm the group is expanded to individual requirement rows.
6. Put an unknown username or a literal email address in Acknowledge. Confirm Publish is rejected before a Published workflow transition is created.
7. Put a literal email address in Notify. Confirm Publish succeeds and the address receives the FYI publication email when mail is enabled.
8. Put one user in both Notify and Acknowledge. Confirm only the Acknowledgement Required email is sent to that address.
9. After publishing, change Default Acknowledge and/or group membership. Confirm the existing publication's required-user snapshot does not change.
10. As a snapshotted required user, open the current Published page. Confirm the bottom acknowledgement box appears. As a user not in the snapshot, confirm it does not.
11. Acknowledge the publication. Confirm the box becomes Acknowledged and the Admin → Struct DocApproval Acknowledgement Status counts update.
12. Expand Outstanding in the admin status table and confirm the expected usernames are shown.
13. Edit the page into Draft. As an ordinary reader, confirm the prior Published revision and its acknowledgement state remain authoritative. As a workflow participant viewing Draft, confirm no Draft acknowledgement box is offered.
14. Publish the Draft again with a different Acknowledge audience. Confirm a new independent requirement snapshot is created and prior acknowledgement evidence remains historical.
15. Before acknowledging the new publication, confirm **View changes since your previous acknowledgement** compares the prior acknowledged Published content revision to the new Published content revision.
16. Make an allowed Published-preserving Minor Change. Confirm users who acknowledged the publication remain complete and are not required to acknowledge again.
17. Move the Published page to a different controlled namespace. Confirm publication snapshot, requirement rows, acknowledgement evidence, and compliance counts follow the new page ID; destination defaults apply only to future Publish actions.
18. Move the Published page to an uncontrolled namespace. Confirm DocApproval UI/acknowledgement box disappear while historical publication/acknowledgement evidence remains stored.
19. Use Admin Override to Published on a revision that was not normally Published. Confirm it does not silently create a Notify/Acknowledge audience; acknowledgement requirements are only created by the normal Publish action.


## 20. Multi-recipient picker and on-page acknowledgement report (v0.12.2)

1. Bring a test page to Ready to Publish with Default Notify and Default Acknowledge populated.
2. Confirm each release-audience field renders existing values as removable chips/tokens.
3. Click into Notify, select a suggested user/group, then immediately type/select a second and third recipient. Confirm suggestions continue to appear after every selection.
4. Type a literal email address in Notify and press comma or Enter. Confirm it becomes a token and Publish accepts it.
5. In Acknowledge, add multiple users/@groups with the picker. Confirm all tokens are submitted and the publication snapshot resolves them correctly.
6. Remove a chip with its × button and confirm the removed token is not included at Publish.
7. Use Backspace in an empty token input and confirm the last token is removed.
8. In Configuration Settings set **Who can see the on-page acknowledgement report** to Off. Confirm no report appears.
9. Test Administrators only, Managers and administrators, assigned Publisher and administrators, Editors/workflow participants and administrators, and All logged-in users with appropriate accounts.
10. Independently set **What the on-page acknowledgement report shows** to acknowledged users, pending users, and both. Confirm the page report changes without altering stored acknowledgement evidence.
11. Confirm acknowledged users show their acknowledgement date/time and pending users show the correct outstanding accounts from the publication-time snapshot.
12. While a newer Draft exists, open the prior Published revision as an ordinary reader with report permission. Confirm the report remains tied to the authoritative Published revision, not the Draft.


## 21. Collapsed report and real names (v0.12.3)

1. Publish a revision with several required acknowledgers whose login names differ from their DokuWiki full names.
2. Open the Published page with an account permitted to see the on-page acknowledgement report.
3. Confirm the report starts collapsed and its summary shows the revision, acknowledged/required count, and outstanding count.
4. Expand the report and confirm acknowledged and pending users are shown by full name rather than login.
5. Hover a displayed full name and confirm the underlying login is available as the tooltip.
6. Confirm acknowledgement date/time remains visible next to completed users.
7. In Admin → Struct DocApproval → Acknowledgement Status, expand an Outstanding count and confirm the listed users use full names.
8. Test a user account with no full-name attribute and confirm the login is used as a safe fallback.


## 22. Change Summary release communication (v0.12.4)

1. Upgrade from v0.12.3 and confirm migration 4 completes without affecting existing workflow, publication audience, or acknowledgement data.
2. Edit a Published controlled page normally. Confirm the new Draft banner contains a blank **Change Summary** box.
3. Enter a multi-line summary and click **Save Summary**. Reload and confirm it persists.
4. Edit the page content again while it is still in the same working cycle. Confirm the saved Change Summary carries forward to the new DokuWiki revision.
5. Submit for Review. Confirm the assigned Reviewer can edit/save the summary and the review-notification email includes it.
6. Approve Review. Confirm the Training role can edit/save the same summary and its workflow notification email includes the current value.
7. Complete Training Review. Confirm the Publisher can still edit/save the summary at Ready to Publish.
8. With **Send publication notification** checked, clear the Change Summary and attempt Publish. Confirm Publish is rejected and no Published workflow row is created.
9. Repeat with Notify off and **Require acknowledgement** checked. Confirm blank Change Summary is rejected.
10. Turn both release-communication checkboxes off and leave Change Summary blank. Confirm Publish succeeds; this is the quiet grammar/administrative-release case.
11. Publish a revision with Notify and/or Acknowledge selected and a populated Change Summary. Confirm the summary appears prominently in the resulting release email.
12. Confirm the full workflow-user Published banner shows the snapshotted Change Summary after publication.
13. Edit that Published page again. Confirm the new Draft cycle starts with a blank Change Summary rather than carrying the prior release summary into the next revision.
14. Make a Published-preserving Minor Change. Confirm it does not start a new Change Summary/release cycle or alter the prior publication snapshot.
15. Move a working controlled page to another controlled namespace. Confirm the current Change Summary follows the page.
16. Move a Published page. Confirm its snapshotted Change Summary follows the publication record.
17. Review `struct_docapproval_change_summary_history` and confirm actual summary edits retain page, working revision, actor, text, and timestamp.


## 23. Change Summary component loading (v0.12.4.1)

1. Upgrade over v0.12.4.
2. Open a controlled Draft page and confirm the Change Summary box renders without "Change summary helper is unavailable."
3. Save a summary and confirm the action is handled and the value persists after redirect.
4. Advance through Review/Training/Ready to Publish and confirm the same component remains available at each stage.

## 24. Workflow-summary save and release-audience display (v0.12.5)

1. On any active workflow stage, edit **Change Summary** and click the stage/action button without first clicking **Save Summary**. Reload the page and confirm the edited summary was saved.
2. Confirm the standalone **Save Summary** button still saves without changing workflow state.
3. From Ready to Publish with Notify or Acknowledge enabled, edit the summary and click **Publish** directly. Confirm the new text is the publication snapshot and is used by release email content.
4. Load a stale workflow page after another content revision is created and confirm a transition refuses to carry the stale Change Summary forward.
5. In Release Audience, confirm an authenticated user chip displays the configured full name, the login is available as the chip tooltip, and the submitted/stored value remains the login.
6. Confirm autocomplete shows Full Name (username), matches searches by either part, and still displays @group unchanged.
7. Confirm the Ready to Publish action row appears below Release Audience with **New Version** beside **Publish**, and the return **Note** beside **Return to Training / Return to Draft**. Narrow the browser and confirm the action groups wrap cleanly.

## 25. My DocApproval Tasks dashboard (v0.12.6)

Create a normal wiki page containing:

```
---- struct_docapproval_my ----
----
```

Then test with accounts that have different DocApproval responsibilities.

1. Open the page while logged out. Confirm it shows only the login-required message and no personal task data.
2. Log in as a user with no current tasks. Confirm all four summary counts are zero and each current-action section says None outstanding.
3. Publish a document requiring that user to acknowledge. Confirm **Acknowledgements Required** becomes 1 and the row shows page, document version, Published date, Change Summary, and **Review / acknowledge**.
4. Click **Review / acknowledge** while a newer Draft exists. Confirm the link opens the authoritative Published revision and the acknowledgement control is available.
5. Make a Published-preserving Minor Change within the same acknowledgement cycle. Confirm the dashboard link follows the actual current Published content revision without creating a new acknowledgement requirement.
6. Assign the user as the selected Reviewer and submit a page for Review. Confirm it appears under **Reviews Required** and disappears after Approve or Return for Changes.
7. Assign Training directly to the user, then through an `@group`; in both cases confirm an Awaiting Training Review page appears only for matching users and disappears after the Training review is completed/returned.
8. Assign Publisher directly to the user, then through an `@group`; confirm a Ready to Publish page appears only for matching users and disappears after Publish/Return.
9. Confirm workflow task rows show the current working Change Summary and the date/time that workflow state was entered.
10. Complete an acknowledgement. Confirm it immediately leaves **Acknowledgements Required** and appears in the collapsed **Recently Acknowledged** list with its acknowledgement time.
11. Remove the user's read ACL for a page that would otherwise be a task. Confirm the page is absent from the personal dashboard.
12. Open the same dashboard page as two different users and confirm each sees only their own task queues.
13. Confirm no syntax parameter can be used to select another username; the dashboard always derives the current authenticated account server-side.

## 26. Management compliance dashboard (v0.12.7)

Create a management/report page containing:

```
---- struct_docapproval_compliance ----
----
```

1. Open the page as a normal authenticated user who is neither manager nor administrator. Confirm no compliance data is shown and access is denied.
2. Open it as a manager/administrator. Confirm the default scope is **Current publications** and the summary cards match the visible publication rows.
3. Confirm each publication row shows Page, Version, Published date, Required, Acknowledged, Outstanding, Completion percentage, Change Summary, and expandable Details.
4. Expand Details. Confirm the snapshotted Acknowledge Audience is shown, users display by full name with login on hover, acknowledged users include timestamps, and outstanding users are listed separately.
5. Complete an acknowledgement from another account, reload the compliance page, and confirm the publication counts, total cards, and User Compliance row update.
6. Make a Published-preserving Minor Change. Confirm the Current publication remains the same formal acknowledgement cycle and its page link opens the actual latest Published content revision.
7. Publish the page again as a new formal revision. Confirm Current scope shows only the new cycle and **All publication history** shows both publication snapshots.
8. Move a previously Published page out of DocApproval control. Confirm it disappears from Current scope but remains visible in All publication history.
9. Filter by a namespace fragment such as `supply:` and confirm only matching page IDs remain.
10. Select/type a Required User and confirm only releases requiring that login remain; with Outstanding/Complete selected, confirm the status applies to that user's requirement.
11. Without a user filter, test Outstanding, Complete, and No acknowledgement required. Confirm the filter applies to the publication-level compliance state.
12. Test Published From/To dates and confirm boundaries are inclusive for the selected local calendar dates.
13. Confirm the User Compliance table aggregates Required, Acknowledged, Outstanding and Completion across only the currently filtered publication set.
14. Remove the manager's read ACL for a page represented in historical compliance data. Confirm that page is not exposed in the report.
15. Switch between two managers/admins and confirm the report does not cache another user's ACL-filtered result.

## 27. Unified DocApproval dashboard (v0.12.8)

Create a normal wiki page containing:

```
---- struct_docapproval_dashboard ----
----
```

1. Open the page while logged out. Confirm it shows the dashboard heading plus the login-required message and no task/document data.
2. Open it as a normal authenticated user. Confirm only **My Tasks** is available and its acknowledgement/review/training/publish queues behave exactly like the standalone `struct_docapproval_my` report.
3. Open it as a DokuWiki manager/administrator. Confirm tabs appear for **My Tasks**, **Documents**, **Compliance**, and **History**.
4. Confirm the My Tasks tab count equals the sum of that account's current acknowledgement, review, training-review and publication tasks.
5. Open **Documents**. Confirm summary cards show Draft, Awaiting Review, Awaiting Training Review, Ready to Publish, Published, and total outstanding acknowledgements for the currently filtered document set.
6. Confirm each document row shows Page, Workflow, Assigned To, Waiting Since, latest Published Rev, Acknowledgement state, Change Summary, and Details.
7. Put pages into each workflow stage. Confirm Review shows the reviewer frozen into that cycle while Training/Ready to Publish show their current direct-user/@group assignment specs.
8. Filter Documents by page/namespace fragment, workflow status, assigned user and acknowledgement state. Confirm Apply and Reset retain the Documents tab.
9. For an assigned-user filter, test both a direct user assignment and an `@group` assignment. Confirm a member of the group matches.
10. Expand Details for an active page. Confirm current workflow status/date, workflow actors/training information/note where present, current Published revision metadata, and acknowledgement summary are shown.
11. From Details click **History**. Confirm the dashboard switches to History with that page already selected.
12. In History select another page and submit. Confirm the dashboard remains on the History tab.
13. Open Compliance, change filters and submit/reset. Confirm the dashboard remains on the Compliance tab and the existing v0.12.7 compliance report still behaves the same.
14. Make a Published-preserving Minor Change. Confirm Documents continues to show the same formal acknowledgement obligation while the page itself remains Published.
15. Open the same dashboard as two different users/managers and confirm nocache/ACL filtering prevents one account's task or page visibility from leaking to another.
16. Confirm all previous standalone syntaxes still render independently after the unified dashboard is installed.

## 28. Live Documents table / DataTables integration (v0.12.9)

Use the unified dashboard Documents tab with a realistic controlled-document set.

1. Confirm the Documents tab initially shows 50 rows per page and DataTables pagination/info are present without a page reload.
2. Type part of a page ID/namespace into **Page / namespace**. Confirm rows update while typing and the browser URL/page does not reload.
3. Change **Workflow status** and **Acknowledgement state**. Confirm each redraw is immediate.
4. Type a direct user's login in **Assigned user** and confirm matching direct assignments remain.
5. Type/select a user who is assigned only through an `@group`. Confirm that row still matches. Repeat by typing part of the user's configured full name.
6. Change **Rows** through 25, 50, 100 and All. Confirm pagination and the DataTables info line update correctly.
7. Click each workflow summary card. Confirm it becomes the active workflow filter; click the same card again to clear it.
8. Click **Ack Outstanding**. Confirm only documents with an outstanding acknowledgement obligation remain; click it again to clear.
9. Combine page/namespace, assignee and acknowledgement filters. Confirm the workflow-card counts update to show the workflow distribution within that filtered set rather than collapsing every non-selected workflow bucket to zero.
10. Apply a Workflow filter and confirm Ack Outstanding reflects that workflow subset while ignoring only the acknowledgement-state facet.
11. Click **Reset**. Confirm all filters clear, 50 rows/page is restored, and the default operational sort returns.
12. Confirm the default ordering prioritizes Awaiting Review, Awaiting Training Review, Ready to Publish, Draft, then Published, with older waiting items first within the stage.
13. Click table headers and confirm normal DataTables column sorting still works.
14. Confirm the Documents table does not show a duplicate generic DataTables search box or page-length selector in addition to the DocApproval controls.
15. Confirm the column heading reads **Acknowledgement**, not **Default Acknowledge**.
16. Confirm My Tasks, Compliance, History, publication acknowledgement controls and all standalone dashboard syntaxes still behave as before.

## 29. Titles, age and compliance CSV export (v0.12.10)

1. Open the unified **Documents** tab with pages whose DokuWiki first heading differs from the page ID. Confirm the human-readable title is the main link and the page ID is retained underneath.
2. Type part of a document title into **Page / title / namespace**. Confirm the live Documents filter matches it just as it matches a page ID/namespace.
3. Confirm **State Since** shows the normal date/time plus a neutral elapsed value such as `3 days`; verify Draft and Published rows are no longer labelled as "Waiting Since".
4. Open **My Tasks**. Confirm acknowledgement, Review, Training Review, Ready to Publish and Recently Acknowledged rows use human-readable document titles with page IDs underneath where different.
5. Confirm My Tasks Published/Waiting dates also show an elapsed value without changing workflow/task ordering.
6. Open **History**. Confirm the page selector shows `Title — page:id` where a first heading is available, and the selected page heading also shows title plus page ID.
7. Open **Compliance**. Confirm publication rows show human-readable title plus page ID and Published dates include elapsed age.
8. Filter Compliance by part of a document title and confirm the correct publication rows remain.
9. Apply a combination of Compliance filters (scope, page/title, user, status and/or date range), click **Export CSV**, and confirm the browser downloads a UTF-8 CSV without changing or reloading the report page.
10. Open the CSV in Excel. Confirm columns include Page, Title, Version, Publication Revision, Content Revision, Published, publisher login/name, Current Publication, Change Summary, Acknowledgement Audience, required-user login/name, acknowledgement status and acknowledgement timestamp.
11. For a publication requiring several users, confirm the export contains one row per required user and records Complete/Outstanding plus the acknowledgement time where complete.
12. Filter Compliance to one Required User before exporting. Confirm only that person's requirement rows are present in the CSV even when the matching publication required several other users.
13. Filter to **No acknowledgement required** and export. Confirm those publications are represented with blank required-user fields and status indicating no acknowledgement was required.
14. Switch Compliance to **All publication history** and confirm historical publication rows export independently with their stored publication/content revisions and acknowledgement evidence.
15. In Ready to Publish Release Audience, type an exact configured full name rather than selecting the autocomplete item. Confirm it canonicalizes to the stored login instead of producing an invalid full-name recipient.
16. Repeat normal chip selection, @group entry and literal Notify email entry to confirm the recipient-picker cleanup did not regress existing behavior.
17. Confirm all v0.12.9 live Documents filtering/sorting/paging, My Tasks workflow actions, acknowledgement recording, Compliance details and standalone syntaxes still behave as before.

## 30. History autocomplete and initial Rev 00 (v0.12.11)

1. Open the unified **History** tab. Confirm the old long page dropdown has been replaced by a searchable text/autocomplete control.
2. Begin typing part of a page ID. Confirm the browser offers accessible DocApproval page suggestions and selecting one fills its actual page ID.
3. Begin typing a document title. Confirm autocomplete suggestions display the human-readable title together with its page ID.
4. Select a suggestion and click **Show History** (or press Enter). Confirm the selected page's workflow history loads normally.
5. Type an exact unique document title manually without selecting the suggestion. Confirm it resolves to the correct page.
6. Type text that does not uniquely identify an accessible DocApproval page. Confirm no history is exposed and a clear no-match message appears.
7. From **Documents → Details → History**, confirm the dashboard still switches to History with that document already selected.
8. Confirm History ACL/workflow-view restrictions remain unchanged and inaccessible pages are absent from the autocomplete set.
9. Create a genuinely new page inside a controlled namespace so it begins as Draft with no previous Published baseline. Complete the normal workflow to Ready to Publish and confirm **New Version** defaults to **00**.
10. Publish that new page as 00, make the next normal revision, complete the workflow again, and confirm the next suggested version is **01**.
11. Use an existing page that was brought under DocApproval by Sync/Reconcile and therefore has an initialized Published baseline with no explicit document version. Edit it through a new workflow cycle and confirm the first subsequent suggested document revision remains **01**, not 00.
12. Confirm manually entered numeric versions continue to normalize to a minimum of two digits and non-simple version labels continue to behave as before.
13. Re-run the v0.12.9 Documents live filtering tests and the v0.12.10 Compliance CSV export tests to confirm no regression.

## 31. Acknowledgement due dates, reminders and compact publication history (v0.12.12)

1. Upgrade from v0.12.11.1 and confirm database migration 5 completes. Verify existing publication rows remain intact, their acknowledgement due date is blank, and no reminder evidence is fabricated.
2. In Configuration Settings confirm **Default acknowledgement due period in days** defaults to 14 and accepts 0 to disable the default due date.
3. Bring a page to Ready to Publish with Require acknowledgement unchecked. Confirm the due-date control is disabled.
4. Enable Require acknowledgement. Confirm the due-date control becomes active and is prefilled from the configured default period.
5. Change the due date for that release and Publish. Confirm the stored Compliance due date matches the selected date and does not change if the configuration default is later changed.
6. Attempt to Publish with a due date in the past. Confirm Publish is rejected without creating a publication snapshot.
7. Confirm the initial acknowledgement-required email includes the due date when one is set and behaves as before when the due date is blank.
8. Log in as a required user. Confirm My Tasks shows the Due column and the Published page acknowledgement box shows the same due date.
9. Test a future due date, a due date of today, and an overdue date (by using a short test date / database-safe test setup). Confirm the UI shows **Due in N days**, **Due today**, and **Overdue N days** appropriately.
10. In Documents confirm an overdue acknowledgement appears in both **Ack Outstanding** and **Overdue** counts. Click the Overdue card and use the Acknowledgement state dropdown to confirm live filtering works without a page reload.
11. In Compliance confirm the new Overdue summary count and **Overdue** status filter reflect outstanding user obligations whose snapshotted due date has passed.
12. In the User Compliance table confirm each user has an Overdue count and overdue users sort ahead of non-overdue outstanding users.
13. Expand a current publication with outstanding acknowledgements in Compliance. Confirm **Send Reminder (N)** appears only when workflow email is enabled.
14. Click Send Reminder and cancel the confirmation. Confirm no email is sent and no audit row is created.
15. Confirm the reminder and send it. Verify only currently outstanding users with DokuWiki email addresses receive the reminder; already acknowledged users do not.
16. Confirm the success message reports delivered users versus total outstanding users. Test an outstanding user without an email address and confirm the delivery count reflects that.
17. Confirm the reminder email identifies the document/revision, includes due date and Change Summary when available, and links to the Published page.
18. Reload Compliance Details. Confirm Last reminder, sender and reminder-batch count are shown.
19. Inspect `struct_docapproval_reminders` and confirm each delivered user has immutable evidence containing batch ID, page ID, formal publication revision, user, actual email address, sender and timestamp.
20. Attempt a reminder for a historical publication or a page that has subsequently been removed from DocApproval control. Confirm the reminder action is unavailable/rejected.
21. Move a controlled Published page after sending a reminder. Confirm publication snapshots, requirements, acknowledgements, due date and reminder evidence all follow the new page ID.
22. Export Compliance CSV. Confirm it now includes Acknowledgement Due, Reminder Batches, Last Reminder At and Last Reminder By, and overdue outstanding requirements are exported with status **Overdue**.
23. Open Documents → Details for a page with several formal publications. Confirm **Recent publication history** shows up to five releases with clickable version, Published time, acknowledgement completion and due date.
24. For an old release that was fully acknowledged after its due date, confirm compact history displays the due date without incorrectly labelling the completed historical release as currently overdue.
25. Confirm Published-preserving Minor Changes remain within the same formal acknowledgement cycle and do not reset the due date or reminder history.
26. Re-run normal Publish/Notify without Require acknowledgement and confirm no acknowledgement due requirement/reminder UI is introduced.
27. Re-run the v0.12.11 History autocomplete / Rev 00 tests and v0.12.10 Compliance CSV tests to confirm no regression.

## 32. Documents Not Published, workflow reminders and automatic acknowledgement reminders (v0.12.13)

1. Upgrade from v0.12.12 and confirm database migration 6 completes without altering existing publication, acknowledgement, due-date or reminder evidence.
2. Open Dashboard → Documents and confirm **Not Published** appears in Workflow status above the individual workflow states.
3. Select Not Published. Confirm Draft, Awaiting Review, Awaiting Training Review and Ready to Publish rows remain while Published rows disappear immediately without a page reload.
4. Combine Not Published with page/title, Assigned user and acknowledgement filters. Confirm faceted summary-card counts and DataTables paging/sorting still behave correctly.
5. Expand Details for a document in **Awaiting Review** with email enabled. Confirm **Email Reviewer** appears. Send it and verify the current frozen reviewer receives a reminder email; configured Review Queue CC behavior remains consistent with normal review submission mail.
6. Expand Awaiting Training Review and Ready to Publish rows. Confirm **Email Training** and **Email Publisher** respectively resolve the current assignment rule users/@groups and send to those recipients.
7. Change a workflow state in another browser/session before submitting a stale Documents reminder form. Confirm the reminder is rejected rather than emailed to the old stage owner.
8. Confirm workflow reminder messages include document title, current workflow status, state/waiting timestamp, page link and current Change Summary when present.
9. Confirm workflow reminder controls are not shown when workflow email is disabled and crafted reminder requests are restricted to managers/administrators with page read access.
10. In Configuration Settings confirm automatic acknowledgement reminders are disabled by default, repeat interval defaults to 7 days, and Reminder CC accepts users, @groups or literal email addresses.
11. Enable automatic reminders and use a test publication whose acknowledgement due date has passed. On the first normal wiki request of the first overdue day, confirm one automatic reminder batch is sent to users still outstanding.
12. Confirm a second request the same day does not send another automatic batch. Inspect `struct_docapproval_auto_reminder_runs` and confirm the calendar day was claimed once.
13. Leave the acknowledgement outstanding and advance/test beyond the configured N-day interval. Confirm another automatic reminder is sent only after the interval has elapsed.
14. Send a manual reminder after the due date. Confirm that manual send resets the automatic cadence and prevents an immediate automatic duplicate until N days after that manual reminder.
15. Send a manual reminder before the due date. Confirm it does not suppress the first automatic reminder once the release becomes overdue.
16. Acknowledge as one required user between reminder cycles. Confirm later automatic reminders contain only users who are still outstanding.
17. Configure Reminder CC with a direct user, @group and/or literal email. Confirm those recipients are CC'd on both manual and automatic acknowledgement reminders and are not duplicated when the same address is also an outstanding user's recipient address.
18. Confirm reminder audit rows identify `manual` versus `auto` and store the configured CC spec with each delivered-user row.
19. In Compliance Details confirm Last reminder identifies Manual/Automatic and shows Reminder CC when configured.
20. Export Compliance CSV and confirm Last Reminder Type and Last Reminder CC columns are present.
21. Disable automatic reminders while leaving email enabled. Confirm no automatic daily-run row or emails are created.
22. Disable workflow email entirely. Confirm neither manual nor automatic acknowledgement reminder sends occur.
23. Verify an existing overdue publication with no due date (pre-v0.12.12 migrated data) does not become an automatic reminder candidate.
24. Confirm an uncontrolled historical document never receives an automatic reminder even if its old snapshot has an overdue date and outstanding users.
25. Verify the traffic-driven scheduler behavior: if no wiki request occurs on a due day, the next normal request runs that day's current sweep and sends any then-eligible reminder rather than requiring cron.
26. Re-run v0.12.12 due-date/manual reminder tests and v0.12.9 live Documents filtering tests to confirm no regression.

## 33. Pre-test hardening checks (v0.12.13.1)

1. On an install currently at database version 5, upgrade to v0.12.13.1 and confirm migration 6 reaches version 6 normally. Verify the new automatic-reminder tables/columns exist and all pre-existing publication/reminder data remains intact.
2. On a disposable database copy, force a migration-6 SQL statement to fail midway and confirm the schema changes and DocApproval version marker roll back together rather than leaving a half-applied migration.
3. With automatic reminders enabled and an overdue test publication, submit a **manual Send Reminder** POST as the first request of the day. Confirm only the manual reminder is sent; the automatic sweep waits for a subsequent normal GET.
4. Force an automatic-reminder exception/DB-lock-style failure and confirm the requested wiki page still renders instead of failing because the background reminder service failed.
5. Simulate a reminder run left in `running`. Confirm another request does not reclaim it immediately, but a run older than 30 minutes can be reclaimed and completed.
6. Double-click/rapidly resubmit the same acknowledgement from two browser requests. Confirm exactly one acknowledgement row exists and both requests resolve idempotently rather than reporting a false duplicate-key failure.
7. Configure Require acknowledgement with a typoed or genuinely empty `@group`. Confirm Publish is rejected before the workflow becomes Published.
8. Start a reminder while the publication/user state changes in another session. Confirm the mail helper rechecks the current controlled publication and removes users who have already acknowledged before the actual send.
9. Remove a page from control or publish a newer formal revision before a stale reminder request reaches the mail helper. Confirm the stale reminder is rejected.
10. Compare dashboard tab badge counts with the Documents/Compliance detail data after the overview-query optimization. Confirm ACL-hidden pages remain excluded from both the document and acknowledgement-outstanding badges.
11. Open Documents with the current ~382-page dataset and compare load time/query feel with v0.12.13. Confirm the tab header no longer appears to build the full Documents report twice.
12. Confirm My Tasks shows only current acknowledgement requirements after several historical revisions with old unacknowledged requirements in the database.
13. Configure an invalid direct Reviewer login and confirm it is not offered as an eligible reviewer. Verify valid users and @group members continue to resolve normally.
14. In a disposable test copy, force publication-audience persistence to fail during Publish. Confirm the page remains Ready to Publish rather than becoming Published without a publication snapshot/requirement set.
15. In a disposable test copy, force the workflow record save to fail after the publication audience has been staged. Confirm the staged publication/requirement rows are removed and the page does not acquire a false formal publication boundary.
16. Open the same workflow action in two tabs. Complete the transition in tab A, then submit the stale form in tab B. Confirm tab B is rejected with a reload message instead of creating a duplicate/stale workflow event.
17. Re-run the full v0.12.13 automatic/manual reminder tests, v0.12.12 due-date tests, and v0.12.9 live Documents filtering tests.

## 34. History-link hotfix (v0.12.13.2)

1. Open Dashboard → Documents, expand Details on a controlled document, and click **History**.
2. Confirm the dashboard switches to the History tab, the page picker is prefilled with that document ID, and that document's workflow history is displayed immediately.
3. Repeat with a namespaced page ID containing multiple colons.
4. From an active Draft/Review/Training workflow with a previous Published revision, click the banner's **View Changes** link and confirm both diff revision parameters arrive correctly and the expected comparison opens.
5. Confirm manually selecting a page by ID/title in the History autocomplete still works unchanged.

## 35. Two-digit minimum document revisions (v0.12.14)

1. Create a genuinely new controlled document and confirm its first formal Publish suggestion is **00**.
2. Publish revision **00**, edit through another full workflow cycle, and confirm the next suggestion is **01**.
3. Confirm numeric entries normalize as follows: `0 → 00`, `1 → 01`, `9 → 09`, `10 → 10`, `99 → 99`, `100 → 100`, and `101 → 101`.
4. Confirm an existing initialized page with a Published baseline but no explicit document revision still suggests **01** for its first subsequent controlled revision.
5. Confirm historical records physically stored under the former three-digit convention display using the new minimum-two-digit convention (for example stored `002` displays as **02**, while stored `100` remains **100**).
6. Check the banner, Documents, History, My Tasks, Compliance, acknowledgement report, emails, PDF placeholders, and Compliance CSV for consistent revision display.
7. Confirm non-simple revision labels continue unchanged.


## Optional Reviewer 2 sequential review

Use the test wiki and a disposable controlled page/rule before promoting this feature.

1. **Database upgrade**
   - Update the plugin from the `devel` branch.
   - Open the wiki and confirm the SQLite migration advances to database version 7 without errors.
   - Confirm the assignment admin page shows **Reviewer 1** and **Reviewer 2** columns.

2. **Single-review regression**
   - Leave Reviewer 2 blank on a rule and sync it.
   - Submit a Draft for review.
   - Confirm the submission form and workflow behave as before: one reviewer approves directly to Training.
   - Confirm History records the existing **Approve** action and no Review 2 stage appears.

3. **Two-review happy path**
   - Configure Reviewer 1 and Reviewer 2 with different users and sync the rule.
   - Submit a Draft and confirm both selected reviewers are shown before submission.
   - Confirm only Reviewer 1 is initially assigned and notified.
   - Reviewer 1 approves. Confirm the document remains **Awaiting Review**, Reviewer 2 becomes the current assignee, and the stage display shows Review 1 complete / Review 2 active.
   - Confirm History shows **Review 1 Approved**.
   - Reviewer 2 approves. Confirm the document advances to **Awaiting Training**.
   - Confirm History shows **Review 2 Approved** and Training receives the normal notification.

4. **Reviewer pools**
   - Configure multiple eligible users for Reviewer 1 and/or Reviewer 2.
   - Confirm the submit form provides separate selectors.
   - Confirm selecting the same login for both reviewers is rejected.
   - Confirm the selected users remain frozen for the active review cycle after the assignment rule is edited.

5. **Return for changes**
   - From Reviewer 1, return the document to Draft with the required note.
   - Repeat from Reviewer 2 after Reviewer 1 has approved.
   - In both cases confirm the review cycle resets; resubmission starts again at Reviewer 1 and prior approvals do not display as current.

6. **Tasks, dashboard, reminders and access**
   - Confirm My Tasks shows the review only for the currently assigned reviewer.
   - Confirm the Documents dashboard Assigned To field changes from Reviewer 1 to Reviewer 2 after the first approval.
   - Confirm workflow reminders while Awaiting Review go only to the current assigned reviewer (plus configured review queue CC).
   - Confirm the frozen current reviewer can still access the working page if the assignment rule is changed during the cycle.

7. **Logs**
   - Exercise the full two-review path through publication and verify the DokuWiki error log remains clean.
