<?php
$config = include __DIR__ . '/config.php';

$canBypassAuth = $config['environment'] === 'local';
$databasePath = $config['database_path'];

if (!is_dir(dirname($databasePath))) {
    mkdir(dirname($databasePath), 0777, true);
}

$pdo = new PDO('sqlite:' . $databasePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec('CREATE TABLE IF NOT EXISTS visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    company TEXT,
    host_last_name TEXT NOT NULL,
    entry_time TEXT NOT NULL,
    exit_time TEXT,
    entry_signature TEXT,
    exit_signature TEXT
)');

$pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    visit_id INTEGER,
    action TEXT NOT NULL,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(visit_id) REFERENCES visits(id)
)');

function userHasGroup(array $groups, array $requiredGroups): bool
{
    if (empty($requiredGroups)) {
        return true;
    }

    return (bool) array_intersect($groups, $requiredGroups);
}

function canViewActiveList(array $config): bool
{
    if ($config['environment'] === 'local') {
        return true;
    }

    return userHasGroup($config['current_user_groups'], $config['operator_groups']);
}

function canViewAuditLogs(array $config): bool
{
    if ($config['environment'] === 'local') {
        return true;
    }

    return userHasGroup($config['current_user_groups'], $config['admin_groups']);
}

function logAudit(PDO $pdo, string $action, ?int $visitId, ?string $details = null): void
{
    $stmt = $pdo->prepare('INSERT INTO audit_logs (visit_id, action, details) VALUES (:visit_id, :action, :details)');
    $stmt->execute([
        ':visit_id' => $visitId,
        ':action' => $action,
        ':details' => $details,
    ]);
}

$errors = [];
$successMessage = '';
$exitGreeting = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'entry') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $hostLastName = trim($_POST['host_last_name'] ?? '');
        $signature = $_POST['entry_signature'] ?? '';

        if ($firstName === '' || $lastName === '' || $hostLastName === '') {
            $errors[] = 'Nome, cognome e referente sono obbligatori.';
        }

        if ($signature === '') {
            $errors[] = 'La firma di entrata è obbligatoria.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO visits (first_name, last_name, company, host_last_name, entry_time, entry_signature) VALUES (:first_name, :last_name, :company, :host_last_name, :entry_time, :entry_signature)');
            $entryTime = date('Y-m-d H:i:s');
            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':company' => $company !== '' ? $company : null,
                ':host_last_name' => $hostLastName,
                ':entry_time' => $entryTime,
                ':entry_signature' => $signature,
            ]);

            $visitId = (int) $pdo->lastInsertId();
            logAudit($pdo, 'Ingresso registrato', $visitId, "Entrata alle $entryTime");
            $successMessage = 'Accesso registrato con successo. Benvenuto!';
        }
    }

    if (isset($_POST['form_type']) && $_POST['form_type'] === 'exit') {
        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $exitSignature = $_POST['exit_signature'] ?? '';

        if ($visitId === 0) {
            $errors[] = 'Seleziona un visitatore valido per l\'uscita.';
        }

        if ($exitSignature === '') {
            $errors[] = 'La firma di uscita è obbligatoria.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('UPDATE visits SET exit_time = :exit_time, exit_signature = :exit_signature WHERE id = :id');
            $exitTime = date('Y-m-d H:i:s');
            $stmt->execute([
                ':exit_time' => $exitTime,
                ':exit_signature' => $exitSignature,
                ':id' => $visitId,
            ]);

            logAudit($pdo, 'Uscita registrata', $visitId, "Uscita alle $exitTime");
            $successMessage = 'Arrivederci! Uscita registrata correttamente.';
            $exitGreeting = 'Grazie per la visita e buona giornata!';
        }
    }
}

$activeVisits = [];
$allLogs = [];

if (canViewActiveList($config)) {
    $activeVisits = $pdo->query('SELECT * FROM visits WHERE exit_time IS NULL ORDER BY entry_time DESC')->fetchAll(PDO::FETCH_ASSOC);
}

