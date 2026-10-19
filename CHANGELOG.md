# Changelog

## Unreleased

Repository established as the authoritative source for Struct DocApproval.

## 0.12.13 management notifications and automatic reminders test — 2026-10-19

- Added **Not Published** to Dashboard → Documents Workflow status. It live-filters every controlled page whose current workflow state is anything except Published.
- Added manager/administrator workflow reminder controls in Documents → Details for **Awaiting Review**, **Awaiting Training Review**, and **Ready to Publish**.
- Workflow reminders resolve the current frozen Reviewer or current Training/Publisher assignment, include the document title/status/state date/Change Summary/page link, and revalidate the workflow state before sending.
- Added configurable automatic acknowledgement reminders:
  - disabled by default;
  - first automatic reminder is eligible on the first day after the snapshotted acknowledgement due date;
  - repeat interval defaults to 7 days and is configurable;
  - a manual reminder sent after the due date resets the automatic interval to avoid an immediate duplicate.
- Added configurable acknowledgement-reminder CC recipients supporting DokuWiki users, @groups, and literal email addresses. The same CC list applies to manual and automatic acknowledgement reminders.
- Automatic reminder sweeps are claimed once per local calendar day and run on the first normal wiki request after they become due. If the wiki receives no requests, the sweep waits until the next request rather than requiring a cron job.
- Added audit differentiation between Manual and Automatic reminder batches plus the configured reminder CC snapshot.
- Compliance Details and CSV now expose reminder type and CC information.
- Added migration 6 for reminder type/CC evidence and the once-daily automatic-reminder run ledger.
- **Database migration required:** 5 → 6.

## 0.12.12 acknowledgement attention test — 2026-10-19

- Added database migration 5 with a snapshotted acknowledgement due date on each publication plus immutable manual-reminder audit rows.
- Added configurable **Default acknowledgement due period in days** (default 14; 0 leaves the Publish due date blank).
- Ready to Publish now shows **Acknowledgement due** beside the acknowledgement audience; the field is enabled only when Require acknowledgement is selected and may be adjusted for that release.
- Due dates are snapshotted with the publication and included in acknowledgement-required release emails.
- My Tasks, the Published-page acknowledgement box/report, Documents, and Compliance now show due / due-today / overdue state.
- Added **Overdue** filters and counts to Documents and Compliance.
- Added manager/administrator **Send Reminder** for the current controlled publication. It targets only users who are still outstanding, asks for confirmation, and reports delivered versus outstanding counts.
- Reminder evidence records a batch ID, document/publication, recipient user, actual email address, sender, and sent timestamp. Reminder evidence follows a document when it is moved.
- Compliance Details shows the last reminder and reminder-batch count; Compliance CSV now includes due date and reminder evidence and marks overdue requirements explicitly.
- Documents → Details now includes a compact five-release publication history with revision links, publication date, acknowledgement completion and due date.
- Existing publications are migrated with no due date and no fabricated reminder history.
- **Database migration required:** 4 → 5.

## 0.12.11.1 LESS compatibility hotfix — 2026-10-19

- Replaced the CSS `min(42rem, 70vw)` expression on the History autocomplete width with equivalent plain `width` / `max-width` rules because DokuWiki's LESS compiler attempts arithmetic unit conversion and fails on mixed `rem` / `vw` values.
- No functional or database changes.

## 0.12.11 history and initial revision test — 2026-10-19

- Replaced the workflow History page dropdown with a searchable browser-autocomplete input backed by the accessible DocApproval page set.
- History autocomplete suggestions show the human-readable document title together with the page ID; the input accepts either a page ID or a unique exact document title.
- Dashboard → History links still prefill the selected page directly.
- A brand-new controlled document with no previous Published baseline now defaults its first formal Publish to document revision **000** instead of 001.
- Existing pages that were initialized into DocApproval with an implicit Published baseline are intentionally unchanged: because they already have a prior baseline, their first subsequent controlled revision continues to suggest **001**.
- No database migration is required.

