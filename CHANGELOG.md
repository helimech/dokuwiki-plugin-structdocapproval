# Changelog

## Unreleased

Repository established as the authoritative source for Struct DocApproval.

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
