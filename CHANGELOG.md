# Changelog

## Unreleased

Repository established as the authoritative source for Struct DocApproval.

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
