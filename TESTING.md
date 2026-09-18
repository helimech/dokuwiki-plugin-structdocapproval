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
