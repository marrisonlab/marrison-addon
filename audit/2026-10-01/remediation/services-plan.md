# Remediation plan: Calendar Sync and GitHub updater

Scope: analysis only. No production/plugin file was changed. The proposed changes are intentionally limited to the existing flows and APIs.

## Calendar Sync

### 1. Restrict public ICS reads

Current path: `class-marrison-addon-calendar-sync.php:199-212`. `download_ics()` accepts any ID for which `get_post()` returns an object. The rollback probe obtained a complete ICS for a temporary `draft` post, including title, excerpt and permalink.

Minimal change:

- load `$post = get_post( $post_id )` once;
- reject when the post is missing or `get_post_status( $post_id ) !== 'publish'`;
- reject when `post_password_required( $post )` is true;
- keep the endpoint public for ordinary published, unprotected events.

Do not use `current_user_can()` as the only guard: that would make ordinary public calendar links unusable for anonymous visitors. Test draft, private, future, password-protected published, and ordinary published posts; assert only the last one emits `BEGIN:VCALENDAR`.

### 2. Preserve shortcode meta overrides in ICS links

Current path: `calendar_link_shortcode()` accepts `start_meta` and `end_meta` at `:168-190`, but `get_ics_url()` at `:387-394` emits only the post ID. The Google link uses the effective override, while the ICS request falls back to saved admin settings at `:210-212`.

Minimal change:

- extend the existing `get_ics_url()` argument list with effective `start_meta`, `end_meta`, and `location`;
- add those values to the existing `add_query_arg()` result using sanitized strings;
- in `download_ics()`, read the query values when non-empty and otherwise use settings;
- do not trust query values for post identity or bypass the publication/password guard.

Runtime test: configure admin keys A/B, create a published event with keys C/D, render `[evento_calendario_link type="ics" start_meta="C" end_meta="D" location="Sala 1"]`, fetch the resulting URL, and assert the ICS uses C/D and `LOCATION:Sala 1`.

### 3. Emit ICS location

Current path: `:251` always writes `LOCATION:` with an empty string, although shortcode `location` is already accepted and passed into Google URL generation.

Minimal change: resolve the effective location from the ICS query argument described above, sanitize it as text, and write `LOCATION:` through the existing `ics_escape()` helper. Empty location should continue to emit an empty field for compatibility.

### 4. Reject blank meta settings

Current path: `sanitize_settings()` at `:82-92` preserves explicitly submitted empty `start_meta`/`end_meta`, so future event lookups use an empty meta key.

Minimal change: trim and sanitize each submitted value, then use the corresponding default when the result is empty. Apply the same fallback to shortcode override values before calling `get_post_meta()`.

### 5. Legacy invalid timezone safety

Current path: `get_ics_timezone_object()` at `:332-340` calls `new DateTimeZone( $timezone_id )` without catching `Exception`; an invalid legacy option causes a fatal request. The current settings sanitizer protects new saves but does not repair old/tampered options.

Minimal change: wrap timezone construction in `try/catch ( Exception $e )` and fall back to the site timezone; if the site timezone itself is invalid, fall back to `UTC`. Keep the existing select values and `Etc/GMT` mapping unchanged.

Runtime test: inject an invalid stored timezone inside the rollback transaction, call the private formatter or ICS endpoint, and assert a valid ICS is returned with the site/UTC timezone and no exception.

## GitHub updater and filesystem layout

### Existing flow

`Marrison_Addon_Updater::__construct()` registers `upgrader_source_selection` at `:31-33`. `fix_folder_name()` at `:35-58` derives `marrison-addon` from the plugin slug and moves the extracted source to `$remote_source/marrison-addon/`.

### Minimal robustness changes

- Keep the existing `hook_extra['plugin'] === $this->slug` guard.
- Normalize source and destination with `trailingslashit()` before comparing.
- Check that `$wp_filesystem` is available after `WP_Filesystem()`; return the original source when initialization fails.
- If source already equals destination, return it unchanged.
- Before moving, verify source exists and destination does not already contain a conflicting directory. Use the existing `WP_Filesystem` methods (`exists`, `is_dir`, `move`) rather than direct filesystem calls.
- Check the boolean result of `move()`. Return the new path only on success; return the original source on failure so WordPress reports the actual upgrade failure instead of proceeding with a nonexistent source.
- Do not delete or overwrite an existing destination automatically.

Suggested tests use a fake filesystem object and temporary extraction paths: unrelated plugin hook, already-correct folder, successful versioned-folder move, missing source, destination collision, and failed move. The test must assert the returned source path and that no existing destination is overwritten.

### Updater cache/network checks

`get_github_version()` already negative-caches HTTP/JSON failures for 30 minutes and success for six hours (`:67-112`). Runtime tests should mock `wp_remote_get()` with: `WP_Error`, HTTP 500, HTTP 200 malformed JSON, HTTP 200 missing `tag_name`, and valid `tag_name`; assert the corresponding transient state and no update response for failures. `clean_cache()` at `:60-65` should be called after a completed upgrade and verified to clear success/failure/info transients.

## Regression risks

- Adding meta/location query arguments changes the generated ICS URL and therefore UID only indirectly; keep UID based on event URL unless a deliberate compatibility change is approved.
- Public calendar feeds must remain available for published events while private/password-protected content stays inaccessible.
- A failed updater move must stop the install cleanly; returning a guessed destination would hide the real filesystem error.
- Do not broaden updater changes into release parsing or remote asset fetching; those flows are separate from folder layout.
