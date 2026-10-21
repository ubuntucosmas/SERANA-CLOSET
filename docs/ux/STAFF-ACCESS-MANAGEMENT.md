# Staff access management

All controls are available to the owner in **Studio → Staff**, on each staff member's card. Managers, cashiers and customer accounts cannot use the management endpoints. Customer accounts cannot be selected as targets.

## Reset sign-in password

Select **Reset sign-in password**, enter and confirm a temporary password or leave both fields blank to generate one, and provide a reason. Confirm the reset. The temporary credentials appear in the sign-in-details panel, which receives focus and remains visible until dismissed or the page is left.

The previous password, account sessions, shared-tablet PIN sessions and outstanding email reset links are revoked. The staff member signs in with the temporary password and must choose a different permanent password before working. Their stored Sales Desk PIN is unchanged, but PIN unlock/approval is blocked until the password change is complete.

Owners change their own password through **Change my password**; the management endpoint refuses self-reset. Existing last-owner role/suspension protections remain in place. Email-based password recovery clears the temporary-password requirement after the person chooses a different password and records an access-history entry.

## Edit staff details

Select **Edit details**, update the name/email, enter a reason and save. Email addresses must be unique. Changing a sign-in email requires confirmation, clears email verification, invalidates old email reset links and ends previous sessions. Name-only edits preserve access. If an owner edits their own sign-in email, the current session is rotated and retained while other sessions end.

Staff self-service profile edits use the same audit and revocation rules. This endpoint cannot change roles or permissions; those remain separate owner-only actions.

## End active sessions

Select **End sessions**, enter the reason and confirm. Account sessions and shared-tablet operator sessions are rejected on their next request. Remember-me tokens are rotated. The person can sign in/unlock again using their existing credentials, provided the account is active and no temporary password is outstanding.

When ending their own sessions, the owner is signed out too. The account remains active, including when it is the last owner. Revocation does not close registers, delete saved orders or change recorded money/stock. Requests already in progress are not cancelled.

## Access history

Select **Access history** to see who created or edited access, issued/recovered/changed a password, changed a role/PIN, suspended/reactivated the account or ended sessions. Entries show Nairobi time, actor, reason and allow-listed before/after values, newest first, 20 per page.

Passwords, hashes, PIN values, tokens, IP addresses and raw audit payloads are not returned. Credential changes record only factual markers such as `password_changed: true`. The existing append-only audit table remains authoritative.

## Implementation and rollout

`users.session_version` is an additive integer column, initially zero. Login and PIN unlock stamp the current version in their sessions; middleware compares against the current database value. Incrementing the version invalidates old sessions independently of the configured session driver. Password changes retain only a newly rotated current session. Suspending an account also advances the version, so reactivation cannot restore old access.

The migration was applied to the local DDEV database. Other environments must run the migration with their deployment:

```sh
php artisan migrate --force
```

No payment, stock, register or order schema/service rules were changed.

## Verification

- Complete backend regression: **555 passed, 17 skipped, 9,431 assertions**. Skipped concurrency tests require MariaDB row locks rather than the default SQLite test database.
- All **11 frontend test files** pass; production build passes; `git diff --check` is clean.
- `StaffAccessManagementTest` covers chosen/generated resets, mandatory password replacement, stale web/JSON/PIN sessions, remember-token rotation, email changes, self-service changes, suspension/reactivation, owner-only permissions, customer-target rejection, self-reset protection and redacted paginated history.
- Three browser walkthroughs pass: desktop light/dark and mobile, with no page overflow, uncaught JavaScript errors or axe findings on scanned desktop surfaces. Browser actions replay synthetic server-generated fixtures; integration tests exercise the real endpoints in an isolated database.

[Browser results](verification/staff-access-results.json) · [Mobile access history](verification/staff-history-375-light.png) · [Desktop password reset](verification/staff-reset-1440-light.png)