## 0.12.10 reporting usability test — 2026-10-19

- Added human-readable DokuWiki page titles throughout the unified Documents view, My Tasks, Compliance rows, and workflow History selector/headings while retaining the page ID underneath for unambiguous identification.
- Page/title/namespace filters now match either the page ID or the page's first heading.
- Added neutral age text to operational dates: workflow state age in Documents, queue age in My Tasks, and publication age in Compliance.
- Renamed the unified Documents date column from **Waiting Since** to **State Since** because Draft and Published rows are not literally waiting for action.
- Added **Export CSV** to Compliance. The export contains the currently filtered publication set and flattens acknowledgement evidence to one row per required user, including page ID/title, document version, publication/content revision, publisher, Change Summary, snapshotted acknowledgement audience, acknowledgement state, and acknowledgement timestamp.
- Required-user filters are honored by the CSV export, so filtering to one person exports only that person's requirement rows.
- Fixed the release-audience token helper's dormant recursive canonicalization bug and now canonicalizes an exact typed full-name match back to the stored login before submission.
- No database migration is required.

## 0.12.9 live dashboard table test — 2026-10-16

- Converted the unified Dashboard → Documents controls to live client-side DataTables filtering; changing filters no longer reloads the wiki page.
- Page/namespace and Assigned User filters update while typing with a short debounce; Workflow and Acknowledgement dropdowns redraw immediately.
- Added a dashboard Rows selector with 25 / 50 / 100 / All and a 50-row default.
- Existing DataTables pagination/info are reused when the site's DataTables plugin has already initialized the table; the DocApproval plugin initializes the same library only as a fallback.
- Workflow summary cards are now clickable filters. Clicking the active card again clears that workflow filter.
- Ack Outstanding is also a clickable filter.
- Summary-card counts update live as the other filters change while remaining faceted so alternate workflow buckets stay visible.
- Assigned User live search includes resolved @group members by both login and configured full name.
- Added operational default sorting: workflow-action stages first, then oldest waiting item.
- Hid the generic DataTables global search/page-length controls for this table because the dashboard now supplies purpose-built controls.
- Renamed the Documents acknowledgement column from the inherited "Default Acknowledge" label to "Acknowledgement".
- No database migration is required.

## 0.12.8 unified dashboard test — 2026-10-16

- Added the recommended one-stop `---- struct_docapproval_dashboard ----` interface.
- All authenticated users receive a **My Tasks** tab using the existing personal acknowledgement/review/training/publish queues.
- Managers/administrators additionally receive **Documents**, **Compliance**, and **History** tabs in the same interface.
- Added a unified Controlled Documents view combining current workflow state, current assignee, waiting date, latest Published revision, current acknowledgement state, Change Summary, and expandable workflow/publication details.
- Added Documents filters for page/namespace, workflow status, assigned user, and acknowledgement state.
- Added workflow-state summary cards plus a current outstanding-acknowledgement count.
- Document details link directly to the page and to that page's workflow History tab.
- Existing Compliance and History filter forms now preserve their active unified-dashboard tab when embedded.
- Existing `struct_docapproval`, `struct_docapprovalhistory`, `struct_docapproval_my`, and `struct_docapproval_compliance` syntaxes remain supported as standalone/special-purpose reports.
- No database migration is required.

## 0.12.7 acknowledgement test — 2026-10-16

- Added manager/administrator `---- struct_docapproval_compliance ----` reporting syntax.
- The report defaults to current controlled publication cycles and can switch to all historical publication snapshots.
- Added filters for page/namespace, required user, compliance status, and Published date range.
- Added publication-level Required / Acknowledged / Outstanding / Completion reporting with expandable full-name user details and snapshotted Acknowledge audience.
- Added aggregate compliance cards and a per-user compliance rollup across the filtered report set.
- Current publication rows follow the actual latest Published content revision after a Published-preserving Minor Change while retaining the original formal publication cycle.
- Historical publication evidence remains available in All Publication History even if the page is later removed from DocApproval control.
- Report data is ACL-filtered and the dashboard is nocache.
- No database migration is required.

