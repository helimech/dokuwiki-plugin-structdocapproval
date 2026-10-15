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

- Published controlled page → revision is three-digit Published revision and status is Published.
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
