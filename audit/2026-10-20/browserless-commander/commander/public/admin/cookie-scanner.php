<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Models\Settings;
use App\Services\CookieBrowserlessService;
use App\Services\MaintenanceSecretVault;

require_admin_login();
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) $flash = ['type' => 'error', 'message' => 'Token di sicurezza non valido.'];
    else {
        try {
            $token = is_string($_POST['browserless_token'] ?? null) ? trim($_POST['browserless_token']) : '';
            $limit = max(2, min(1000, (int) ($_POST['monthly_units'] ?? 900)));
            if ($token !== '' && !preg_match('/^[a-zA-Z0-9_-]{20,255}$/', $token)) throw new RuntimeException('Formato della chiave non valido.');
            if ($token !== '' && !Settings::set('cookie_browserless_token', MaintenanceSecretVault::encrypt($token))) throw new RuntimeException('Salvataggio della chiave non riuscito.');
            if (isset($_POST['enabled']) && CookieBrowserlessService::token() === '') throw new RuntimeException('Inserisci prima una chiave Browserless.');
            $saved = Settings::set('cookie_browserless_monthly_units', (string) $limit)
                && Settings::set('cookie_browserless_enabled', isset($_POST['enabled']) ? '1' : '0');
            if (!$saved) throw new RuntimeException('Salvataggio non riuscito.');
            $flash = ['type' => 'success', 'message' => 'Configurazione scanner salvata.'];
        } catch (Throwable $error) { $flash = ['type' => 'error', 'message' => $error instanceof RuntimeException ? $error->getMessage() : 'Configurazione non salvata.']; }
    }
}
$configured = CookieBrowserlessService::token() !== '';
$activePage = 'settings';
include __DIR__ . '/partials/nav.php';
?>
<div class="mc-wrap">
    <div class="mc-header"><div><h1>Scanner cookie Browserless</h1><p>Servizio pubblico per Cookie Manager, senza licenze o registrazioni dei siti.</p></div></div>
    <?php if ($flash): ?><div class="mc-msg <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <div class="mc-card">
        <form method="post">
            <?= csrf_field() ?>
            <div class="mc-field"><label for="browserless-token">Chiave Browserless</label>
                <input id="browserless-token" type="password" name="browserless_token" value="" autocomplete="new-password" placeholder="<?= $configured ? 'Chiave configurata: lascia vuoto per conservarla' : 'Inserisci la chiave Browserless' ?>">
                <small>Salvata cifrata su Commander. Mai distribuita ai plugin. Puoi usare anche BROWSERLESS_TOKEN nell’ambiente del server.</small>
            </div>
            <div class="mc-field"><label for="monthly-units">Limite mensile di unità riservate</label><input id="monthly-units" type="number" min="2" max="1000" name="monthly_units" value="<?= e((string) Settings::get('cookie_browserless_monthly_units', '900')) ?>">
                <small>Due unità riservate per pagina, anche in caso di errore. Limite locale prudenziale: altri utilizzi dell’account Browserless consumano la stessa quota del fornitore.</small></div>
            <div class="mc-field"><label><input type="checkbox" name="enabled" value="1" <?= Settings::get('cookie_browserless_enabled', '0') === '1' ? 'checked' : '' ?>> Abilita il servizio di scansione</label></div>
            <p>Endpoint fisso: production-sfo.browserless.io. Nessun proxy o risoluzione CAPTCHA a pagamento. Una pagina per richiesta, massimo 45 secondi e una richiesta attiva su Commander. Limiti: 100 pagine al giorno per dominio e 200 per IP.</p>
            <p>Il browser visita pagine pubbliche, verifica prima e dopo il consenso e tenta l’avvio dei video YouTube già incorporati. Le risoluzioni DNS dei domini usano Google Public DNS. I valori dei cookie vengono esclusi dai risultati.</p>
            <button type="submit" class="mc-btn mc-btn-primary">Salva configurazione scanner</button>
        </form>
        <p><a href="settings.php">Torna alle impostazioni</a></p>
    </div>
</div>