## 0.12.6 acknowledgement test — 2026-10-15

- Added a personal `---- struct_docapproval_my ----` dashboard syntax that resolves the currently logged-in user server-side; no username/`%USER%` parameter is required or accepted.
- The shared dashboard shows four current-action queues: Acknowledgements Required, assigned Reviews Required, Training Reviews Required, and Ready to Publish.
- Training/Publisher task membership uses the same direct-user/@group assignment matching as the workflow; Review tasks use the reviewer frozen into the active review cycle.
- Outstanding acknowledgement links open the actual current Published content revision, including Published-preserving Minor Changes within the same formal acknowledgement cycle.
- Task tables show the current/published Change Summary and waiting/published timestamps.
- Added a collapsed Recently Acknowledged list for the current user.
- The dashboard is nocache and filters every item through the current user's page read ACL.
- No database migration is required.

## 0.12.5.2 acknowledgement test — 2026-10-15

- Replaced the workflow-carried Change Summary textarea with a real hidden input so theme/DokuWiki textarea CSS cannot expose it as a duplicate visible Change Summary box.
- Workflow transitions still copy the live visible Change Summary into that hidden field immediately before submit.

## 0.12.5.1 acknowledgement test — 2026-10-15

- Fixed the workflow-carried Change Summary field appearing as a second visible textarea on themes whose textarea styling overrides the HTML hidden attribute.
- The transition copy remains in the form for workflow submission and is hidden from the visible interface.

## 0.12.5 acknowledgement test — 2026-10-15

- Release Audience user chips now display the user's configured full name while retaining the login name as the stored value and hover tooltip.
- Release Audience autocomplete now shows Full Name (username) and can be searched by either full name or username; groups remain displayed as @group.
- Clicking a normal workflow transition button now saves the current Change Summary before the transition is processed, while the standalone Save Summary button remains available.
- Change Summary transition saves keep the working-revision stale-page check and the same workflow-stage edit permissions as the standalone save action.
- On Ready to Publish, New Version and the return Note were moved below Release Audience and grouped beside the Publish/Return buttons they apply to.

## 0.12.4.1 acknowledgement test — 2026-10-15

- Fixed DokuWiki component loading for Change Summary by renaming the helper/action component from `change_summary` to loader-safe `changesummary`. DokuWiki legacy plugin component names cannot contain an additional underscore after the plugin name.

## 0.12.4 acknowledgement test — 2026-10-15

- Added an editable workflow **Change Summary** that persists from Draft through Review, Training, and Ready to Publish.
- Change Summary is separate from workflow Notes and Training Notes.
- Editors/current-stage workflow owners can update the summary; changes are retained in a dedicated audit-history table.
- A normal edit after Published starts a fresh blank summary; later edits in the same working cycle carry the summary forward.
- Workflow emails include the current summary when present.
- Normal Publish snapshots the summary with the release and includes it in Notify and Acknowledgement Required emails.
- Change Summary is a hard requirement only when **Send publication notification** or **Require acknowledgement** is selected. Quiet publications with neither selected may publish without one.
- Published summaries are immutable snapshots and follow the page through Move operations.
- Added database migration 4 for current/audit Change Summary storage and the publication snapshot field.

## 0.12.3 acknowledgement test — 2026-10-15

- On-page acknowledgement reports now start collapsed to keep long user lists out of the way.
- The collapsed summary shows revision, acknowledged/required counts, and outstanding count.
- Acknowledged and pending users are displayed using their DokuWiki full name instead of login name; the login remains available as a hover tooltip.
- The Admin Acknowledgement Status outstanding-user expansion also uses full names.

