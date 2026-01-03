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
        .alt-hero { background: linear-gradient(135deg, rgba(4,118,244,0.12), rgba(51,225,161,0.18)); border: 1px solid var(--border); border-radius: 18px; padding: 22px 24px; display: flex; flex-wrap: wrap; gap: 18px; align-items: center; }
        .alt-hero .pill { background: var(--color-primary); color: #FFFFFF; padding: 6px 12px; border-radius: 999px; font-size: 13px; }
        .alt-hero .stat-card { min-width: 140px; background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 12px 14px; }
        .alt-hero .stat-value { font-size: 26px; font-weight: 800; margin: 0; }
        .glass-card { background: linear-gradient(145deg, rgba(255,255,255,0.02), rgba(0,0,0,0.02)); border: 1px solid var(--border); border-radius: 18px; padding: 18px; box-shadow: 0 18px 28px rgba(0,0,0,0.06); }
        .glass-card h5 { margin-bottom: 6px; }
        .ribbon { display: inline-flex; align-items: center; gap: 8px; background: var(--card); border: 1px solid var(--border); border-radius: 999px; padding: 6px 10px; font-size: 13px; }
        .pill-ghost { padding: 6px 12px; border-radius: 999px; border: 1px dashed var(--border); font-size: 13px; color: var(--muted); }
        .quick-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .presenti-card { border: 1px dashed var(--border); border-radius: 14px; padding: 12px; display: flex; gap: 12px; align-items: center; }
        .presenti-avatar { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: var(--table-alt); color: var(--color-primary); font-weight: 700; }
        .timeline { border-left: 2px solid var(--border); padding-left: 12px; margin: 0; list-style: none; display: grid; gap: 12px; }
        .timeline li { position: relative; }
        .timeline li::before { content: ""; position: absolute; left: -19px; top: 6px; width: 12px; height: 12px; border-radius: 50%; background: var(--color-primary); box-shadow: 0 0 0 4px var(--table-alt); }
        .alt-subtitle { color: var(--muted); font-size: 13px; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="app-shell">
        <div class="hero">
            <div class="logo-mark">CA</div>
            <div>
                <h1 class="h4 mb-1">Benvenuto nel sistema di controllo accessi</h1>
                <p class="mb-0 text-muted">Registra rapidamente ingressi e uscite dei visitatori con firme digitali sicure.</p>
            </div>
            <div class="ms-auto text-muted small text-end">
                Tema: <strong><?= $isDark ? 'Dark' : 'Light' ?></strong><br>
                Ambiente: <strong><?= htmlspecialchars($config->environment, ENT_QUOTES, 'UTF-8') ?></strong><br>
                Ruolo simulato: <strong><?= htmlspecialchars($config->simulateRole ?? '—', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        </div>

        <div class="p-4">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-outline-primary<?= $view === 'home' ? ' active' : '' ?>" href="?view=home">Dashboard</a>
                <a class="btn btn-outline-primary<?= $view === 'history' ? ' active' : '' ?><?= $canViewHistory ? '' : ' disabled' ?>" href="<?= $canViewHistory ? '?view=history' : '#' ?>">Lista accessi</a>
                <a class="btn btn-outline-primary<?= $view === 'audit' ? ' active' : '' ?><?= $canViewAudit ? '' : ' disabled' ?>" href="<?= $canViewAudit ? '?view=audit' : '#' ?>">Log di audit</a>
                <a class="btn btn-outline-primary<?= $view === 'alt' ? ' active' : '' ?>" href="?view=alt">UI alternativa</a>
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
            <?php elseif ($view === 'alt'): ?>
                <div class="alt-hero mb-4">
                    <div>
                        <div class="pill mb-2">Concept visivo</div>
                        <h2 class="h4 mb-1">Interfaccia alternativa sperimentale</h2>
                        <p class="mb-0 alt-subtitle">Una vista più ariosa per reception e desk informativi, con riepiloghi rapidi.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 ms-auto">
                        <div class="stat-card">
                            <div class="alt-subtitle">Presenti</div>
                            <p class="stat-value mb-0"><?= $canViewActive ? count($activeVisits) : '—' ?></p>
                        </div>
                        <div class="stat-card">
                            <div class="alt-subtitle">Accessi totali</div>
                            <p class="stat-value mb-0"><?= $canViewHistory ? count($historyVisits) : '—' ?></p>
                        </div>
                        <div class="stat-card">
                            <div class="alt-subtitle">Log</div>
                            <p class="stat-value mb-0"><?= $canViewAudit ? count($auditLogs) : '—' ?></p>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="glass-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="alt-subtitle">Check-in semplificato</div>
                                    <h5 class="mb-0">Registra un nuovo ingresso</h5>
                                </div>
                                <span class="ribbon">Oggi <?= date('d/m/Y') ?></span>
                            </div>
                            <form method="POST" id="entry-form-alt">
                                <input type="hidden" name="form_type" value="entry">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nome *</label>
                                        <input type="text" class="form-control" name="first_name" required placeholder="Nome del visitatore">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cognome *</label>
                                        <input type="text" class="form-control" name="last_name" required placeholder="Cognome del visitatore">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Azienda (facoltativa)</label>
                                        <input type="text" class="form-control" name="company" placeholder="Organizzazione di appartenenza">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Referente interno *</label>
                                        <input type="text" class="form-control" name="host_last_name" required placeholder="Chi accoglie il visitatore">
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0">Firma di entrata *</label>
                                            <span class="pill-ghost">Touch o mouse</span>
                                        </div>
                                        <canvas id="entrySignatureAlt" class="signature-pad"></canvas>
                                        <input type="hidden" name="entry_signature" id="entrySignatureDataAlt" required>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearEntrySignatureAlt">Pulisci firma</button>
                                        </div>
                                    </div>
                                    <div class="col-12 d-flex justify-content-between align-items-center">
                                        <div class="alt-subtitle">L'orario viene salvato automaticamente all'invio.</div>
                                        <div class="quick-actions">
                                            <a href="?view=home" class="btn btn-outline-primary btn-sm">Vista classica</a>
                                            <button type="submit" class="btn btn-primary">Conferma ingresso</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-5 d-flex flex-column gap-3">
                        <div class="glass-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="alt-subtitle">Presenze in tempo reale</div>
                                    <h5 class="mb-0">Visitatori presenti</h5>
                                </div>
                                <?php if (!$config->isLocal()): ?>
                                    <span class="ribbon">Accesso controllato</span>
                                <?php else: ?>
                                    <span class="ribbon">Ambiente locale</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!$canViewActive): ?>
                                <div class="alert alert-warning mb-0">Accesso alla lista consentito solo a operatori o admin autorizzati.</div>
                            <?php elseif (count($activeVisits) === 0): ?>
                                <div class="text-muted">Nessun visitatore presente ora.</div>
                            <?php else: ?>
                                <div class="d-grid gap-2">
                                    <?php foreach ($activeVisits as $visit): ?>
                                        <div class="presenti-card">
                                            <div class="presenti-avatar"><?= strtoupper(substr($visit['first_name'], 0, 1) . substr($visit['last_name'], 0, 1)) ?></div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold"><?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="alt-subtitle">Referente: <?= htmlspecialchars($visit['host_last_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="alt-subtitle">Entrata: <?= date('d/m/Y H:i', strtotime($visit['entry_time'])) ?></div>
                                            </div>
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#exitModal" data-visit-id="<?= $visit['id'] ?>" data-visitor-name="<?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?>">Uscita</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($canViewHistory): ?>
                            <div class="glass-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="alt-subtitle">Ultimi movimenti</div>
                                        <h5 class="mb-0">Timeline rapida</h5>
                                    </div>
                                    <a href="?view=history" class="btn btn-outline-primary btn-sm">Apri elenco</a>
                                </div>
                                <ul class="timeline">
                                    <?php $recentHistory = array_slice($historyVisits, 0, 4); ?>
                                    <?php if (count($recentHistory) === 0): ?>
                                        <li class="text-muted">Ancora nessun accesso registrato.</li>
                                    <?php else: ?>
                                        <?php foreach ($recentHistory as $visit): ?>
                                            <li>
                                                <div class="fw-semibold"><?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="alt-subtitle">Entrata: <?= date('d/m/Y H:i', strtotime($visit['entry_time'])) ?></div>
                                                <div class="alt-subtitle">Esito: <?= $visit['exit_time'] ? 'Uscito' : 'Ancora presente' ?></div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
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
    const entryCanvas = document.getElementById('entrySignature') || document.getElementById('entrySignatureAlt');
    const entryDataInput = document.getElementById('entrySignatureData') || document.getElementById('entrySignatureDataAlt');
    const entryForm = document.getElementById('entry-form') || document.getElementById('entry-form-alt');
    const clearEntryButton = document.getElementById('clearEntrySignature') || document.getElementById('clearEntrySignatureAlt');
    const exitCanvas = document.getElementById('exitSignature');
    const computedStyles = getComputedStyle(document.documentElement);
    const canvasColor = computedStyles.getPropertyValue('--canvas');
    const successColor = computedStyles.getPropertyValue('--color-success');
    const primaryColor = computedStyles.getPropertyValue('--color-primary');
    const entryPad = entryCanvas ? new SignaturePad(entryCanvas, { backgroundColor: canvasColor, penColor: successColor }) : null;
    const exitPad = new SignaturePad(exitCanvas, { backgroundColor: canvasColor, penColor: primaryColor });

    function resizeCanvas(canvas, signaturePad, preserveData = false) {
        if (!canvas || !signaturePad) return;
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
        if (!canvas || !pad) return;
        if (canvas.offsetHeight === 0) {
            canvas.style.height = '180px';
        }
        resizeCanvas(canvas, pad, preserveData);
    }

    window.addEventListener('resize', () => {
        if (entryPad) ensureCanvasReady(entryCanvas, entryPad);
        ensureCanvasReady(exitCanvas, exitPad);
    });

    if (entryPad) ensureCanvasReady(entryCanvas, entryPad);

    if (clearEntryButton && entryPad) {
        clearEntryButton.addEventListener('click', () => entryPad.clear());
    }
    document.getElementById('clearExitSignature').addEventListener('click', () => exitPad.clear());

    if (entryForm && entryPad && entryDataInput) {
        entryForm.addEventListener('submit', (event) => {
            if (entryPad.isEmpty()) {
                event.preventDefault();
                alert('Inserisci la firma di entrata.');
                return;
            }
            entryDataInput.value = entryPad.toDataURL('image/png');
        });
    }

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
