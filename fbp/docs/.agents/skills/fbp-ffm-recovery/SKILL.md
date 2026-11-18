---
name: fbp-ffm-recovery
description: Recover FBP fixed_file_manager records from operation logs, including insert undo, full or selected-field update restoration, deleted-record recovery, conflict checks and verification. Environment-specific clients and target resolution follow operating procedures.
---

# FFM operation-log recovery

Use for recovery of overwritten, inserted or deleted records through the framework's operation-log API, or for implementation and verification of that recovery behavior.

## Scope and target

- Identify the application, environment, DB class/table, record ID, transaction and requested fields before changing data. Inspecting a log does not authorize restoring it.
- Use the environment's authenticated client and target-selection procedure. Do not rely on an unrelated default application's credentials or confuse a test management service with the target application's environment.
- Logs at classes/log/ffm/*.jsonl contain sensitive before/after values. Read only the necessary entries and do not expose credentials or entire sensitive records.

## Inspect before restoring

Confirm the log's table/class/id, operation, before/after values and request.class/request.function, then compare them with the current record. A missing active record can mean an X-flag deletion, not physical removal. Select the transaction whose before values match the intended state.

## Restore modes

- `insert_undo`: undo an accidental insert through normal FFM deletion. The record should no longer be active after recovery.
- `update_partial`: restore only the named fields from the logged before values. Prefer this for a mistaken field and verify that unselected fields retain their current values.
- `update_full`: restore the whole logged before row only when that is the agreed scope.
- `delete_restore`: reactivate an X-flag-deleted record through restore_deleted_record(), preserving the original ID and record ordering.

Normal conflict checking rejects an update restore if the current values no longer match the logged after state. Do not disable it without explicit acceptance of the identified newer values that would be overwritten. If a response is uncertain, read the current record and recovery logs before retrying.

## Verification

- Read back the same table/id in the same application and environment; verify restored fields, unchanged other fields, active/deleted state and the recovery operation's log entry.
- When changing recovery behavior, use isolated records to test A→B→C conflict refusal, full/partial restore, a deleted middle ID with intact neighbors, and rejection of an already-restored deletion.
- Do not run allclear/changeFormat recovery tests against real production tables. Production-safe test rows require explicit scope and still do not authorize destructive table tests.
- An operation response alone does not prove the expected values were restored. Report unknown or unverified outcomes accurately.

## Report

Report application/environment, table/id, operation/mode, relevant transaction IDs, a minimal field-level before/after summary, verification outcome and whether the recovery itself was logged. Temporary credential/payload handling and cleanup follow the operating environment; never include secrets or a full sensitive row in the report.
