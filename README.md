# controllo-accessi

Applicazione PHP (senza framework) per registrare ingressi/uscite visitatori con firme digitali, SQLite e controlli di autorizzazione.

## Requisiti
- PHP 8.1+ con estensione SQLite attiva.
- Nessuna dipendenza extra (usa CDN per Bootstrap e SignaturePad).

## Avvio rapido
```bash
php -S 0.0.0.0:8000 index.php
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
- `public/index.php`: front controller + UI.
- `src/`: servizi, repository, config, infrastruttura DB.
- `bootstrap.php`: autoload semplice + configurazione.

## Navigazione UI
- Dashboard: form di ingresso, lista presenti, pulsanti di navigazione.
- Lista accessi: solo operator/admin (o simulazione), accesso tramite pulsante.
- Log di audit: solo admin (o simulazione), accesso tramite pulsante.

## Nota per il deploy
Configura un webserver che punti a `public/` come document root; `index.php` root è solo un forwarder per il server built-in.
