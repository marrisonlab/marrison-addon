# Cookie Manager second pass

Probe: `cookie-probe.php`; output: `cookie-probe-output.txt`. LocalWP runtime uses PHP 8.2.29 and the shared rollback harness; outbound HTTP is intercepted by `pre_http_request` with a controlled `WP_Error`. No plugin files or persistent WP data were changed.

## Confirmed runtime behavior

- `Marrison_Cookie_Scanner::perform_scan()` completes with DB storage (`local.wp_marrison_cookies`), reads 48 existing records and reports the controlled homepage failure as `Richiesta home fallita: External HTTP disabled in isolated audit harness.`
- The scanner pipeline is current `$_COOKIE` → homepage `Set-Cookie` response → fixed WordPress patterns → fixed third-party patterns. It does not parse homepage HTML, inline JavaScript, DOM-created cookies or arbitrary URLs. This is a coverage limit to keep separate from the successful DB scan.
- With no consent, analytics scripts and marketing iframes are rewritten to `type="text/plain"`/`data-marrison-blocked-src` and retain unrelated attributes such as `nonce`.
- `accept_all` preserves the original tags. Custom `necessary|analytics` preserves the analytics script and blocks the marketing iframe.
- Nonce refresh and consent-save are public AJAX actions, required for anonymous visitors and cached-page recovery. Scanner scan/delete write endpoints have no `nopriv` registration.

## Additional confirmed issue

`class-cookie-consent.php:97` rewrites iframe `src` only when the attribute value is quoted. The real filter was tested with both forms:

- `src="https://www.youtube.com/embed/test"` becomes `data-marrison-blocked-src` and has no executable `src`;
- `src=https://www.youtube.com/embed/test` is classified as marketing but keeps its original `src` alongside the blocked marker.

The unquoted marketing iframe can therefore load before consent. This is reproduced in `cookie-probe-output.txt` (`unquoted marketing iframe has src removed: ok=false`). Source evidence: `class-cookie-consent.php:89-99`.

The frontend reactivation routine `assets/js/frontend.js:393-412` drops every `type` attribute at line 398. Browser confirmation now reproduces the bug: a valid blocked type=module analytics script with export is recreated as classic, raises Unexpected token 'export' at activateBlockedScript line 411, and its counter stays 0 while allowed classic analytics/marketing counters reach 1.

## Exact frontend fixture

On a clean browser profile, open a page where Cookie Manager is active and inject this markup before page load (or place it in an HTML widget):

```html
<script id="audit-analytics" src="/audit-tracker.js"></script>
<script id="audit-marketing" src="https://www.youtube.com/audit-tracker.js"></script>
<iframe id="audit-youtube" src="https://www.youtube.com/embed/test"></iframe>
<button id="audit-reject">reject</button>
<button id="audit-accept">accept</button>
```

Add a second iframe fixture with `src=https://www.youtube.com/embed/test` without quotes to reproduce the confirmed unquoted-src issue. Add a `type="module"` analytics fixture using an `export` statement and a counter to verify whether reactivation changes its execution semantics.

Serve `/audit-tracker.js` locally with a counter endpoint or use an inline tracker that increments `window.auditTrackerRuns`. Clear `marrison_cookie_consent` and `marrison_cookie_categories`, reload, and verify the marketing script/iframe do not execute or request their source before consent. Click `#marrison-reject-all`; reload and verify the counter remains unchanged. Clear the consent cookies, reload, click `#marrison-accept-all`, and verify blocked script/iframe nodes are replaced/activated once. Repeat with custom preferences selecting Analytics only: analytics must run, YouTube/marketing must remain blocked.

For cache/nonce validation, save a page response, wait until the embedded nonce is stale, submit accept/reject and open preferences. Browser network should show one retry through `action=marrison_cookie_refresh_nonce`, followed by the original request with the refreshed nonce; no second refresh loop should occur.

## Coverage limits

The additional local browser fixture exercised real AJAX Reject all, Accept all and Custom necessary+analytics with a deliberately invalid nonce; all three recover and save. Classic counters behave as expected, the quoted iframe is blocked/activated correctly, the unquoted iframe loads before consent, and type=module reactivation fails. All tracker destinations are harmless local fixtures. Evidence: [browser-results.json](C:/Users/Angelo/Documents/GitHub/marrison-addon/audit/2026-10-01/second-pass/browser-results.json). External trackers, dynamically inserted tags and cross-domain cookies remain unverified. The test consent state is restored and fixtures removed after testing.
