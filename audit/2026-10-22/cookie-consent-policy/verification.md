# Cookie Manager consent and privacy verification

Local verification only; no production deployment or Browserless requests.

- PHP receipt-store fixture: signed states, expiry, revision checks, withdrawals, journal and policy integrity, authorization, retention, scan isolation.
- Simulated forbidden web.config on a LiteSpeed server: receipt and signed choice saved. On IIS, missing protection correctly prevents saving, public response omits technical details and administrator receipt view identifies web.config. Saving works after restoring the IIS guard.
- PHP shortcode fixture using native WordPress hooks: all fields are optional. Empty, name-only and email-only profiles render the policy without an incomplete-notice placeholder, empty field labels or invented transfer declarations. The form has no required markers or attributes.
- Real PHP HTTP server and Chrome: initial verification error leaves the banner without diagnostics or retry controls; an explicit choice refreshes failed state and persists successfully. Accept, reject, custom choice, revocation, reload, nonce refresh, failed-save recovery, CSRF, receipt export and integrity passed.
- Saved the identity-only profile through the actual settings form with recipients, transfers and log retention left empty; reloaded the backend and verified that the public shortcode includes the saved identity. Desktop and 390px layout checks passed; screenshots saved alongside this file.
- PHP syntax, JavaScript syntax, consent runtime, administrator receipt UI and git diff --check passed.

The exact permissions/security rule on the reported production host remains unverified. Following the user instruction, no profile input is required for saving or shortcode output; existing saved data were not discarded.
