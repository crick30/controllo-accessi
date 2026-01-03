<?php

use Config\AppConfig;
use Domain\Repositories\AuditLogRepository;
use Domain\Repositories\VisitRepository;
use Domain\Services\AccessControlService;
use Domain\Services\VisitService;
use Infrastructure\Database;

require __DIR__ . '/helpers.php';

/** @var AppConfig $config */
$config = require __DIR__ . '/../bootstrap.php';

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
$uiStyle = 'concept';
$performedBy = $config->appUser;
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$errors = [];
$successMessage = '';
$exitGreeting = '';

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
    streamCsv('visite_attive.csv', ['ID', 'Nome', 'Cognome', 'Azienda', 'Referente', 'Entrata'], array_map(
        fn($visit) => [
            $visit['id'],
            $visit['first_name'],
            $visit['last_name'],
            $visit['company'] ?? '',
            $visit['host_last_name'],
            $visit['entry_time'],
        ],
        $activeVisits
    ));
    exit;
}

$isExportHistory = $accessControl->canViewHistory() && isset($_GET['export']) && $_GET['export'] === 'history_csv';
if ($isExportHistory) {
    $auditLogger->log(null, 'Export storico accessi', 'Records: ' . count($historyVisits), $performedBy, $ipAddress);
    streamCsv('storico_accessi.csv', ['ID', 'Nome', 'Cognome', 'Azienda', 'Referente', 'Entrata', 'Uscita'], array_map(
        fn($visit) => [
            $visit['id'],
            $visit['first_name'],
            $visit['last_name'],
            $visit['company'] ?? '',
            $visit['host_last_name'],
            $visit['entry_time'],
            $visit['exit_time'] ?? '',
        ],
        $historyVisits
    ));
    exit;
}

$isDark = activeTheme($config) === 'dark';
$canViewActive = $accessControl->canViewActiveList();
$canViewAudit = $accessControl->canViewAuditLogs();
$canViewHistory = $accessControl->canViewHistory();

if ($view === 'history' && $canViewHistory) {
    $auditLogger->log(null, 'View lista accessi', sprintf(
        'Filtri: q=%s, from=%s, to=%s, status=%s',
        $historyFilters['search'],
        $historyFilters['from'],
        $historyFilters['to'],
        $historyFilters['status']
    ), $performedBy, $ipAddress);
}

if ($view === 'audit' && $canViewAudit) {
    $auditLogger->log(null, 'View audit log', 'Consultazione log', $performedBy, $ipAddress);
}

return [
    'config' => $config,
    'uiStyle' => $uiStyle,
    'view' => $view,
    'filters' => $filters,
    'historyFilters' => $historyFilters,
    'errors' => $errors,
    'successMessage' => $successMessage,
    'exitGreeting' => $exitGreeting,
    'activeVisits' => $activeVisits,
    'activeCount' => $activeCount,
    'recentExits' => $recentExits,
    'historyVisits' => $historyVisits,
    'auditLogs' => $auditLogs,
    'isDark' => $isDark,
    'canViewActive' => $canViewActive,
    'canViewAudit' => $canViewAudit,
    'canViewHistory' => $canViewHistory,
];
