<?php
/** Front controller for access control app (clean-ish architecture). */
$config = require __DIR__ . '/../bootstrap.php';

use Domain\Repositories\AuditLogRepository;
use Domain\Repositories\VisitRepository;
use Domain\Services\AccessControlService;
use Domain\Services\VisitService;
use Infrastructure\Database;

$db = new Database($config);
$pdo = $db->pdo();

$accessControl = new AccessControlService($config);
$auditLogger = new AuditLogRepository($pdo);
$visitService = new VisitService(
    new VisitRepository($pdo),
    $auditLogger,
    $config->appUser,
    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
);

$errors = [];
$successMessage = '';
$exitGreeting = '';

function isPost(array $server): bool
{
    return ($server['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function buildUrl(array $params): string
{
    $merged = array_merge($_GET, $params);
    return '?' . http_build_query($merged);
}

function activeTheme(Config\AppConfig $config): string
{
    if ($config->themeMode === 'dark') {
        return 'dark';
    }
    if ($config->themeMode === 'light') {
        return 'light';
    }
    $hour = (int) date('G');
    return ($hour >= $config->lightStartHour && $hour < $config->lightEndHour) ? 'light' : 'dark';
}

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'from' => trim($_GET['from'] ?? ''),
    'to' => trim($_GET['to'] ?? ''),
];
$historyFilters = [
    'search' => trim($_GET['h_search'] ?? ''),
    'from' => trim($_GET['h_from'] ?? ''),
    'to' => trim($_GET['h_to'] ?? ''),
    'status' => trim($_GET['h_status'] ?? 'all'),
];
$view = $_GET['view'] ?? 'home';
$uiStyle = in_array($_GET['ui'] ?? 'classic', ['classic', 'concept'], true) ? $_GET['ui'] : 'classic';
[$performedBy, $ipAddress] = [$config->appUser, $_SERVER['REMOTE_ADDR'] ?? 'unknown'];

if (!$config->isLocal() && !$accessControl->canViewActiveList()) {
    $filters = ['search' => '', 'from' => '', 'to' => ''];
}

if (isPost($_SERVER)) {
    try {
        if (($_POST['form_type'] ?? '') === 'entry') {
            $visitService->registerEntry($_POST);
            $successMessage = 'Accesso registrato con successo. Benvenuto!';
        }

        if (($_POST['form_type'] ?? '') === 'exit') {
            $exitTime = $visitService->registerExit($_POST);
            $successMessage = 'Arrivederci! Uscita registrata correttamente. Grazie per la visita e buona giornata!';
            $exitGreeting = '';
        }
    } catch (\InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    } catch (\Throwable $e) {
        $errors[] = 'Errore inatteso: ' . $e->getMessage();
    }
}

$activeVisits = $accessControl->canViewActiveList() ? $visitService->activeVisits($filters) : [];
$auditLogs = $accessControl->canViewAuditLogs() ? $auditLogger->latest() : [];
$historyVisits = $accessControl->canViewHistory() ? $visitService->historyVisits($historyFilters) : [];

if ($accessControl->canViewActiveList() && isset($_GET['export']) && $_GET['export'] === 'active_csv') {
    $auditLogger->log(null, 'Export lista presenti', 'Records: ' . count($activeVisits), $performedBy, $ipAddress);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="visite_attive.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Nome', 'Cognome', 'Azienda', 'Referente', 'Entrata']);
    foreach ($activeVisits as $visit) {
        fputcsv($out, [
            $visit['id'],
            $visit['first_name'],
            $visit['last_name'],
            $visit['company'] ?? '',
            $visit['host_last_name'],
            $visit['entry_time'],
        ]);
    }
    fclose($out);
    exit;
}

$isExportHistory = $accessControl->canViewHistory() && isset($_GET['export']) && $_GET['export'] === 'history_csv';
if ($isExportHistory) {
    $auditLogger->log(null, 'Export storico accessi', 'Records: ' . count($historyVisits), $performedBy, $ipAddress);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="storico_accessi.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Nome', 'Cognome', 'Azienda', 'Referente', 'Entrata', 'Uscita']);
    foreach ($historyVisits as $visit) {
        fputcsv($out, [
            $visit['id'],
            $visit['first_name'],
            $visit['last_name'],
            $visit['company'] ?? '',
            $visit['host_last_name'],
            $visit['entry_time'],
            $visit['exit_time'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$isDark = activeTheme($config) === 'dark';
$canViewActive = $accessControl->canViewActiveList();
$canViewAudit = $accessControl->canViewAuditLogs();
$canViewHistory = $accessControl->canViewHistory();

if ($view === 'history' && $canViewHistory) {
    $auditLogger->log(null, 'View lista accessi', sprintf('Filtri: q=%s, from=%s, to=%s, status=%s', $historyFilters['search'], $historyFilters['from'], $historyFilters['to'], $historyFilters['status']), $performedBy, $ipAddress);
}

if ($view === 'audit' && $canViewAudit) {
    $auditLogger->log(null, 'View audit log', 'Consultazione log', $performedBy, $ipAddress);
}
?>
<!DOCTYPE html>
<html lang="it" class="<?= $isDark ? 'theme-dark' : 'theme-light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Controllo Accessi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --color-background: #FFFFFF;
            --color-surface: #E5E7EB;
            --color-surface-secondary: #FAFBFB;
            --color-text-primary: #000000;
            --color-text-secondary: #374151;
            --color-text-muted: #6B7280;
            --color-border: #C1C1C1;
            --color-primary: #0476F4;
            --color-success: #33E1A1;
            --color-error: #E90A07;
            --color-disabled: #D1D5DB;
            --bg: var(--color-background);
            --text: var(--color-text-primary);
            --muted: var(--color-text-muted);
            --card: var(--color-surface-secondary);
            --border: var(--color-border);
            --accent: var(--color-primary);
            --accent-2: var(--color-success);
            --canvas: var(--color-surface-secondary);
            --table-bg: var(--color-surface-secondary);
            --table-alt: var(--color-surface);
        }
        .theme-light {
            --color-background: #FFFFFF;
            --color-surface: #E5E7EB;
            --color-surface-secondary: #FAFBFB;
            --color-text-primary: #000000;
            --color-text-secondary: #374151;
            --color-text-muted: #6B7280;
            --color-border: #C1C1C1;
            --color-primary: #0476F4;
            --color-success: #33E1A1;
            --color-error: #E90A07;
            --color-disabled: #D1D5DB;
        }
        .theme-dark {
            --color-background: #101212;
            --color-surface: #111827;
            --color-surface-secondary: #101212;
            --color-text-primary: #FFFFFF;
            --color-text-secondary: #D1D5DB;
            --color-text-muted: #6B7280;
            --color-border: #374151;
            --color-primary: #0476F4;
            --color-success: #33E1A1;
            --color-error: #E90A07;
            --color-disabled: #374151;
        }
        body { background: var(--bg); color: var(--text); min-height: 100vh; }
        a { color: var(--color-primary); }
        body.ui-concept { background: radial-gradient(circle at 10% 20%, rgba(4, 118, 244, 0.08), transparent 28%), radial-gradient(circle at 90% 10%, rgba(51, 225, 161, 0.12), transparent 26%), radial-gradient(circle at 70% 80%, rgba(233, 10, 7, 0.06), transparent 30%), var(--bg); }
        .app-shell { background: var(--card); box-shadow: 0 20px 60px var(--border); border-radius: 20px; overflow: hidden; border: 1px solid var(--border); }
        .hero { background: var(--color-surface); padding: 28px; display: flex; align-items: center; gap: 16px; }
        .logo-mark { width: 54px; height: 54px; border-radius: 12px; background: var(--color-primary); display: grid; place-items: center; color: #FFFFFF; font-weight: 800; font-size: 22px; box-shadow: 0 0 0 1px var(--border); }
        .section-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 18px; height: 100%; }
        .section-card h5 { color: var(--accent); letter-spacing: 0.2px; margin-bottom: 12px; }
        .form-label { color: var(--color-text-secondary); }
        .btn-primary, .btn-primary:focus, .btn-primary:active, .btn-primary:hover { background: var(--color-primary); border-color: var(--color-primary); color: #FFFFFF; }
        .btn-success, .btn-success:focus, .btn-success:active, .btn-success:hover { background: var(--color-success); border-color: var(--color-success); color: #000000; }
        .btn-outline-primary, .btn-outline-primary:focus, .btn-outline-primary:active, .btn-outline-primary:hover { color: var(--color-primary); border-color: var(--color-primary); background: transparent; }
        .btn-outline-secondary, .btn-outline-secondary:focus, .btn-outline-secondary:active, .btn-outline-secondary:hover { color: var(--color-text-secondary); border-color: var(--color-border); background: transparent; }
        .btn-outline-success, .btn-outline-success:focus, .btn-outline-success:active, .btn-outline-success:hover { color: var(--color-success); border-color: var(--color-success); background: transparent; }
        .btn:disabled, .btn.disabled { background: var(--color-disabled) !important; border-color: var(--color-disabled) !important; color: var(--color-text-secondary) !important; }
        .table-modern { color: var(--text); --bs-table-bg: var(--table-bg); --bs-table-striped-bg: var(--table-alt); --bs-table-hover-bg: var(--table-alt); --bs-table-border-color: var(--border); }
        .theme-dark .table-modern { color: var(--color-text-secondary); --bs-table-bg: var(--table-bg); --bs-table-striped-bg: var(--table-alt); --bs-table-hover-bg: var(--table-alt); --bs-table-border-color: var(--border); }
        .table-modern thead th { background: var(--table-alt); color: var(--text); }
        .theme-dark .table-modern thead th { color: var(--color-text-secondary); }
        .table-modern td, .table-modern th { border-color: var(--border); }
        canvas.signature-pad { width: 100%; height: 180px; border-radius: 10px; border: 1px dashed var(--border); background: var(--canvas); touch-action: none; }
        .badge-pill { border-radius: 30px; padding: 8px 14px; }
        .badge.bg-success { background: var(--color-success) !important; color: #000000; }
        .badge.bg-primary { background: var(--color-primary) !important; color: #FFFFFF; }
        .badge.bg-secondary { background: var(--color-text-secondary) !important; color: #FFFFFF; }
        .badge.bg-info { background: var(--color-primary) !important; color: #FFFFFF; }
        .bg-gradient { background-image: none !important; }
        .text-muted { color: var(--muted) !important; }
        .modal-content { background: var(--card); color: var(--text); border: 1px solid var(--border); }
        .modal-header, .modal-footer { border-color: var(--border); }
        .border-secondary { border-color: var(--border) !important; }
        .alert { background: var(--color-surface); color: var(--color-text-primary); border: 1px solid var(--color-border); }
        .alert-danger { background: var(--color-error); border-color: var(--color-error); color: #FFFFFF; }
        .alert-success { background: var(--color-success); border-color: var(--color-success); color: #000000; }
        .alert-info { background: var(--color-primary); border-color: var(--color-primary); color: #FFFFFF; }
        .alert-warning { background: var(--color-border); border-color: var(--color-border); color: var(--color-text-primary); }
        /* Alternative UI */
        .ui-concept .app-shell { border: 1px solid rgba(4, 118, 244, 0.35); box-shadow: 0 25px 80px rgba(0,0,0,0.2); }
        .ui-concept .hero { border-bottom: 1px dashed var(--border); position: relative; overflow: hidden; }
        .ui-concept .hero::after { content: ""; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(4,118,244,0.08), rgba(51,225,161,0.05)); pointer-events: none; }
        .ui-concept .logo-mark { box-shadow: 0 10px 30px rgba(4,118,244,0.35); }
        .concept-toggle { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 6px; display: inline-flex; gap: 6px; }
        .concept-toggle .btn { border-radius: 10px; }
        .concept-lab { border: 1px dashed var(--border); border-radius: 14px; padding: 16px; background: linear-gradient(135deg, rgba(4, 118, 244, 0.04), rgba(51, 225, 161, 0.05)); }
        .concept-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-top: 12px; }
        .concept-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 14px; position: relative; overflow: hidden; }
        .concept-card small { color: var(--muted); }
        .concept-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: rgba(4, 118, 244, 0.12); color: var(--text); font-weight: 600; }
        .concept-chip.success { background: rgba(51, 225, 161, 0.18); }
        .concept-chip.ghost { background: rgba(255,255,255,0.06); border: 1px dashed var(--border); }
        .concept-pill-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
        .concept-card .badge { font-size: 11px; }
        .concept-card .spark { position: absolute; width: 90px; height: 90px; border-radius: 50%; background: radial-gradient(circle, rgba(4,118,244,0.18), transparent 60%); top: -28px; right: -32px; opacity: 0.7; }
        .concept-card .spark.green { background: radial-gradient(circle, rgba(51,225,161,0.16), transparent 60%); left: -32px; right: auto; top: auto; bottom: -32px; }
        .concept-card .list-unstyled { margin-bottom: 0; }
        .concept-card .list-unstyled li { margin-bottom: 4px; }
        .concept-bullet { display: flex; align-items: center; gap: 8px; color: var(--color-text-secondary); }
        .concept-bullet::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--color-primary); display: inline-block; }
        .concept-mini-timeline { list-style: none; padding-left: 0; margin: 10px 0 0 0; }
        .concept-mini-timeline li { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 8px; background: var(--color-surface); }
        .concept-mini-timeline .meta { color: var(--muted); font-size: 12px; }
        @media (max-width: 768px) {
            .hero { flex-direction: column; align-items: flex-start; }
            .concept-toggle { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body class="<?= $uiStyle === 'concept' ? 'ui-concept' : 'ui-classic' ?>">
<div class="container py-4">
    <div class="app-shell">
        <div class="hero">
            <div class="logo-mark">CA</div>
            <div>
                <h1 class="h4 mb-1">Benvenuto nel sistema di controllo accessi</h1>
                <p class="mb-0 text-muted">Registra rapidamente ingressi e uscite dei visitatori con firme digitali sicure.</p>
            </div>
            <div class="ms-auto text-muted small text-end d-flex flex-column align-items-end gap-2">
                <div>Tema: <strong><?= $isDark ? 'Dark' : 'Light' ?></strong><br>
                Ambiente: <strong><?= htmlspecialchars($config->environment, ENT_QUOTES, 'UTF-8') ?></strong><br>
                Ruolo simulato: <strong><?= htmlspecialchars($config->simulateRole ?? '—', ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="concept-toggle">
                    <a class="btn btn-sm<?= $uiStyle === 'classic' ? ' btn-primary' : ' btn-outline-secondary' ?>" href="<?= htmlspecialchars(buildUrl(['ui' => 'classic']), ENT_QUOTES, 'UTF-8') ?>">UI classica</a>
                    <a class="btn btn-sm<?= $uiStyle === 'concept' ? ' btn-success' : ' btn-outline-secondary' ?>" href="<?= htmlspecialchars(buildUrl(['ui' => 'concept']), ENT_QUOTES, 'UTF-8') ?>">UI alternativa</a>
                </div>
            </div>
        </div>

        <div class="p-4">
            <?php if ($uiStyle === 'concept'): ?>
                <div class="concept-lab mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <div class="text-muted small">Laboratorio UI</div>
                            <h5 class="mb-1">Proposte alternative per l’interfaccia</h5>
                            <div class="text-muted small">Esplora variazioni di layout, chips e timeline per presentare i dati in modo narrativo.</div>
                        </div>
                        <div class="concept-chip success">Moodboard attiva</div>
                    </div>
                    <div class="concept-grid">
                        <div class="concept-card">
                            <div class="spark"></div>
                            <div class="concept-chip mb-2">Pannello ibrido</div>
                            <div class="concept-bullet">Header con badge di contesto e CTA principali.</div>
                            <div class="concept-pill-list">
                                <span class="badge bg-success">Timeline ingressi</span>
                                <span class="badge bg-primary">Card laterali</span>
                                <span class="badge bg-secondary">CTA fluttuanti</span>
                            </div>
                            <ul class="concept-mini-timeline">
                                <?php if (count($activeVisits) > 0): ?>
                                    <?php foreach (array_slice($activeVisits, 0, 3) as $visit): ?>
                                        <li>
                                            <div>
                                                <strong><?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                                <span class="meta"><?= htmlspecialchars($visit['company'] ?? 'Visitatore', ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <span class="meta"><?= date('H:i', strtotime($visit['entry_time'])) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>
                                        <div><strong>Nessuna presenza</strong><br><span class="meta">La timeline si popolerà con i prossimi ingressi</span></div>
                                        <span class="meta">—</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="concept-card">
                            <div class="spark green"></div>
                            <div class="concept-chip success mb-2">Palette narrativa</div>
                            <p class="mb-2 small">Fondali sfumati e cards stratificate per dare profondità senza perdere leggibilità.</p>
                            <ul class="list-unstyled small">
                                <li class="concept-bullet">Comparti “ingresso/uscita” con tag colorati.</li>
                                <li class="concept-bullet">Badge ruoli per segmentare i pannelli.</li>
                                <li class="concept-bullet">CTA evidenziate in chiave neon (verde/blu).</li>
                            </ul>
                            <div class="concept-pill-list">
                                <span class="badge bg-info">Glass effect</span>
                                <span class="badge bg-success">Gradienti morbidi</span>
                                <span class="badge bg-primary">Contrast helper</span>
                            </div>
                        </div>
                        <div class="concept-card">
                            <div class="concept-chip ghost mb-2">Widget rapidi</div>
                            <p class="mb-1 small">Mini-schedine per vedere e agire senza aprire modali.</p>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <div class="text-muted small">Presenti ora</div>
                                    <h4 class="mb-0"><?= count($activeVisits) ?></h4>
                                </div>
                                <span class="badge bg-primary">Realtime</span>
                            </div>
                            <div class="text-muted small">Idea: pulsanti rapidi per chiudere visite o segnalare ospiti VIP direttamente dalla lista.</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-outline-primary<?= $view === 'home' ? ' active' : '' ?>" href="?view=home">Dashboard</a>
                <a class="btn btn-outline-primary<?= $view === 'history' ? ' active' : '' ?><?= $canViewHistory ? '' : ' disabled' ?>" href="<?= $canViewHistory ? '?view=history' : '#' ?>">Lista accessi</a>
                <a class="btn btn-outline-primary<?= $view === 'audit' ? ' active' : '' ?><?= $canViewAudit ? '' : ' disabled' ?>" href="<?= $canViewAudit ? '?view=audit' : '#' ?>">Log di audit</a>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div id="alert-success" class="alert alert-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($exitGreeting): ?>
                <div id="alert-exit" class="alert alert-info"><?= htmlspecialchars($exitGreeting, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($view === 'home'): ?>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="section-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <div class="text-muted small">Ingresso rapido</div>
                                <h5 class="mb-0">Registra accesso</h5>
                            </div>
                            <span class="badge bg-success bg-gradient badge-pill">Oggi <?= date('d/m/Y') ?></span>
                        </div>
                        <form method="POST" id="entry-form">
                            <input type="hidden" name="form_type" value="entry">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nome *</label>
                                    <input type="text" class="form-control" name="first_name" required placeholder="Mario">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cognome *</label>
                                    <input type="text" class="form-control" name="last_name" required placeholder="Rossi">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Azienda (facoltativa)</label>
                                    <input type="text" class="form-control" name="company" placeholder="Acme S.p.A.">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Referente interno *</label>
                                    <input type="text" class="form-control" name="host_last_name" required placeholder="Cognome referente">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Firma di entrata *</label>
                                    <canvas id="entrySignature" class="signature-pad"></canvas>
                                    <input type="hidden" name="entry_signature" id="entrySignatureData" required>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearEntrySignature">Pulisci firma</button>
                                    </div>
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center">
                                    <div class="text-muted small">L'orario di entrata viene salvato automaticamente.</div>
                                    <button type="submit" class="btn btn-primary">Conferma ingresso</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="section-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <div class="text-muted small">Monitoraggio in tempo reale</div>
                                <h5 class="mb-0">Visitatori presenti</h5>
                            </div>
                            <?php if (!$config->isLocal()): ?>
                                <span class="badge bg-primary bg-gradient badge-pill">Accesso controllato</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-gradient badge-pill">Accesso locale</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($canViewActive): ?>
                            <?php if (count($activeVisits) === 0): ?>
                                <div class="text-muted">Nessun visitatore presente al momento.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 table-modern">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Azienda</th>
                                                <th>Referente</th>
                                                <th>Entrata</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($activeVisits as $visit): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($visit['company'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($visit['host_last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($visit['entry_time'])) ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#exitModal" data-visit-id="<?= $visit['id'] ?>" data-visitor-name="<?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?>">Registra uscita</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">Accesso alla lista consentito solo a operatori o admin autorizzati.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php elseif ($view === 'history' && $canViewHistory): ?>
                <div class="section-card mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div class="text-muted small">Storico completo</div>
                            <h5 class="mb-0">Lista accessi</h5>
                        </div>
                        <span class="badge bg-primary bg-gradient badge-pill">Operatori / Admin</span>
                    </div>
                    <form class="row g-2 mb-3" method="GET">
                        <div class="col-md-3">
                            <input type="text" name="h_search" value="<?= htmlspecialchars($historyFilters['search'], ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="Cerca nome, azienda, referente">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="h_from" value="<?= htmlspecialchars($historyFilters['from'], ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="Dal">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="h_to" value="<?= htmlspecialchars($historyFilters['to'], ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="Al">
                        </div>
                        <div class="col-md-3">
                            <select name="h_status" class="form-select">
                                <option value="all" <?= $historyFilters['status'] === 'all' ? 'selected' : '' ?>>Tutti</option>
                                <option value="active" <?= $historyFilters['status'] === 'active' ? 'selected' : '' ?>>Solo presenti</option>
                                <option value="closed" <?= $historyFilters['status'] === 'closed' ? 'selected' : '' ?>>Solo usciti</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button class="btn btn-outline-primary w-100" type="submit">Filtra</button>
                            <a class="btn btn-outline-success" href="?export=history_csv">CSV</a>
                        </div>
                    </form>
                    <div class="table-responsive" style="max-height: 320px;">
                        <table class="table table-hover align-middle mb-0 table-modern">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Azienda</th>
                                    <th>Referente</th>
                                    <th>Entrata</th>
                                    <th>Uscita</th>
                                    <th>Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($historyVisits) === 0): ?>
                                    <tr><td colspan="6" class="text-muted">Nessun accesso trovato.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($historyVisits as $visit): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($visit['company'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($visit['host_last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($visit['entry_time'])) ?></td>
                                            <td><?= $visit['exit_time'] ? date('d/m/Y H:i', strtotime($visit['exit_time'])) : '—' ?></td>
                                            <td>
                                                <?php if ($visit['exit_time']): ?>
                                                    <span class="badge bg-secondary">Uscito</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Presente</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($view === 'audit' && $canViewAudit): ?>
                <div class="section-card mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div class="text-muted small">Storico delle azioni</div>
                            <h5 class="mb-0">Log di audit (ISO 27001 ready)</h5>
                        </div>
                        <span class="badge bg-info bg-gradient badge-pill">Solo Admin</span>
                    </div>
                    <div class="table-responsive" style="max-height: 260px;">
                        <table class="table table-striped align-middle mb-0 table-modern">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Azione</th>
                                    <th>Dettagli</th>
                                    <th>ID visita</th>
                                    <th>Eseguito da</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($auditLogs) === 0): ?>
                                    <tr><td colspan="6" class="text-muted">Nessuna attività registrata.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($log['details'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($log['visit_id'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($log['performed_by'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($log['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="exitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Registra uscita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="exit-form">
                <input type="hidden" name="form_type" value="exit">
                <input type="hidden" name="visit_id" id="exitVisitId">
                <div class="modal-body">
                    <p class="text-muted mb-2" id="exitVisitorName"></p>
                    <label class="form-label">Firma di uscita *</label>
                    <canvas id="exitSignature" class="signature-pad"></canvas>
                    <input type="hidden" name="exit_signature" id="exitSignatureData" required>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearExitSignature">Pulisci firma</button>
                    </div>
                    <div class="text-muted small mt-2">L'orario di uscita sarà registrato automaticamente.</div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success">Conferma uscita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js"></script>
<script>
    const entryCanvas = document.getElementById('entrySignature');
    const exitCanvas = document.getElementById('exitSignature');
    const computedStyles = getComputedStyle(document.documentElement);
    const canvasColor = computedStyles.getPropertyValue('--canvas');
    const successColor = computedStyles.getPropertyValue('--color-success');
    const primaryColor = computedStyles.getPropertyValue('--color-primary');
    const entryPad = new SignaturePad(entryCanvas, { backgroundColor: canvasColor, penColor: successColor });
    const exitPad = new SignaturePad(exitCanvas, { backgroundColor: canvasColor, penColor: primaryColor });

    function resizeCanvas(canvas, signaturePad, preserveData = false) {
        const existing = preserveData ? signaturePad.toData() : [];
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        const ctx = canvas.getContext('2d');
        ctx.scale(ratio, ratio);
        signaturePad.clear();
        if (preserveData && existing.length) {
            signaturePad.fromData(existing);
        }
    }

    function ensureCanvasReady(canvas, pad, preserveData = false) {
        if (canvas.offsetHeight === 0) {
            canvas.style.height = '180px';
        }
        resizeCanvas(canvas, pad, preserveData);
    }

    window.addEventListener('resize', () => {
        ensureCanvasReady(entryCanvas, entryPad);
        ensureCanvasReady(exitCanvas, exitPad);
    });

    ensureCanvasReady(entryCanvas, entryPad);

    document.getElementById('clearEntrySignature').addEventListener('click', () => entryPad.clear());
    document.getElementById('clearExitSignature').addEventListener('click', () => exitPad.clear());

    document.getElementById('entry-form').addEventListener('submit', (event) => {
        if (entryPad.isEmpty()) {
            event.preventDefault();
            alert('Inserisci la firma di entrata.');
            return;
        }
        document.getElementById('entrySignatureData').value = entryPad.toDataURL('image/png');
    });

    const exitModal = document.getElementById('exitModal');
    exitModal.addEventListener('shown.bs.modal', (event) => {
        const button = event.relatedTarget;
        const visitId = button.getAttribute('data-visit-id');
        const visitorName = button.getAttribute('data-visitor-name');
        document.getElementById('exitVisitId').value = visitId;
        document.getElementById('exitVisitorName').textContent = `Uscita per ${visitorName}`;
        exitPad.clear();
        setTimeout(() => ensureCanvasReady(exitCanvas, exitPad), 50);
    });

    document.getElementById('exit-form').addEventListener('submit', (event) => {
        ensureCanvasReady(exitCanvas, exitPad, true);
        if (exitPad.isEmpty()) {
            event.preventDefault();
            alert('Inserisci la firma di uscita.');
            return;
        }
        document.getElementById('exitSignatureData').value = exitPad.toDataURL('image/png');
    });

    ['alert-success', 'alert-exit'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            setTimeout(() => el.remove(), 5000);
        }
    });
</script>
</body>
</html>
