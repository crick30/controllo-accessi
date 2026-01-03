# controllo-accessi

Applicazione PHP (senza framework) per registrare ingressi/uscite visitatori con firme digitali, SQLite e controlli di autorizzazione.

## Requisiti
- PHP 8.1+ con estensione SQLite attiva.
- Nessuna dipendenza extra (usa CDN per Bootstrap e SignaturePad).

## Avvio rapido
```bash
php -S 0.0.0.0:8000 -t public public/index.php
```
Poi apri `http://localhost:8000`.

## Configurazione (config.php / variabili env)
- `APP_ENV`: `local` bypassa i controlli di gruppo. Default: `local`.
- `USER_GROUPS`: gruppi dell’utente separati da `;` (solo se non si usa simulazione).
- `APP_SIMULATE_ROLE`: per testare ruoli senza AD (prioritario rispetto all’ambiente). Valori: `user` (form + presenti), `operator` (presenti + storico), `admin` (operator + audit).
- `APP_THEME`: `light` | `dark` | `auto` (default). `auto` usa `APP_LIGHT_START` / `APP_LIGHT_END`.
- `APP_USER`: nome utente salvato nei log di audit (es. SSO).

## Funzioni principali
- Registro ingresso con firma e timestamp automatico.
- Registro uscita con firma obbligatoria (modale con canvas funzionante).
- Lista presenti filtrabile (search, data da/a) ed esportabile in CSV (dashboard).
- Storico accessi per operator/admin con filtri (cerca, date, stato) ed export CSV.
- Audit log visibile solo agli admin, con utente e IP (ISO 27001 ready).
- Tema light/dark personalizzabile o auto.

## Struttura cartelle
- `public/index.php`: front controller; include i template in `public/views/`.
- `public/views/`: layout e partial PHP/HTML.
- `public/assets/css/styles.css`: tutti gli stili dell’interfaccia.
- `public/assets/js/app.js`: logica per firme digitali e interazioni UI.
- `src/`: servizi, repository, config, infrastruttura DB.
- `bootstrap.php`: autoload semplice + configurazione.

## Navigazione UI
- Dashboard: form di ingresso, lista presenti, pulsanti di navigazione.
- Lista accessi: solo operator/admin (o simulazione), accesso tramite pulsante.
- Log di audit: solo admin (o simulazione), accesso tramite pulsante.

## Modalità di configurazione per ambienti diversi

### Simulazione locale (senza AD/Windows Authentication)
1. Imposta `.env` (o variabili del sistema) con `APP_ENV=local` e, se vuoi forzare i permessi, `APP_SIMULATE_ROLE=admin` (o `operator` / `user`).
2. Facoltativo: imposta `APP_USER` per personalizzare il nome salvato nei log di audit.
3. Avvia l’app con il server built-in: `php -S 0.0.0.0:8000 -t public public/index.php`.
4. Assicurati che la root di pubblicazione sia `public/` (necessario per caricare correttamente CSS/JS).

### Ambiente di test su IIS (Windows Authentication)
1. Configura il sito con document root su `public/`.
2. Abilita **Windows Authentication** e disabilita (o limita) l’accesso anonimo.
3. Esponi a PHP le variabili d’ambiente necessarie (ad esempio via `web.config` o FastCGI):
   - `APP_ENV=test` (o `production` se usi un unico profilo).
   - `APP_USER=%REMOTE_USER%` (o `%AUTH_USER%`) per salvare nei log l’utente Windows autenticato.
   - `USER_GROUPS` con i gruppi AD dell’utente separati da `;` per sbloccare le viste operator/admin.
4. Assicurati che l’utente del pool abbia permessi di lettura/scrittura su `storage/database.sqlite`.
5. Verifica che il sito punti a `public/` come document root, così gli asset (`/assets/css`, `/assets/js`) siano raggiungibili.

### Produzione su IIS (Windows Authentication)
1. Replica la configurazione di test con `APP_ENV=production` (o un profilo dedicato, es. `APP_ENV_PROFILE=prod` per caricare `.env.prod`).
2. Mantieni `APP_USER` alimentato dai dati di Windows Authentication per la tracciabilità.
3. Popola `USER_GROUPS` dai gruppi AD effettivi (operator/admin) oppure gestisci il mapping tramite un modulo SSO che compili le variabili d’ambiente.
4. Imposta `APP_THEME`/`APP_LIGHT_*` secondo la policy aziendale e verifica che il sito punti a `public/` per servire correttamente asset CSS/JS.
