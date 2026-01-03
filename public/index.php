<?php
/** Front controller for access control app (clean architecture oriented). */
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
$assetBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
if ($assetBase === '' || $assetBase === '.' || $assetBase === '/') {
    $assetBase = '';
}

$documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
if ($assetBase === '' && $documentRoot !== '' && is_dir($documentRoot . '/public/assets')) {
    $assetBase = '/public';
}

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
            $visitService->registerExit($_POST);
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
$previewHistory = $visitService->historyVisits($historyFilters);
$activeCount = count($activeVisits);
$today = date('Y-m-d');
$recentExits = array_values(array_filter(
    $previewHistory,
    fn($visit) => !empty($visit['exit_time']) && str_starts_with($visit['exit_time'], $today)
));

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
    $auditLogger->log(
        null,
        'View lista accessi',
        sprintf(
            'Filtri: q=%s, from=%s, to=%s, status=%s',
            $historyFilters['search'],
            $historyFilters['from'],
            $historyFilters['to'],
            $historyFilters['status']
        ),
        $performedBy,
        $ipAddress
    );
}

if ($view === 'audit' && $canViewAudit) {
    $auditLogger->log(null, 'View audit log', 'Consultazione log', $performedBy, $ipAddress);
}

require __DIR__ . '/views/layout.php';