## 0.12.2 acknowledgement test — 2026-10-15

- Replaced the single-value browser datalist behavior with a multi-recipient token/chip autocomplete on the Ready to Publish release audience.
- Selecting one user/group leaves the autocomplete ready for subsequent recipients; comma/Enter can also commit typed recipients.
- Added independently configurable on-page acknowledgement report visibility and report content.
- Report visibility choices include Off, Administrators, Managers + Administrators, assigned Publisher + Administrators, Editors/workflow participants + Administrators, and all logged-in users.
- Report content can show acknowledged users, pending users, or both.
- On-page reports are tied to the snapshotted current formal publication audience and show acknowledgement timestamps for completed users.

## 0.12.1 acknowledgement test — 2026-10-15

- Fixed the missing `migration3()` handler that prevented database migration 3 from running after upgrading from the acknowledgement alpha.

## 0.12 acknowledgement test — 2026-10-14

- Changed Notify/Acknowledge from live assignment roles to **publication defaults**.
- Ready to Publish now includes explicit release-audience controls. The Publisher can enable/disable and edit Notify/Acknowledge for that specific release.
- Added lightweight user/@group browser suggestions to the release fields.
- Added database migration 3 with publication-audience snapshots and per-user acknowledgement requirement rows.
- Acknowledge groups are resolved to individual users at Publish time so later group changes do not rewrite historical obligations.
- Acknowledgement boxes now use the stored publication requirement snapshot rather than current rule/group membership.
- Publication email routing now uses the stored release audience and deduplicates Acknowledge recipients from Notify.
- Acknowledge accepts authenticated users/@groups only; invalid users or unresolved group backends block Publish before the workflow commits.
- Notify continues to accept users, @groups, and literal email addresses.
- Added an Admin Acknowledgement Status report with Required, Acknowledged, Outstanding, and release-audience columns.
- Publication snapshots, requirements, and acknowledgement evidence follow page moves.
- Existing Default Notify / Default Acknowledge values remain useful as prefilled release defaults, but changing them does not alter an already Published revision.
- Published-preserving Minor Changes remain within the same acknowledgement cycle.

## 0.11 acknowledgement alpha — 2026-10-14

- Started acknowledgement work on the `feature/acknowledgements` branch.
- Added ordered `Notify` and `Acknowledge` assignment fields alongside Reviewer/Training/Publisher.
- Added database migration 2 and a dedicated acknowledgement evidence table.
- Added a bottom-of-page acknowledgement box for assigned authenticated users on the current Published revision.
- Acknowledging requires an explicit read/understood confirmation and records the publication cycle, actual viewed Published content revision, user, document version and timestamp.
- Added a diff link from the user's prior acknowledged content revision to the current Published revision when available.
- Publication sends separate deduplicated informational Notify and Acknowledgement Required audiences.
- Existing global `publish_notify` remains an additional informational publication list.
- Published-preserving Minor Edits do not create a new acknowledgement requirement.
- Acknowledgement history follows page moves while destination assignment rules remain authoritative.

## 0.10 test build — 2026-10-02

- Added page-local `~~NOREVISIONCONTROL~~` for one-off exclusions from DocApproval.
- The directive has final precedence over ordered assignment rules and renders no visible output.
- Only administrators or the assigned/resolved Publisher may add or remove the directive.
- Active non-Published workflows cannot be opted out with the directive.
- Published pages can be explicitly de-controlled while retaining all existing workflow history.
- Removing the directive allows the page's normal assignment rules to take effect again.
- Save & Sync and Reconcile Existing Pages now report page-directive exclusions separately from rule exclusions.

## 0.9 test build — 2026-10-02

- Added DW2PDF `@DOCHEADERMETA@` for templates shared by controlled and uncontrolled pages.
- Published controlled pages render a combined revision/status/date suffix.
- Working controlled pages render a combined DRAFT / NOT APPROVED suffix.
- Uncontrolled pages explicitly render `PAGE NOT SUBJECT TO REVISION CONTROL` instead of leaving the composite header metadata blank.
- Existing individual DW2PDF placeholders remain unchanged and continue to resolve blank on uncontrolled pages.

