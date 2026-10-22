<?php

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\Settings;
use App\Models\Site;
use App\Services\SmsToolsService;
use App\Services\GeminiService;
use App\Services\GeminiModelCatalog;
use App\Services\MaintenanceVerificationSchedule;
use App\Models\MaintenanceMonitorState;

require_admin_login();

$flash = null;
$smsTestLog = [];
$smsTestResult = null;
$smsTestPhone = '';
$smsTestSender = '';
$smsTestMessage = 'Test SMS da Commander';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $flash = ['type' => 'error', 'message' => 'Token di sicurezza non valido.'];
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_gemini_settings' || $action === 'test_gemini_api') {
            $apiKey = trim($_POST['gemini_api_key'] ?? '');
            $model = GeminiModelCatalog::normalize((string) ($_POST['gemini_default_model'] ?? ''));
            if (!GeminiModelCatalog::isSelectable($model)) {
                $flash = ['type' => 'error', 'message' => 'Scegli un modello Gemini valido dall’elenco.'];
            } elseif ($action === 'test_gemini_api') {
                $test = GeminiService::testConnection($apiKey, $model);
                $flash = ['type' => $test['success'] ? 'success' : 'error', 'message' => $test['message']];
            } else {
                $saved = Settings::setGeminiDefaultModel($model) && Settings::setGeminiApiKey($apiKey);
                $flash = ['type' => $saved ? 'success' : 'error', 'message' => $saved ? 'Impostazioni Gemini salvate.' : 'Salvataggio Gemini non riuscito.'];
            }
        } elseif ($action === 'save_update_settings') {
            Settings::setGithubToken(trim($_POST['github_token'] ?? ''));
            $pluginConfig = [];
            $slugs = (array) ($_POST['update_plugin_slug'] ?? []);
            $users = (array) ($_POST['update_github_user'] ?? []);
            $files = (array) ($_POST['update_plugin_file'] ?? []);
            $tested = (array) ($_POST['update_tested'] ?? []);
            $requires = (array) ($_POST['update_requires_php'] ?? []);
            foreach ($slugs as $idx => $slugRaw) {
                $slug = trim((string) $slugRaw);
                if ($slug === '') {
                    continue;
                }
                $pluginConfig[$slug] = [
                    'github_user' => trim((string) ($users[$idx] ?? '')),
                    'plugin_slug' => $slug,
                    'plugin_file' => trim((string) ($files[$idx] ?? '')),
                    'tested' => trim((string) ($tested[$idx] ?? '')),
                    'requires_php' => trim((string) ($requires[$idx] ?? '')),
                ];
            }
            Settings::setUpdatePluginConfig($pluginConfig);
            $flash = ['type' => 'success', 'message' => 'Impostazioni aggiornamenti salvate.'];
        } elseif ($action === 'save_mcu_repository_settings') {
            $saved = Settings::setMcuRepositoryConfig(
                (string) ($_POST['mcu_plugin_repository_url'] ?? ''),
                (string) ($_POST['mcu_theme_repository_url'] ?? '')
            );
            $flash = $saved
                ? ['type' => 'success', 'message' => 'Repository privati MCU salvati. La configurazione verra distribuita ai siti al prossimo controllo o sincronizzazione.']
                : ['type' => 'error', 'message' => 'Impossibile salvare la configurazione dei repository privati MCU.'];
        } elseif ($action === 'save_maintenance_verification') {
            $verificationTime = is_string($_POST['maintenance_verification_time'] ?? null) ? $_POST['maintenance_verification_time'] : '';
            if (!MaintenanceVerificationSchedule::validTime($verificationTime)) {
                $flash = ['type' => 'error', 'message' => 'Inserisci un orario valido per la verifica.'];
            } else {
                $saved = Settings::set('maintenance_verification_time', $verificationTime)
                    && Settings::set('maintenance_verification_enabled', isset($_POST['maintenance_verification_enabled']) ? '1' : '0');
                if ($saved) { MaintenanceMonitorState::resetUpdateSchedule(); }
                $flash = ['type' => $saved ? 'success' : 'error', 'message' => $saved
                    ? 'Orario verifica salvato. Ogni verifica esegue anche la sincronizzazione forzata.' : 'Salvataggio verifica non riuscito.'];
            }
        } elseif ($action === 'save_sms_settings') {
            Settings::setSmsToolsAccountEmail(trim($_POST['sms_tools_account_email'] ?? ''));
            Settings::setSmsToolsClientId(trim($_POST['sms_tools_client_id'] ?? ''));
            Settings::setSmsToolsClientSecret(trim($_POST['sms_tools_client_secret'] ?? ''));
            Settings::setSmsToolsSender(trim($_POST['sms_tools_sender'] ?? ''));
            $flash = ['type' => 'success', 'message' => 'Impostazioni SMS Tools salvate.'];
        } elseif ($action === 'test_sms_settings') {
            $smsTestPhone = trim($_POST['sms_test_phone'] ?? '');
            $smsTestSender = trim($_POST['sms_test_sender'] ?? '');
            $smsTestMessage = trim($_POST['sms_test_message'] ?? '');
            if ($smsTestMessage === '') {
                $smsTestMessage = 'Test SMS da Commander';
            }

            $smsTestResult = SmsToolsService::send($smsTestPhone, $smsTestMessage, $smsTestSender, true);
            $smsTestLog = $smsTestResult['log'] ?? [];
            $flash = $smsTestResult['success']
                ? ['type' => 'success', 'message' => 'Test SMS inviato: SMS Tools ha accettato la richiesta.']
                : ['type' => 'error', 'message' => 'Test SMS fallito: ' . ($smsTestResult['error'] ?? 'errore non specificato')];
        }
    }
}