if (canViewAuditLogs($config)) {
    $allLogs = $pdo->query('SELECT * FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Controllo Accessi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 40%, #111827 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }
        .app-shell {
            background: #0b1220;
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
            border-radius: 20px;
            overflow: hidden;
        }
        .hero {
            background: radial-gradient(circle at 20% 20%, rgba(59,130,246,0.25), transparent 40%),
                        radial-gradient(circle at 80% 0%, rgba(16,185,129,0.25), transparent 35%),
                        linear-gradient(90deg, #0f172a, #0b1220);
            padding: 28px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .logo-mark {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            background: linear-gradient(135deg, #60a5fa, #22c55e);
            display: grid;
            place-items: center;
            color: #0b1220;
            font-weight: 800;
            font-size: 22px;
            box-shadow: 0 10px 30px rgba(34,197,94,0.3);
        }
        .section-card {
            background: #0f172a;
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 16px;
            padding: 18px;
            height: 100%;
        }
        .section-card h5 {
            color: #a5b4fc;
            letter-spacing: 0.2px;
            margin-bottom: 12px;
        }
        .form-label { color: #cbd5f5; }
        .btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); border: none; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); border: none; }
        .table-dark { --bs-table-bg: #0b1220; --bs-table-striped-bg: #0f172a; --bs-table-hover-bg: #111827; }
        canvas.signature-pad {
            width: 100%;
            height: 160px;
            border-radius: 10px;
            border: 1px dashed rgba(255,255,255,0.15);
            background: #0b1220;
        }
        .badge-pill { border-radius: 30px; padding: 8px 14px; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="app-shell">
        <div class="hero">
            <div class="logo-mark">CA</div>
            <div>
                <h1 class="h4 mb-1 text-white">Benvenuto nel sistema di controllo accessi</h1>
                <p class="mb-0 text-secondary">Registra rapidamente ingressi e uscite dei visitatori con firme digitali sicure.</p>
            </div>
        </div>

        <div class="p-4">
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
                                    <input type="hidden" name="entry_signature" id="entrySignatureData">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-light" id="clearEntrySignature">Pulisci firma</button>
                                    </div>
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center">
                                    <div class="text-secondary small">L'orario di entrata viene salvato automaticamente.</div>
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
                            <?php if (!$canBypassAuth): ?>
                                <span class="badge bg-primary bg-gradient badge-pill">Accesso limitato</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-gradient badge-pill">Accesso locale</span>
                            <?php endif; ?>
                        </div>
                        <?php if (canViewActiveList($config)): ?>
                            <?php if (count($activeVisits) === 0): ?>
                                <div class="text-secondary">Nessun visitatore presente al momento.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover align-middle mb-0">
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
                            <div class="alert alert-warning mb-0">Accesso alla lista consentito solo a operatori autorizzati.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (canViewAuditLogs($config)): ?>
                <div class="section-card mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div class="text-muted small">Storico delle azioni</div>
                            <h5 class="mb-0">Log di audit</h5>
                        </div>
                        <span class="badge bg-info bg-gradient badge-pill">Admin</span>
                    </div>
                    <div class="table-responsive" style="max-height: 260px;">
                        <table class="table table-dark table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Azione</th>
                                    <th>Dettagli</th>
                                    <th>ID visita</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($allLogs) === 0): ?>
                                    <tr><td colspan="4" class="text-secondary">Nessuna attività registrata.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($allLogs as $log): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($log['details'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) $log['visit_id'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
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
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Registra uscita</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="exit-form">
                <input type="hidden" name="form_type" value="exit">
                <input type="hidden" name="visit_id" id="exitVisitId">
                <div class="modal-body">
                    <p class="text-secondary mb-2" id="exitVisitorName"></p>
                    <label class="form-label">Firma di uscita *</label>
                    <canvas id="exitSignature" class="signature-pad"></canvas>
                    <input type="hidden" name="exit_signature" id="exitSignatureData">
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-light" id="clearExitSignature">Pulisci firma</button>
                    </div>
                    <div class="text-secondary small mt-2">L'orario di uscita sarà registrato automaticamente.</div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Annulla</button>
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
    const entryPad = new SignaturePad(entryCanvas, { backgroundColor: '#0b1220', penColor: '#22c55e' });
    const exitPad = new SignaturePad(exitCanvas, { backgroundColor: '#0b1220', penColor: '#3b82f6' });

    function resizeCanvas(canvas, signaturePad) {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        signaturePad.clear();
    }

    window.addEventListener('resize', () => {
        resizeCanvas(entryCanvas, entryPad);
        resizeCanvas(exitCanvas, exitPad);
    });

    resizeCanvas(entryCanvas, entryPad);
    resizeCanvas(exitCanvas, exitPad);

    document.getElementById('clearEntrySignature').addEventListener('click', () => entryPad.clear());
    document.getElementById('clearExitSignature').addEventListener('click', () => exitPad.clear());

    document.getElementById('entry-form').addEventListener('submit', (event) => {
        if (entryPad.isEmpty()) {
            event.preventDefault();
            alert('Inserisci la firma di entrata.');
            return;
        }
        document.getElementById('entrySignatureData').value = entryPad.toDataURL();
    });

    const exitModal = document.getElementById('exitModal');
    exitModal.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const visitId = button.getAttribute('data-visit-id');
        const visitorName = button.getAttribute('data-visitor-name');
        document.getElementById('exitVisitId').value = visitId;
        document.getElementById('exitVisitorName').textContent = `Uscita per ${visitorName}`;
        exitPad.clear();
    });

    document.getElementById('exit-form').addEventListener('submit', (event) => {
        if (exitPad.isEmpty()) {
            event.preventDefault();
            alert('Inserisci la firma di uscita.');
            return;
        }
        document.getElementById('exitSignatureData').value = exitPad.toDataURL();
    });
</script>
</body>
</html>
