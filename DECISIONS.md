# Engineering Decisions

This document records the significant product and engineering decisions made during this iteration of the AI Form Builder. Each entry describes the problem, the implementation chosen, the trade-offs considered, and what we would do with more time.

---

## 1. Form Completion & Drop-off Analytics

### User problem
Form owners could see how many submissions they received, but had no idea what happened to everyone who *didn't* submit. A form with a healthy conversion funnel requires visibility into views, starts, per-step abandonment, and time-to-completion. Without this, users are blind to where friction kills submissions.

### Implementation
- Added `app/Services/FormAnalyticsService.php`, a single service that records funnel events (`view`, `start`, `step`, `complete`, `abandon`) and de-duplicates them (a visitor refreshing the page or re-advancing to a step they already saw only counts once per session).
- Wired the service into the public form experience (`app/Livewire/Forms/FormView.php`) and the non-Livewire controller path via a lightweight beacon endpoint `POST /f/{form}/analytics/beacon`. The beacon uses `navigator.sendBeacon()` from an `unload`/`pagehide` handler so abandonment is captured even when the user closes the tab — a plain fetch/AJAX call would be cancelled by the browser.
- Added `ip_address` to the analytics table (with an index) so a single visitor across pages/sessions can be attributed without exposing full IPs in the UI.
- Built a read-only dashboard (`app/Livewire/Forms/FormAnalytics.php` + route `forms.analytics`) showing views, starts, completion rate, average completion time, per-step conversion funnel, a 14-day activity series, and recent visitors. Access is gated behind the existing `view submissions` permission.

### Trade-offs
- **IP addresses are privacy-sensitive.** We record and aggregate them for funnel accuracy but only display anonymized/shortened forms in the dashboard and added no public "user profile" view. A fully cookieless approach would undercount return visitors.
- **Session-based attribution** is a pragmatic middle ground between no attribution and full account tracking. It needs a signed session cookie; when that's unavailable (some privacy browsers), we fall back to the server session ID, so numbers are approximate, not exact.
- **Synchronous writes** are cheap per event, but a high-traffic published form could add load. The current cache/database writes are fine at this scale; batching is a listed future improvement.
- Only the authenticated owner sees analytics; public visitors are never exposed to any tracking UI or opt-out (they have no account to manage it), which we accepted for this feature.

### What we'd do with more time
- Batch analytics writes via a queued job to reduce request overhead.
- Add time-bucketed funnel charts and abandonment heatmaps by field.
- Add real geo/device breakdowns and a per-visitor session timeline with privacy-preserving hashing.

---

## 2. Form Versioning & Rollback

### User problem
Editing a form is destructive: a user can tweak the schema, then later regret the change with no way to get the old layout back. Every accidental save has the potential to erase a carefully tuned form. Form builders need version history and one-click rollback the same way code editors do.

### Implementation
- Added a `form_versions` table (snapshot of the form's field schema as JSON, version number, creator, and an optional note) with a unique constraint on `(form_id, version)` to guarantee a clean, ordered history.
- Created `app/Services/FormVersioningService.php` with `capture()`/`snapshot()`, `restore()`, `diff()`, and `sameSchema()`. Key detail: a save that doesn't actually change the schema is a **no-op** — we compare the incoming schema to the latest snapshot so we don't pollute history with "Saved from builder" noise on every harmless click of the save button.
- Rollback is itself captured: restoring an older version first snapshots the current state, so rollbacks are reversible and never destroy work.
- Exposed history + preview (`app/Livewire/Forms/FormVersions.php`, route `forms.versions`, permission `edit forms`) with a side-by-side diff of the old vs. new schema before the user confirms.
- Snapshots are taken at save time from the database (`FormField::with('options')`), **not** from the cached `fields` relation, avoiding stale in-memory snapshots.

### Trade-offs
- **Schema snapshots only** — we version the form structure, not submissions or response data. That keeps versions small and cheap; it also means "rollback" restores the layout, not historical submissions, which is the correct scope for a builder.
- **Full-snapshot storage** (JSON per version) is simple and robust at this scale. A delta/diff-based store would be more compact but much more complex to reconstruct and debug.
- Version history could grow; we cap the uniqueness by `(form_id, version)` but don't yet prune old snapshots. Cleanup/retention is noted for a future pass.

### What we'd do with more time
- Add a retention policy / prune old versions beyond a configurable limit.
- Add a "restore as new form" option to fork from any historical version.
- Diff at the visual level (mini render of the old vs. new form) instead of raw JSON.

---

## 3. Spam Protection & Rate Limiting

### User problem
Published forms are public endpoints and attract spam bots: fake submissions, junk data polluting the submissions table, and high-velocity floods. Owners were spending real time deleting junk, and the noise degraded the data quality of anything downstream.

### Implementation
- Added `config/spam.php` so behavior is tunable per environment (enabled flag, honeypot field name, minimum fill time, IP velocity thresholds) without touching code.
- `app/Services/SpamProtectionService.php` layers three defenses:
  1. **Honeypot** — an invisible field that humans can't see but bots often fill; a filled honeypot hard-blocks the submission.
  2. **Time trap** — submissions faster than `min_fill_seconds` are impossible for a human; treated as blocked.
  3. **IP velocity** — more than N submissions from the same IP in a window flags the submission as spam (`is_spam`) instead of silently dropping it, so legitimate behavior (e.g., a user submitting twice) is preserved.
- Wired into both the Livewire public form and the non-Livewire controller `submit()` path, so no entry point bypasses protection.
- Flagged submissions are stored but surfaced separately: the submissions table now has a spam filter (all / legit / spam), a visible `SPAM` badge on flagged rows, and a quick action to toggle a submission's spam status or delete it. Nothing is ever permanently lost.

### Trade-offs
- **Blocking vs. flagging** is a deliberate split: hard blocks are reserved for certain bot signals (honeypot, time trap), while velocity triggers a *flag* that the owner reviews. Hard-blocking on velocity would risk dropping a power user behind a shared/NAT IP.
- **Time-trap requires a client-side timestamp**, which we pass along from the render. Tampering is possible, which is why velocity + honeypot still catch the stubborn cases — it's defense in depth, not a single wall.
- **IP-based velocity has a false-positive surface** on shared networks, which is exactly why it only flags and is configurable.
- Disabled by default in the test environment (`SPAM_ENABLED=false` in phpunit.xml) so tests that submit instantly don't trip the time trap; feature tests opt in explicitly.

### What we'd do with more time
- Add browser-fingerprint heuristics and a simple CAPTCHA/custom puzzle for repeated offenders.
- Record spam decisions in analytics so owners see the spam volume as a metric.
- Add per-form IP allow/deny lists and per-user submission limits for logged-in forms.
- Move velocity counters to Redis TTL keys (already structured to support this) and add distributed locking for high-traffic forms.

---

## Test coverage added
- `tests/Feature/FormAnalyticsTest.php` — funnel recording, de-duplication, dashboard summary/step funnel/activity series, owner exclusion. (8 tests)
- `tests/Feature/FormVersioningTest.php` — snapshot capture, no-op on unchanged schema, rollback + pre-rollback snapshot, field rebuild, diff. (7 tests)
- `tests/Feature/SpamProtectionTest.php` — honeypot block, time-trap block, legit pass, IP velocity flag, controller-path behavior, spam filter UI. (7 tests)

Full suite: **75 passed** (up from 53 baseline); `vendor/bin/pint` clean.
