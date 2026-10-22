from pathlib import Path
import re
root=Path(__file__).resolve().parents[3]
plugin=root/'marrison-addon'
p=plugin/'marrison-addon.php';s=p.read_text(encoding='utf8');s=s.replace('Version: 1.3.73','Version: 1.3.74').replace("const VERSION = '1.3.73'", "const VERSION = '1.3.74'");p.write_text(s,encoding='utf8',newline='\n')
notes='''### 1.3.74
* Skip trashed/private sample pages and record removed 404/410 routes separately, without marking a completed scan incomplete. Keep genuine network/server failures visible.
* Add a structured Privacy Policy generator in settings and an optional, skippable wizard step. Include controller/contact details, purposes and legal bases, retention, providers/transfers, DPO, rights and optional marketing/automated processing. Flag missing inputs.
* Generate fuller Cookie Policy text and observed browser-storage details. Use paragraphs, bold text and lists instead of headings. Keep existing user-created pages and shortcodes.
* Remove duplicate shortcode controls from Banner and remove the banner close X/note. Preserve compact mobile actions and explicit rejection.
* Hide the scanner progress bar on completion and show a clear final state; reduce explanatory text. Display readable consent receipts with integrity status; retain the technical JSON export.

'''
for p in [plugin/'README.md',root/'README.md']:
    s=p.read_text(encoding='utf8');s=s.replace('**Stable tag:** 1.3.73','**Stable tag:** 1.3.74').replace('**Version:** 1.3.73','**Version:** 1.3.74')
    s=s.replace('### 1.3.73',notes+'### 1.3.73',1)
    s=s.replace('The same copy controls appear in the wizard and banner settings, without creating pages automatically.','The same copy controls appear in the wizard; no pages are created automatically.')
    old='    *   **Policy texts** stores the controller-approved HTML used by full-policy shortcodes, plus a revision label for significant changes. No invented controller, third-party or analytics claims are generated. Configure the actual purposes, providers, legal bases, recipients/transfers, retention and rights for the site. Existing legal pages built in Elementor can keep their content and use the list-only shortcode. Evidence includes configured texts, service rules and the saved content/Elementor source of local linked policy pages; it does not capture a rendered external policy page.'
    new='    *   **Privacy and policies** generates the Privacy Policy from structured controller details, actual processing, legal bases, retention and provider/transfer settings. The wizard step is optional; incomplete required information is flagged. Cookie Policy text, current scan inventory and preferences access are generated automatically. Shortcode content uses basic paragraphs/bold/lists without headings. Existing independently written Elementor pages remain user-managed. Receipts preserve the generator inputs/text and the configured local policy page sources; their admin view shows readable choices and integrity results.'
    s=s.replace(old,new);p.write_text(s,encoding='utf8',newline='\n')
p=plugin/'readme.txt';s=p.read_text(encoding='utf8').replace('Stable tag: 1.3.73','Stable tag: 1.3.74')
s=s.replace('= 1.3.73 =','''= 1.3.74 =
* Skip deleted 404/410 scan routes without false incomplete results; keep genuine failures visible.
* Add structured privacy generation and a skippable wizard step, richer Cookie Policy and heading-free shortcode content.
* Remove duplicate Banner shortcodes and banner close X/note. Show a clear scan completion state and readable consent receipts.

= 1.3.73 =''',1)
s=s.replace('== Upgrade Notice ==\n','''== Upgrade Notice ==

= 1.3.74 =
Configure the new Privacy and policies fields to generate your notice. Custom policy textareas are replaced; independent policy pages remain user-managed.
''',1)
# WordPress readme stays below its size limit; the complete history remains in README.md.
s=re.sub(r'\n= 1.3.70 =.*?\n\n== Upgrade Notice ==', '\n\n== Upgrade Notice ==', s, flags=re.S)
p.write_text(s,encoding='utf8',newline='\n')
source=(root/'audit/2026-10-21/cookie-background/package-addon.py').read_text(encoding='utf8')
source=source.replace("version = '1.3.73'", "version = '1.3.74'")
Path(__file__).with_name('package-addon.py').write_text(source,encoding='utf8',newline='\n')