$currentKey = Settings::getGeminiApiKey();
$currentModel = Settings::getGeminiDefaultModel();
$geminiModels = GeminiModelCatalog::choices();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['csrf_token'] ?? null)
    && in_array($_POST['action'] ?? '', ['save_gemini_settings', 'test_gemini_api'], true)) {
    $currentKey = trim((string) ($_POST['gemini_api_key'] ?? ''));
    $currentModel = GeminiModelCatalog::normalize((string) ($_POST['gemini_default_model'] ?? ''));
}
$githubToken = Settings::getGithubToken();
$mcuPluginRepositoryUrl = Settings::getMcuPluginRepositoryUrl();
$mcuThemeRepositoryUrl = Settings::getMcuThemeRepositoryUrl();
$maintenanceVerification = MaintenanceVerificationSchedule::configuration();
$monitorLastTick = json_decode((string) Settings::get('maintenance_monitor_last_tick', '{}'), true);
$updateConfig = Settings::getUpdatePluginConfig();
$updateRows = [];
foreach ($updateConfig as $slug => $row) {
    if (!is_array($row)) {
        continue;
    }
    $updateRows[] = array_merge([
        'plugin_slug' => $slug,
        'github_user' => '',
        'plugin_file' => '',
        'tested' => '6.4',
        'requires_php' => '7.4',
    ], $row);
}
if (!$updateRows) {
    $updateRows[] = [
        'plugin_slug' => 'marrison-assistant',
        'github_user' => 'marrisonlab',
        'plugin_file' => 'marrison-assistant/marrison-assistant.php',
        'tested' => '6.4',
        'requires_php' => '7.4',
    ];
}
$sitesUsingGlobal = count(Site::all());
$smsEmail = Settings::getSmsToolsAccountEmail();
$smsClientId = Settings::getSmsToolsClientId();
$smsClientSecret = Settings::getSmsToolsClientSecret();
$smsSender = Settings::getSmsToolsSender();
if ($smsTestSender === '') {
    $smsTestSender = $smsSender;
}

