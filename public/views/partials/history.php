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
    <div class="table-responsive table-scroll-history">
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
