(() => {
    const entryCanvas = document.getElementById('entrySignature');
    const exitCanvas = document.getElementById('exitSignature');

    if (!entryCanvas || !exitCanvas) {
        return;
    }

    const computedStyles = getComputedStyle(document.documentElement);
    const canvasColor = computedStyles.getPropertyValue('--canvas');
    const successColor = computedStyles.getPropertyValue('--color-success');
    const primaryColor = computedStyles.getPropertyValue('--color-primary');
    const entryPad = new SignaturePad(entryCanvas, { backgroundColor: canvasColor, penColor: successColor });
    const exitPad = new SignaturePad(exitCanvas, { backgroundColor: canvasColor, penColor: primaryColor });

    function resizeCanvas(canvas, signaturePad, preserveData = false) {
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
        if (canvas.offsetHeight === 0) {
            canvas.style.height = '180px';
        }
        resizeCanvas(canvas, pad, preserveData);
    }

    window.addEventListener('resize', () => {
        ensureCanvasReady(entryCanvas, entryPad);
        ensureCanvasReady(exitCanvas, exitPad);
    });

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

    const entryModal = document.getElementById('entryModal');
    entryModal.addEventListener('shown.bs.modal', () => {
        ensureCanvasReady(entryCanvas, entryPad);
        entryPad.clear();
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
})();
