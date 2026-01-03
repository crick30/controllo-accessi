<div class="modal fade" id="entryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Registra nuovo ingresso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="entry-form">
                <input type="hidden" name="form_type" value="entry">
                <div class="modal-body">
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
                        <div class="col-12 text-muted small">
                            L'orario di entrata viene salvato automaticamente.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">Conferma ingresso</button>
                </div>
            </form>
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