## 0.8 test build — 2026-10-01

- Reworked Move integration so destination DocApproval rules are authoritative after a page rename/move.
- Published pages moved to an uncontrolled namespace immediately become uncontrolled while retaining complete workflow history.
- Pages moved between controlled namespaces immediately adopt the destination Reviewer/Training/Publisher rules.
- Active non-Published workflows are blocked from moving into an uncontrolled destination.
- Planned/tree/namespace Move operations are pre-scanned and aborted before execution if any active controlled page would escape control.
- Exact-page DocApproval rules continue to follow their page.
- Added a fail-safe that keeps an active page controlled if a nonstandard Move/API path bypasses pre-move validation.
- Moving an uncontrolled existing page into a controlled namespace initializes the moved live revision as a new Published baseline.

## 0.7 test build — 2026-10-01

- Fixed ordinary-reader display after an administrator changes workflow state without changing page content.
- Ordinary readers now resolve the compact revision banner from an actual Published workflow record, even when a newer workflow row shares the same DokuWiki revision.
- Avoids forcing historical-revision mode when the Published content revision is already the current physical page revision.
- Removed Draft as an Admin Override target. A real page edit remains the way to start a new Draft cycle.
- Server-side validation rejects crafted Admin Override requests targeting Draft.

## 0.6 test build — 2026-09-24

- Reorganized notification emails so Page/Current status appear first, followed by View page and then the action details.
- Renamed the email Action label to Last Action to distinguish it from Current status.
- Added additional spacing after email field labels for readability.
- Moved the long previous-published diff URL onto its own line below the bold header so Outlook can wrap it cleanly.

## 0.5 test build — 2026-09-23

- Fixed Move plugin integration when the destination page ID already has a materialized DocApproval assignment row.
- Destination assignment rows are now replaced safely instead of causing a UNIQUE constraint failure.
- Removed duplicate renaming of the Struct-backed workflow data table; Struct's own Move integration already handles Struct schema data.
- The DocApproval assignment move handler is now idempotent when a move event is retried.

## 0.4 test build — 2026-09-23

- Notification emails now contain an explicit HTML body plus a plain-text fallback.
- Email field labels are bold in the HTML version.
- Page display uses the first DokuWiki headline when available, with page ID as fallback.
- Workflow actors display their configured DokuWiki real name when available, falling back to login.
- Page and diff URLs are explicit HTML hyperlinks rather than relying on mail-client auto-link detection.
- Generic workflow Comment wording is now presented as Note.

## 0.3 test build — 2026-09-21

- Added DW2PDF template placeholders for Published revision, date, publisher, status, and last Published revision/date.
- Working-revision PDF exports are explicitly marked DRAFT / WORKING DRAFT - NOT APPROVED.
- Workflow notes are now retained on successful transitions as well as returns.
- Added optional notes at Ready for Review and Training Review; publication notes are retained when Publish is selected.
- Generic action notes remain separate from the Training Note field.

## 0.2 test build — 2026-09-17

- Added compact Published banner for ordinary readers.
- Numeric document revisions display as three digits (for example, 002).
- Added configurable administrator workflow override tool.
- Kept normal workflow actions available to administrators even when the exceptional override tool is disabled.

## 0.1 test build — 2026-09-15

- Initial independent Struct DocApproval plugin.
- Draft → Review → Training → Publication workflow.
- Ordered include/exclude assignment rules with page, namespace wildcard, and regex matching.
- Existing-page reconcile/initialization.
- Reader protection of unpublished working revisions.
- Current workflow and workflow history aggregations.
- DokuWiki revision-history annotations.
- Email routing, minor-edit handling, and page-move support.
