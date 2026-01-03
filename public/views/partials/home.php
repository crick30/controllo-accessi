<div class="concept-lab mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h5 class="mb-0">Dashboard alternativa</h5>
            <div class="text-muted small">Accessi e movimenti in sintesi.</div>
        </div>
    </div>
    <div class="concept-stats">
        <div class="concept-stat success">
            <div class="signal">A</div>
            <div>
                <div class="label">Presenti adesso</div>
                <strong><?= $canViewActive ? $activeCount : '—' ?></strong>
            </div>
        </div>
    </div>
    <div class="concept-grid">
        <div class="concept-card">
            <div class="spark"></div>
            <div class="concept-section-title mb-2">
                <div>
                    <div class="text-muted small">Timeline immediata</div>
                    <h6 class="mb-0">Ultimi ingressi</h6>
                </div>
                <span class="concept-badge">Presenti</span>
            </div>
            <?php if (!$canViewActive): ?>
                <div class="text-muted small">Accesso alla lista consentito solo a operatori o admin autorizzati.</div>
            <?php else: ?>
                <ul class="concept-mini-timeline">
                    <?php if ($activeCount > 0): ?>
                        <?php foreach ($activeVisits as $visit): ?>
                            <li>
                                <div>
                                    <strong><?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                    <span class="meta"><?= htmlspecialchars($visit['company'] ?? 'Visitatore', ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="meta"><?= date('H:i', strtotime($visit['entry_time'])) ?></span>
                                    <button
                                        class="btn btn-sm btn-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#exitModal"
                                        data-visit-id="<?= $visit['id'] ?>"
                                        data-visitor-name="<?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        Registra uscita
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>
                            <div><strong>Nessuna presenza</strong><br><span class="meta">La timeline si popolerà con i prossimi ingressi</span></div>
                            <span class="meta">—</span>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
            <div class="concept-cta mt-2">
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#entryModal">Registra nuovo ingresso</button>
            </div>
        </div>
        <div class="concept-card">
            <div class="concept-section-title mb-2">
                <div>
                    <div class="text-muted small">Ultimi movimenti</div>
                    <h6 class="mb-0">Storico rapido</h6>
                </div>
                <span class="concept-badge">Ultimi 5 usciti</span>
            </div>
            <?php if (count($recentExits) > 0): ?>
                <ul class="concept-mini-timeline">
                    <?php foreach ($recentExits as $visit): ?>
                        <li>
                            <div>
                                <strong><?= htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                                <span class="meta">Uscito · <?= htmlspecialchars($visit['host_last_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <span class="meta"><?= $visit['exit_time'] ? date('d/m H:i', strtotime($visit['exit_time'])) : '—' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="text-muted small">Nessun dato storico disponibile con i filtri correnti.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div class="d-flex flex-wrap gap-2 mb-3"></div>