$activePage = 'settings';
include __DIR__ . '/partials/nav.php';
?>
<div class="mc-wrap">
    <div class="mc-header">
        <div>
            <h1>Impostazioni</h1>
            <p>Configurazione centralizzata dei servizi di Commander.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="mc-msg <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="mc-card"><h3>Scanner cookie Browserless</h3><p>Servizio pubblico per Cookie Manager, senza licenze. <a href="cookie-scanner.php">Configura scanner e quota gratuita</a>.</p></div>

    <div class="mc-card">
        <h3 style="margin-top:0;">API key Gemini globale</h3>
        <p style="color:#64748b; font-size:13px;">
            Tutti i siti usano questa chiave e il modello scelto qui. Le chiavi e i modelli dedicati ai singoli siti non sono disponibili.
        </p>
        <p style="font-size:13px;">Attualmente <strong><?= $sitesUsingGlobal ?></strong> sito/i usano la chiave globale.</p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mc-field">
                <label>API Key Gemini</label>
                <input type="password" name="gemini_api_key" value="<?= e($currentKey) ?>" placeholder="AIzaSy..." autocomplete="off">
                <?php if ($currentKey): ?>
                    <small style="color:#64748b;">Attuale: <code class="mc-key"><?= e(mask_api_key($currentKey)) ?></code></small>
                <?php endif; ?>
            </div>
            <div class="mc-field">
                <label>Modello di default</label>
                <select name="gemini_default_model" required>
                    <?php if (!isset($geminiModels[$currentModel])): ?>
                        <option value="" selected disabled>Scegli un modello valido (attuale: <?= e($currentModel ?: 'non indicato') ?>)</option>
                    <?php endif; ?>
                    <?php foreach ($geminiModels as $id => $label): ?>
                        <option value="<?= e($id) ?>" <?= $currentModel === $id ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="action" value="save_gemini_settings" class="mc-btn mc-btn-primary">Salva impostazioni Gemini</button>
            <button type="submit" name="action" value="test_gemini_api" class="mc-btn mc-btn-ghost">Test API</button>
            <p style="color:#64748b;font-size:12px;">Il test usa chiave e modello del modulo senza salvarli ed effettua una breve richiesta Gemini, soggetta alla quota API.</p>
        </form>
    </div>

    <div class="mc-card">
        <h3 style="margin-top:0;">Repository privati MCU</h3>
        <p style="color:#64748b; font-size:13px;">
            Questi indirizzi sono la configurazione centrale dei repository privati usati da Marrison Custom Updater per plugin e temi. Vengono inviati ai client MCU tramite le richieste autenticate di Commander; sui siti restano solo come cache operativa.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_mcu_repository_settings">
            <div class="mc-field">
                <label>Repository privato plugin</label>
                <input type="url" name="mcu_plugin_repository_url" value="<?= e($mcuPluginRepositoryUrl) ?>" placeholder="https://example.com/plugins" autocomplete="off">
                <small style="color:#64748b;">Lascia vuoto per disattivare il repository plugin sui client dopo la sincronizzazione.</small>
            </div>
            <div class="mc-field">
                <label>Repository privato temi</label>
                <input type="url" name="mcu_theme_repository_url" value="<?= e($mcuThemeRepositoryUrl) ?>" placeholder="https://example.com/themes" autocomplete="off">
                <small style="color:#64748b;">Lascia vuoto per disattivare il repository temi sui client dopo la sincronizzazione.</small>
            </div>
            <button type="submit" class="mc-btn mc-btn-primary">Salva repository MCU</button>
        </form>
    </div>

    <div class="mc-card">
        <h3 style="margin-top:0;">Verifica programmata siti MCU</h3>
        <p style="color:#64748b; font-size:13px;">Ogni giorno Commander forza la ricerca degli aggiornamenti sui siti MCU e ne acquisisce l'esito. I siti vengono distribuiti nei 15 minuti successivi all'orario scelto; gli errori vengono ritentati dopo 15 minuti.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_maintenance_verification">
            <div class="mc-field"><label for="maintenance-verification-enabled" style="display:flex;align-items:center;gap:8px;"><input id="maintenance-verification-enabled" style="width:auto;margin:0;" type="checkbox" name="maintenance_verification_enabled" value="1" <?= $maintenanceVerification['enabled'] ? 'checked' : '' ?>> Attiva verifica giornaliera</label></div>
            <div class="mc-field"><label for="maintenance-verification-time">Orario (Europe/Rome)</label><input id="maintenance-verification-time" style="max-width:160px;" type="time" required name="maintenance_verification_time" value="<?= e($maintenanceVerification['time']) ?>"></div>
            <button type="submit" class="mc-btn mc-btn-primary">Salva verifica programmata</button>
        </form>
        <p style="font-size:13px;">Configura il cron del server Commander ogni 5 minuti, dalla cartella di Commander: <code>php bin/maintenance-monitor.php --limit=30</code>. L'esecuzione non dipende dalle visite ai siti.</p>
        <p style="font-size:13px;">Ultima esecuzione del monitor: <?= e(!empty($monitorLastTick['finished_at']) ? (string) $monitorLastTick['finished_at'] : 'Mai registrata') ?>.
            <?php if (!empty($monitorLastTick['finished_at'])): ?>Operazioni: <?= (int) ($monitorLastTick['processed'] ?? 0) ?>; errori: <?= (int) ($monitorLastTick['failed'] ?? 0) ?>.<?php endif; ?></p>
    </div>

    <div class="mc-card">
        <h3 style="margin-top:0;">Aggiornamenti plugin</h3>
        <p style="color:#64748b; font-size:13px;">
            Questa chiave resta solo su Commander. I plugin client chiedono gli update a questo pannello, e Commander scarica i pacchetti dai repo privati.
        </p>
        <div style="margin:12px 0 16px; padding:12px 14px; background:#f8fafc; border:1px solid #dbe3ee; border-radius:6px; color:#334155; font-size:13px;">
            Qui inserisci il token GitHub una sola volta e poi aggiungi una riga per ogni plugin da aggiornare. Lo slug del plugin coincide con lo slug del repository.
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_update_settings">
            <div class="mc-field">
                <label>GitHub token</label>
                <input type="password" name="github_token" value="<?= e($githubToken) ?>" autocomplete="new-password" spellcheck="false" placeholder="ghp_...">
                <?php if ($githubToken): ?>
                    <small style="color:#64748b;">Token salvato: <code class="mc-key"><?= e(mask_api_key($githubToken)) ?></code></small>
                <?php endif; ?>
            </div>
            <div id="mc-update-plugins">
                <?php foreach ($updateRows as $row): ?>
                    <div class="mc-update-row" style="border:1px solid #dbe3ee; border-radius:6px; padding:14px; margin-bottom:12px; background:#fff;">
                        <div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
                            <button type="button" class="mc-btn mc-btn-secondary mc-remove-update-plugin" style="padding:6px 10px;">Rimuovi</button>
                        </div>
                        <div class="mc-settings-grid">
                            <div class="mc-field">
                                <label>Plugin slug</label>
                                <input type="text" name="update_plugin_slug[]" value="<?= e($row['plugin_slug']) ?>" placeholder="marrison-cookie">
                            </div>
                            <div class="mc-field">
                                <label>GitHub user</label>
                                <input type="text" name="update_github_user[]" value="<?= e($row['github_user']) ?>" placeholder="marrisonlab">
                            </div>
                            <div class="mc-field">
                                <label>Plugin file</label>
                                <input type="text" name="update_plugin_file[]" value="<?= e($row['plugin_file']) ?>" placeholder="marrison-cookie/marrison-cookie.php">
                            </div>
                            <div class="mc-field">
                                <label>Tested</label>
                                <input type="text" name="update_tested[]" value="<?= e($row['tested']) ?>" placeholder="6.4">
                            </div>
                            <div class="mc-field">
                                <label>Requires PHP</label>
                                <input type="text" name="update_requires_php[]" value="<?= e($row['requires_php']) ?>" placeholder="7.4">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="mc-btn" id="mc-add-update-plugin" style="margin-bottom:12px;">Aggiungi plugin</button>
            <button type="submit" class="mc-btn mc-btn-primary">Salva impostazioni aggiornamenti</button>
        </form>
    </div>

    <div class="mc-card">
        <h3 style="margin-top:0;">SMS Tools</h3>
        <p style="color:#64748b; font-size:13px;">
            Commander gestisce credenziali e invio SMS Tools. Il mittente viene passato dal plugin client, oppure lasciato vuoto per usare il default/numerico del provider.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_sms_settings">
            <div class="mc-field">
                <label>Email account SMS Tools</label>
                <input type="email" name="sms_tools_account_email" value="<?= e($smsEmail) ?>" placeholder="account@example.com">
                <small style="color:#64748b;">Campo informativo per il profilo SMS Tools; l'invio API usa Client ID e Client Secret.</small>
            </div>
            <div class="mc-settings-grid">
                <div class="mc-field">
                    <label>Client ID</label>
                    <input type="text" name="sms_tools_client_id" value="<?= e($smsClientId) ?>" autocomplete="off">
                </div>
                <div class="mc-field">
                    <label>Client Secret</label>
                    <input type="password" name="sms_tools_client_secret" value="<?= e($smsClientSecret) ?>" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="mc-btn mc-btn-primary">Salva impostazioni SMS</button>
        </form>
    </div>

    <div class="mc-card">
        <h3 style="margin-top:0;">Test invio SMS</h3>
        <p style="color:#64748b; font-size:13px;">
            Usa le credenziali SMS Tools salvate sopra e mostra il log tecnico della chiamata. Il secret non viene stampato.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test_sms_settings">
            <div class="mc-settings-grid">
                <div class="mc-field">
                    <label>Numero destinatario</label>
                    <input type="text" name="sms_test_phone" value="<?= e($smsTestPhone) ?>" placeholder="+39 333 1234567" required>
                </div>
                <div class="mc-field">
                    <label>Mittente test</label>
                    <input type="text" name="sms_test_sender" maxlength="11" value="<?= e($smsTestSender) ?>" placeholder="Lascia vuoto per default SMS Tools">
                    <small style="color:#64748b;">Usa solo un alias gia abilitato sul provider. Per diagnosticare la consegna, prova prima lasciando vuoto.</small>
                </div>
                <div class="mc-field mc-field-wide">
                    <label>Messaggio test</label>
                    <textarea name="sms_test_message" rows="3"><?= e($smsTestMessage) ?></textarea>
                </div>
            </div>
            <button type="submit" class="mc-btn mc-btn-primary">Invia SMS di test</button>
        </form>

        <div class="mc-console-wrap">
            <div class="mc-console-head">
                <strong>Console log</strong>
                <?php if ($smsTestResult !== null): ?>
                    <span class="mc-console-status <?= $smsTestResult['success'] ? 'ok' : 'ko' ?>">
                        <?= $smsTestResult['success'] ? 'OK' : 'ERRORE' ?>
                    </span>
                <?php endif; ?>
            </div>
            <pre class="mc-console-log<?= $smsTestLog ? '' : ' is-empty' ?>"><?php
                if ($smsTestLog) {
                    echo e(implode("\n", $smsTestLog));
                } else {
                    echo 'Nessun test eseguito in questa sessione.';
                }
            ?></pre>
        </div>
    </div>
