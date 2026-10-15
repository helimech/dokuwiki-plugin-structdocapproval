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

Each include rule has Reviewer, Training, Publisher, Default Notify, and Default Acknowledge fields. Later matching nonblank values override earlier ones; blank fields inherit. An exclude rule removes matching pages from the workflow. A later include rule can re-include them.

Existing pages newly brought under control are initialized as Published. New pages created later inside a controlled area begin as Draft.

Reviewer, Training, and Publisher are workflow-routing assignments. Default Notify and Default Acknowledge are only publication defaults: when a page reaches Ready to Publish, the Publisher sees those values prefilled and may change or disable either audience for that specific release.

## Page-level exclusion

For one-off exceptions, place the following directive on its own line in the page:

```
~~NOREVISIONCONTROL~~
```

The directive has final precedence over the ordered assignment rules and renders no visible page content.

- Only an administrator or the page's assigned/resolved Publisher may add or remove it.
- It may be added to an uncontrolled page or a controlled page whose workflow is currently Published.
- It cannot be added while the page is Draft, Awaiting Review, Awaiting Training Review, or Ready to Publish.
- All existing DocApproval workflow history is retained when the page becomes uncontrolled.
- Save & Sync and Reconcile Existing Pages both honor the directive.
- Removing the directive from a page that matches a control rule brings the page back under DocApproval; that save enters the normal Draft workflow, preserving the prior live revision as a Published baseline when needed.

## Page moves

Workflow history follows the page when it is renamed or moved.

- Published page → controlled destination: allowed; the destination's ordered workflow/default rules become authoritative for future releases. Existing publication-audience snapshots and acknowledgement evidence follow the page unchanged.
- Published page → uncontrolled destination: allowed; active DocApproval control is removed immediately, while all workflow history is retained.
- Draft / Awaiting Review / Awaiting Training / Ready to Publish → controlled destination: allowed; the active workflow and history are preserved and destination routing rules apply to later stages.
- Draft / Awaiting Review / Awaiting Training / Ready to Publish → uncontrolled destination: blocked before the move begins.
- Exact-page assignment rules follow the renamed page.
- Moving an uncontrolled existing page into a controlled namespace initializes its current live revision as the Published baseline.

An already assigned reviewer remains attached to the current Awaiting Review workflow record; later Training/Publisher permissions come from the destination rules.

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
- `@DOCHEADERMETA@` — complete header suffix. Controlled pages show revision/status/date; uncontrolled pages show `PAGE NOT SUBJECT TO REVISION CONTROL`

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

For mixed controlled/uncontrolled templates, a header can use:

```html
<td style="text-align: right">@TITLE@ @DOCHEADERMETA@</td>
```

Examples:

- Published controlled page: `Title -- REV: 009 (Published 2026-10-02)`
- Working controlled page: `Title -- REV: DRAFT (WORKING DRAFT - NOT APPROVED)`
- Uncontrolled page: `Title -- PAGE NOT SUBJECT TO REVISION CONTROL`

The original individual DocApproval tokens remain available. On uncontrolled pages those individual fields continue to resolve blank so existing templates do not repeat the warning in every field.

## Change Summary

A working controlled revision has a separate **Change Summary** field in the workflow banner. It is intended to explain the revision in plain language for reviewers and release recipients; it is separate from per-action workflow Notes and from the Training Note.

- A normal edit of a Published page starts a new Draft cycle with a blank Change Summary.
- Further content edits within that same Draft/Review/Training/Ready-to-Publish cycle carry the summary forward.
- Editors can maintain it during Draft. The assigned Reviewer, Training role, Publisher, and administrators can maintain it while they own the corresponding workflow stage.
- Summary edits are retained in a dedicated audit history.
- Workflow notification emails include the current Change Summary when one exists.
- On normal Publish, the Change Summary is snapshotted with the publication and included in both informational Notify and Acknowledgement Required emails.
- **The summary is required only when either Send publication notification or Require acknowledgement is selected.** A quiet grammar/administrative revision with neither release communication option selected may Publish with a blank summary.
- Once Published, the publication snapshot is fixed. A new normal content edit starts the next summary cycle.

## Publication notification and acknowledgement

The release audience is selected and snapshotted when a normal workflow **Publish** action occurs.

At Ready to Publish the Publisher sees:

- **Send publication notification** — accepts DokuWiki users, `@groups`, and literal email addresses.
- **Require acknowledgement** — accepts authenticated DokuWiki users and `@groups` only.
- Both fields are prefilled from the page's Default Notify / Default Acknowledge assignment values, but the Publisher may change or disable them for that publication.
- The publication fields use a token/chip autocomplete. After selecting one user/group the input remains available for the next recipient; comma or Enter also commits typed values.

On Publish:

- the selected Notify/Acknowledge specs are stored with the publication;
- every Acknowledge user/group is resolved to individual user accounts and snapshotted as the required audience for that publication;
- Acknowledge recipients receive an action-required email and automatically count as notified;
- Notify-only recipients receive an informational publication email;
- overlap is deduplicated so a person receives only the acknowledgement-required message;
- the acknowledgement box appears at the bottom of the Published page only for users in that publication's requirement snapshot.

Acknowledgement evidence records the formal publication-cycle revision, the actual Published content revision viewed, document version, user and timestamp. Historical requirement/acknowledgement rows are retained.

Changing Default Acknowledge or group membership after publication does **not** rewrite the audience for an already Published revision. The next Publish uses the then-current defaults and group membership. This preserves a defensible audit answer to "who was required to acknowledge this revision when it was released?"

Draft/Review/Training changes do not invalidate acknowledgement of the prior Published revision. A Published-preserving Minor Change also does not create a new acknowledgement requirement; the formal publication cycle remains the same.

Struct DocApproval Admin includes an **Acknowledgement Status** table for current formal publications showing Required, Acknowledged, Outstanding, and the stored release audience. Outstanding users can be expanded inline.

The Published page can also show a **collapsed Acknowledgement Report**. Its summary shows the revision/completion counts; expand it to see the user lists. Report users are displayed by their DokuWiki full name, with the login retained as a hover tooltip. Configuration separates access from content:

- **Who can see the on-page acknowledgement report:** Off, Administrators, Managers + Administrators, assigned Publisher + Administrators, Editors/workflow participants + Administrators, or all logged-in users.
- **What the report shows:** acknowledged users, pending users, or both.

The default is Managers + Administrators and Both.

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
