# Struct DocApproval

A controlled-document workflow plugin for DokuWiki backed by the Struct database.

> **Status:** active test build. The workflow is working on a test server, but the plugin is still under development.

## Workflow

`Draft → Awaiting Review → Awaiting Training Review → Ready to Publish → Published`

- Editors are governed by normal DokuWiki ACLs.
- A draft is submitted to an eligible reviewer.
- Reviewers can **Approve** or **Return for Changes**.
- Training performs a release-readiness review and records the training impact.
- Publishers can **Publish**, **Return to Training**, or **Return to Draft**.
- Ordinary readers continue to see the last Published revision while a newer working revision moves through the workflow.
- Numeric document revisions are normalized to three digits, e.g. `2 → 002`.
- The exceptional administrator workflow override tool can be enabled or disabled in plugin configuration.

## Assignment rules

Rules are processed top-to-bottom. A pattern can be:

- an exact page, e.g. `policy:humanresources:vacation`
- a namespace wildcard, e.g. `policy:**`
- a full regular expression

Each include rule has Reviewer, Training, and Publisher fields. Later matching nonblank values override earlier ones; blank fields inherit. An exclude rule removes matching pages from the workflow. A later include rule can re-include them.

Existing pages newly brought under control are initialized as Published. New pages created later inside a controlled area begin as Draft.

## Reader view

Ordinary readers see a compact publication banner:

`Published Revision: 002 {date}`

Editors and workflow participants see the full workflow tracker and the actions relevant to their current stage.

## Minor edits

A DokuWiki **Minor Change** on a Published page may preserve Published status when made by an administrator or assigned Publisher and when the option is enabled. Minor edits during Draft, Review, Training, or Ready-to-Publish return to Draft like other content changes.

## DW2PDF integration

Struct DocApproval adds document-control placeholders to DW2PDF templates through DW2PDF's `PLUGIN_DW2PDF_REPLACE` event. No changes to DW2PDF itself are required.

Available placeholders:

- `@DOCREV@` — Published document revision; `DRAFT` when exporting an unpublished working revision
- `@DOCDATE@` — publication date
- `@DOCPUBLISHEDBY@` — publisher display name
- `@DOCSTATUS@` — `Published` or `WORKING DRAFT - NOT APPROVED`
- `@DOCLASTPUBREV@` — last Published document revision
- `@DOCLASTPUBDATE@` — last Published date

Example `footer.html`:

```html
<table width="100%">
  <tr>
    <td>@ID@</td>
    <td style="text-align:center;">Revision @DOCREV@</td>
    <td style="text-align:right;">@DOCSTATUS@ &nbsp; @DOCDATE@ &nbsp; Page @PAGE@ of @PAGES@</td>
  </tr>
</table>
```

A working revision exported by a workflow participant is deliberately marked `DRAFT` / `WORKING DRAFT - NOT APPROVED`. Ordinary readers continue to be restricted to the last Published revision.

## Notification email formatting

Workflow notification emails are sent with both plain-text and HTML bodies. The HTML version uses bold field labels, explicit hyperlinks for page/diff links, the page's first heading as its display title (falling back to the page ID), and the DokuWiki user's configured real name instead of the login wherever available. The plain-text part remains available for clients that do not render HTML.

## Workflow notes

The generic workflow Note is recorded on successful actions as well as returns. Notes are optional for Ready for Review, Approve, Training Review, and Publish, but remain mandatory for Return for Changes, Return to Training, Return to Draft, and Admin Override. Training Note remains a separate field because it describes the training disposition/release-readiness action.

## Dashboard syntax

Current workflow / work queue:

```
---- struct_docapproval ----
----
```

Workflow history with a page selector:

```
---- struct_docapprovalhistory ----
----
```

## Installation

1. Install the plugin as `lib/plugins/structdocapproval/`.
2. Ensure the DokuWiki **Struct** plugin is installed and enabled.
3. Open a normal wiki/admin page once so the migration can create the internal schema and tables.
4. Configure rules under **Administration → Struct DocApproval**.
5. Sync or reconcile existing pages.
6. Test on a non-production wiki before broad deployment.

The old `structpublish` tables are not read or modified by this plugin.

## Plugin ID and storage

DokuWiki plugin ID/folder: `structdocapproval`

Internal schema/table prefix: `struct_docapproval`

## Testing

See [TESTING.md](TESTING.md) for the current smoke-test checklist.

## License

GPL-2.0.