</div>

<template id="mc-update-plugin-template">
    <div class="mc-update-row" style="border:1px solid #dbe3ee; border-radius:6px; padding:14px; margin-bottom:12px; background:#fff;">
        <div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
            <button type="button" class="mc-btn mc-btn-secondary mc-remove-update-plugin" style="padding:6px 10px;">Rimuovi</button>
        </div>
        <div class="mc-settings-grid">
            <div class="mc-field">
                <label>Plugin slug</label>
                <input type="text" name="update_plugin_slug[]" value="" placeholder="marrison-cookie">
            </div>
            <div class="mc-field">
                <label>GitHub user</label>
                <input type="text" name="update_github_user[]" value="marrisonlab" placeholder="marrisonlab">
            </div>
            <div class="mc-field">
                <label>Plugin file</label>
                <input type="text" name="update_plugin_file[]" value="" placeholder="marrison-cookie/marrison-cookie.php">
            </div>
            <div class="mc-field">
                <label>Tested</label>
                <input type="text" name="update_tested[]" value="6.4" placeholder="6.4">
            </div>
            <div class="mc-field">
                <label>Requires PHP</label>
                <input type="text" name="update_requires_php[]" value="7.4" placeholder="7.4">
            </div>
        </div>
    </div>
</template>

<script>
(function() {
    var button = document.getElementById('mc-add-update-plugin');
    var container = document.getElementById('mc-update-plugins');
    var template = document.getElementById('mc-update-plugin-template');
    if (!button || !container || !template) return;
    button.addEventListener('click', function() {
        container.appendChild(template.content.cloneNode(true));
    });
    container.addEventListener('click', function(event) {
        var target = event.target;
        if (target && target.classList && target.classList.contains('mc-remove-update-plugin')) {
            var row = target.closest('.mc-update-row');
            if (row) {
                row.remove();
            }
        }
    });
})();
</script>
