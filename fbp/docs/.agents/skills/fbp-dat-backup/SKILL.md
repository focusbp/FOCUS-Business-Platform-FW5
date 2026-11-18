---
name: fbp-dat-backup
description: Design and validate FBP .dat development snapshots, manifest metadata, sensitive-data handling, schema authority and comparisons across environments. Tool paths and repository operations belong to the operating environment.
---

# fbp-dat-backup

Use this skill when the user asks to back up, compare, inspect, or restore from local FBP `.dat` files during development, especially under a project `docs/backup/` workspace.

## Purpose

Development backups are local comparison snapshots. They help Codex and developers compare data before/after schema or data work without relying on `.fmt` files.

They are not release artifacts, customer-facing docs, or a substitute for production backups.

Reading a `.dat` header is allowed for inspection and comparison, but it does not make `.dat` or `.fmt` the source of DB-managed schema. For DB-managed tables, `db_fields` and `screen_fields` remain the source of truth.

## Storage policy

Preferred structure:

```text
docs/backup/
  20260602_183000_a1b2c3/
    manifest.json
    common/customers.dat
    db/db.dat
```

- Use `docs/backup/<yyyymmdd_hhmmss>_<shortid>/`.
- Preserve source-relative subpaths when copying from `classes/data/`, e.g. `common/customers.dat`.
- Include only `.dat` files needed for the current task.
- Prefer `manifest.json`, not `manifest.txt`, so tools and Codex can parse it reliably.

## Security constraints

- Treat all `.dat` files as sensitive.
- Do not commit backup `.dat` files.
- Do not write passwords, API keys, URLs, login info, server names, local absolute source paths, or release commands into `manifest.json`.
- Be extra careful with `setting.dat`, `user.dat`, `remember_me.dat`, upload indexes, and any customer/person data.
- Do not take production data backups into `docs/backup/` unless the user explicitly asks and understands the sensitivity.

## Schema safety

- Use read-only tools for snapshot inspection and comparison.
- Do not use `.dat` backup inspection to bypass DB field management.
- When adding, changing, or deleting fields on DB-managed tables, switch to `fbp-db` and use `db_fields_*` / CLI. Do not directly edit `classes/data/_common/fmt/*.fmt`.
- If comparison reveals a mismatch between `.dat` header, `.fmt`, `db_fields`, and `screen_fields`, treat it as metadata inconsistency. For DB-managed tables, repair through DB CLI and clean up `screen_fields`; do not fix only `.fmt`.

## Version-control policy

Exclude sensitive snapshot contents from the project's version control using its existing ignore rules. An empty backup workspace may be tracked when the repository policy allows it. Use the environment's authorized repository workflow for ignore changes; never add data files as part of source changes.

## Manifest fields

Use a compact `manifest.json`:

```json
{
  "created_at": "2026-06-02T18:30:00+09:00",
  "purpose": "before db field edit",
  "source": "local_web",
  "files": [
    {
      "backup_path": "common/customers.dat",
      "source_relative": "classes/data/common/customers.dat",
      "size": 12345,
      "sha256": "...",
      "maxid": 33,
      "recordsize": 4472,
      "headersize": 304,
      "record_count": 28
    }
  ]
}
```

Allowed `source` values should stay generic, such as `local_web`, `local_source`, `test_export`, or `manual`. Do not include server names or URLs.

## Snapshot and comparison workflow

1. Resolve the application's actual data directory and source workspace using the environment's inventory and access rules.
2. Establish whether the source is development/test data or production-derived data, and verify that the intended snapshot scope is authorized.
3. Configure repository ignores before creating a snapshot; preserve source-relative paths below classes/data.
4. Use the environment's backup tool to copy only the needed .dat files and generate the manifest together. Refresh a manifest separately only for an existing snapshot.
5. Compare sha256, record_count, maxid, recordsize and headersize first. Inspect row values only when needed and permitted; normally report changed IDs and field names without sensitive values.
6. Verify snapshot contents remain excluded from source control. DB-managed schema repairs use fbp-db rather than editing generated fmt files.

The tool entrypoints, machine paths, backup inventory and repository commands belong to the operating environment, not this shared skill.

## Restore stance

- This workflow primarily creates and compares snapshots; replacement of current data requires an explicitly agreed restore scope.
- Back up the current data before replacement and inspect the candidate snapshot through a read-only reader.
- Prefer record/field recovery from operation logs when suitable; follow fbp-ffm-recovery.
- Production restoration requires its own agreed plan and environment-specific execution procedure.
