<div class="hero">
    <div class="logo-mark">CA</div>
    <div>
        <h1 class="h4 mb-1">Benvenuto nel sistema di controllo accessi</h1>
        <p class="mb-0 text-muted">Registra rapidamente ingressi e uscite dei visitatori con firme digitali sicure.</p>
    </div>
    <div class="ms-auto d-flex flex-column align-items-end gap-2">
        <div class="concept-cta">
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#entryModal">Registra nuovo ingresso</button>
            <?php if ($canViewHistory): ?>
                <a class="btn btn-sm btn-outline-primary" href="?view=history">Lista accessi</a>
            <?php endif; ?>
            <?php if ($canViewAudit): ?>
                <a class="btn btn-sm btn-outline-secondary" href="?view=audit">Log di audit</a>
            <?php endif; ?>
            <?php if ($view !== 'home'): ?>
                <a class="btn btn-sm btn-outline-dark" href="?view=home">Torna alla home</a>
            <?php endif; ?>
        </div>
    </div>
</div>
