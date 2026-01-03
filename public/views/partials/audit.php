<div class="section-card mt-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <div class="text-muted small">Storico delle azioni</div>
            <h5 class="mb-0">Log di audit (ISO 27001 ready)</h5>
        </div>
        <span class="badge bg-info bg-gradient badge-pill">Solo Admin</span>
    </div>
    <div class="table-responsive table-scroll-audit">
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
