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

## Guida produzione
### Principi base
- Usa un web server (Apache/Nginx) con PHP-FPM e imposta `public/` come document root. Il file `index.php` nella root serve solo per il server PHP built-in.
- Assicurati che la cartella `storage/` (creata automaticamente accanto a `config.php` alla prima esecuzione) sia scrivibile dal processo PHP: il database SQLite vive qui (`storage/database.sqlite`).
- In produzione non usare il server integrato: preferisci un VirtualHost/ServerBlock con HTTPS e restrizioni di accesso alla root.

### Prima installazione
1) **Clona e installa dipendenze PHP** (nessun composer richiesto; basta PHP 8.1+ con SQLite).
2) **Crea il file `.env`** nella root del progetto (stesso livello di `config.php`). Esempio minimo per produzione:
   ```bash
   APP_ENV=production
   APP_USER=portal-sso  # utente che compare nei log di audit (es. header SSO)
   USER_GROUPS="CN=AccessOperators,OU=Security,DC=example,DC=com;CN=AccessAdmins,OU=Security,DC=example,DC=com"  # se non hai integrazione AD diretta
   APP_THEME=auto
   ```
   - Imposta `APP_ENV=production` per abilitare i controlli di gruppo.
   - `USER_GROUPS` viene usato solo se non hai una sorgente AD: è la lista di gruppi dell’utente loggato separati da `;`.
   - Per profili differenti crea `.env.production` o esporta `APP_ENV_PROFILE=production`: verrà caricato dopo `.env`.
3) **Configura il web server** puntando `DocumentRoot` o `root` a `<project>/public` e abilita PHP-FPM. Blocca l’accesso diretto alla root del progetto.
4) **Permessi**: verifica che l’utente PHP possa creare/leggere/scrivere `storage/database.sqlite`. In ambienti con SELinux/AppArmor potrebbe servire un contesto dedicato.

### Aggiornamenti
1) **Metti il sito in manutenzione** (se necessario) e blocca nuovi accessi.
2) **Backup**: copia `storage/database.sqlite` (e l’eventuale `.env`/`.env.*`). Esempio: `cp storage/database.sqlite storage/database.sqlite.$(date +%F-%H%M).bak`.
3) **Aggiorna il codice** (git pull o deploy artefatto) mantenendo `config.php` e i file `.env`.
4) **Migrazioni**: l’app esegue automaticamente la creazione/aggiornamento delle tabelle su SQLite all’avvio (`Infrastructure/Database.php`). Non serve una procedura manuale.
5) **Verifiche post-deploy**:
   - Apri la home e registra un ingresso/uscita di prova.
   - Esporta CSV di presenti/storico (se il ruolo lo consente) per validare permessi e scrittura su disco.
   - Controlla i log di audit: devono riportare utente (`APP_USER` o header SSO) e IP.

### Variabili e profili
- `APP_ENV_FILE`: percorso alternativo di un file `.env` da caricare prima di `.env`/`.env.<profile>`.
- `APP_ENV_PROFILE`: carica `.env.<profile>` dopo `.env` (utile per distinguere `production`, `staging`, ecc.).
- `APP_ENV`: se impostato a `local` disabilita i controlli di gruppo; con `production` li attiva.
- `APP_SIMULATE_ROLE`: **solo per test** (`user` | `operator` | `admin`), ha priorità rispetto ai gruppi reali.
