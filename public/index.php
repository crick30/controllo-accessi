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
$visitService = new VisitService(
    new VisitRepository($pdo),
    new AuditLogRepository($pdo),
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
            $successMessage = 'Arrivederci! Uscita registrata correttamente.';
            $exitGreeting = 'Grazie per la visita e buona giornata!';
        }
    } catch (\InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    } catch (\Throwable $e) {
        $errors[] = 'Errore inatteso: ' . $e->getMessage();
    }
}

$activeVisits = $accessControl->canViewActiveList() ? $visitService->activeVisits($filters) : [];
$auditLogs = $accessControl->canViewAuditLogs() ? (new AuditLogRepository($pdo))->latest() : [];
$historyVisits = $accessControl->canViewHistory() ? $visitService->historyVisits($historyFilters) : [];

if ($accessControl->canViewActiveList() && isset($_GET['export']) && $_GET['export'] === 'active_csv') {
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
?>
<!DOCTYPE html>
<html lang="it" class="<?= $isDark ? 'theme-dark' : 'theme-light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Controllo Accessi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --bg: #f7f9fc; --text: #0f172a; --muted: #52606d; --card: #ffffff; --border: #e5e7eb; --accent: #2563eb; --accent-2: #22c55e; --canvas: #eef2f7; }
        .theme-dark { --bg: #0d1117; --text: #e6e6e6; --muted: #c0c5cc; --card: #111820; --border: rgba(255,255,255,0.08); --accent: #10a37f; --accent-2: #7dd3fc; --canvas: #0b0f15; }
        body { background: radial-gradient(circle at 20% 20%, rgba(16,163,127,0.08), transparent 35%), radial-gradient(circle at 80% 0%, rgba(125,211,252,0.08), transparent 35%), var(--bg); color: var(--text); min-height: 100vh; }
        .app-shell { background: var(--card); box-shadow: 0 20px 60px rgba(0,0,0,0.12); border-radius: 20px; overflow: hidden; border: 1px solid var(--border); }
        .hero { background: linear-gradient(135deg, rgba(37,99,235,0.08), rgba(34,197,94,0.08)); padding: 28px; display: flex; align-items: center; gap: 16px; }
        .logo-mark { width: 54px; height: 54px; border-radius: 12px; background: linear-gradient(135deg, #7dd3fc, #34d399); display: grid; place-items: center; color: #0b1220; font-weight: 800; font-size: 22px; box-shadow: 0 10px 30px rgba(52,211,153,0.35); }
        .section-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 18px; height: 100%; }
        .section-card h5 { color: var(--accent); letter-spacing: 0.2px; margin-bottom: 12px; }
        .form-label { color: var(--muted); }
        .btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); border: none; }
        .table { color: var(--text); }
        .table thead th { border-color: var(--border); }
        .table td, .table th { border-color: var(--border); }
        canvas.signature-pad { width: 100%; height: 180px; border-radius: 10px; border: 1px dashed var(--border); background: var(--canvas); touch-action: none; }
        .badge-pill { border-radius: 30px; padding: 8px 14px; }
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
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($exitGreeting): ?>
                <div class="alert alert-info"><?= htmlspecialchars($exitGreeting, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($view === 'home'): ?>
            <div class="row g-4">
                <div class="col-lg-5">
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
                <div class="col-lg-7">
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
                                    <table class="table table-hover align-middle mb-0">
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
                        <table class="table table-hover align-middle mb-0">
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
                        <table class="table table-striped align-middle mb-0">
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
    const entryPad = new SignaturePad(entryCanvas, { backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--canvas'), penColor: '#22c55e' });
    const exitPad = new SignaturePad(exitCanvas, { backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--canvas'), penColor: '#2563eb' });

    function resizeCanvas(canvas, signaturePad) {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        const ctx = canvas.getContext('2d');
        ctx.scale(ratio, ratio);
        signaturePad.clear();
    }

    function ensureCanvasReady(canvas, pad) {
        if (canvas.offsetHeight === 0) {
            canvas.style.height = '180px';
        }
        resizeCanvas(canvas, pad);
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
        ensureCanvasReady(exitCanvas, exitPad);
        if (exitPad.isEmpty()) {
            event.preventDefault();
            alert('Inserisci la firma di uscita.');
            return;
        }
        document.getElementById('exitSignatureData').value = exitPad.toDataURL('image/png');
    });
</script>
</body>
</html>
