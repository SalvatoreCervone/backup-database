<style>
    :root {
        --primary: #4f46e5;
        --primary-soft: #eef2ff;
        --danger: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
        --bg: #f9fafb;
        --border: #e5e7eb;
        --muted: #6b7280;
    }
    body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: var(--bg); padding: 2rem; color: #1f2937; }
    .card { background: white; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 1.5rem; margin-bottom: 1.5rem; }
    table { width: 100%; border-collapse: collapse; margin-top: 0.75rem; }
    th { background: #f3f4f6; padding: 10px 12px; text-align: left; font-weight: 600; border-bottom: 2px solid var(--border); font-size: 0.85rem; color: #374151; }
    td { padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: 0.9rem; vertical-align: middle; }
    .btn { padding: 8px 14px; border-radius: 6px; font-weight: 500; cursor: pointer; border: none; transition: all 0.15s; color: white; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 6px; }
    .btn-sm { padding: 5px 10px; font-size: 0.8rem; }
    .btn-primary { background: var(--primary); }
    .btn-danger { background: var(--danger); }
    .btn-success { background: var(--success); }
    .btn-warning { background: var(--warning); color: #1f2937; }
    .btn-secondary { background: #4b5563; }
    .btn-outline { background: white; color: #374151; border: 1px solid #d1d5db; }
    .btn:hover { opacity: 0.92; transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .badge { padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #e0e7ff; color: var(--primary); display: inline-block; }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-neutral { background: #f3f4f6; color: #374151; }

    /* Mini Menu */
    .mini-menu { display: flex; gap: 8px; flex-wrap: wrap; border-top: 1px solid var(--border); margin-top: 1rem; padding-top: 1rem; }
    .mini-menu-btn { background: #f3f4f6; color: #374151; border: 1px solid transparent; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.88rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.15s; }
    .mini-menu-btn:hover { background: #e5e7eb; }
    .mini-menu-btn.active { background: var(--primary); color: white; }
    .mini-menu-btn.active .badge { background: rgba(255,255,255,0.2); color: white; }
    .tab-panel[hidden] { display: none; }

    /* Forms */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-size: 0.85rem; font-weight: 600; color: #374151; }
    .form-control { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; font-family: inherit; background: #fff; color: #111827; }
    .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }
    .form-hint { font-size: 0.78rem; color: var(--muted); }
    .section-divider { border: 0; border-top: 1px solid var(--border); margin: 1.5rem 0; }

    /* Radio cards (1 of 3 & SMTP mode) */
    .radio-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
    .radio-card { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: #f9fafb; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
    .radio-card:has(input:checked) { border-color: var(--primary); background: var(--primary-soft); }
    .radio-card input[type="radio"] { margin-top: 3px; width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
    .radio-title { font-weight: 600; font-size: 0.9rem; display: block; color: #111827; }
    .radio-desc { font-size: 0.78rem; color: var(--muted); display: block; margin-top: 2px; }

    /* Env summary box */
    .env-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; }
    .env-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-top: 0.5rem; }
    .env-kv { font-size: 0.85rem; }
    .env-kv span { display: block; font-size: 0.75rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; }
    .env-kv strong { color: #111827; font-family: monospace; font-size: 0.85rem; word-break: break-all; }

    /* DataTable toolbar */
    .datatable-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 0.5rem; }
    .datatable-search { max-width: 280px; width: 100%; }
    .table-wrap { overflow-x: auto; }
    .actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }

    /* Test mail box */
    .test-mail-box { background: #f9fafb; border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-top: 1.25rem; }
    .test-mail-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 0.5rem; }
    .test-mail-row input { flex: 1; min-width: 240px; }

    /* Alerts */
    .alert { padding: 10px 14px; border-radius: 6px; font-size: 0.88rem; margin-top: 1rem; display: none; }
    .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; display: block; }
    .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; display: block; }
</style>

{{-- CSRF token for AJAX requests --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    $ms = $mailSettings ?? [];
    $env = $ms['env_fallback'] ?? [];
    $smtpSource = $ms['smtp_source'] ?? 'unconfigured';
    $smtpMode = $ms['smtp_mode'] ?? 'env';
    $notificationType = $ms['notification_type'] ?? 'failure';
    $initialRecipients = $ms['recipients'] ?? [];
    $initialHistory = $mailHistory ?? [];
@endphp

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <h1 style="margin: 0; font-size: 1.5rem;">Database Backups</h1>
            <span id="smtp-status-badge" class="badge {{ $smtpSource === 'panel' ? '' : ($smtpSource === 'env' ? 'badge-success' : 'badge-warning') }}">
                @if ($smtpSource === 'panel')
                    SMTP: Manuale
                @elseif ($smtpSource === 'env')
                    SMTP: .env ({{ $env['mailer'] ?: 'default' }})
                @else
                    SMTP: Non configurato
                @endif
            </span>
        </div>
        <button type="button" class="btn btn-primary" onclick="createAllBackup(event)">Create All Backups</button>
    </div>

    {{-- Mini Menu --}}
    <nav class="mini-menu" role="tablist" aria-label="Menu gestione backup e notifiche">
        <button type="button" class="mini-menu-btn active" role="tab" aria-selected="true" aria-controls="panel-backups" id="tab-backups" onclick="switchTab('backups')">
            Backup
        </button>
        <button type="button" class="mini-menu-btn" role="tab" aria-selected="false" aria-controls="panel-settings" id="tab-settings" onclick="switchTab('settings')">
            Impostazioni
        </button>
        <button type="button" class="mini-menu-btn" role="tab" aria-selected="false" aria-controls="panel-history" id="tab-history" onclick="switchTab('history')">
            Elenco Email Inviate
            <span id="history-count-badge" class="badge badge-neutral">{{ count($initialHistory) }}</span>
        </button>
    </nav>
</div>

{{-- TAB 1: BACKUPS --}}
<section id="panel-backups" class="tab-panel" role="tabpanel" aria-labelledby="tab-backups">
    <div id="backup-logs"></div>

    @if (!$listBackups || count($listBackups) === 0)
        <div class="card"><h3 style="margin: 0;">Nessun backup trovato.</h3></div>
    @else
        @foreach ($listBackups as $backupConnection => $backups)
            <div class="card">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h2 style="margin: 0;">{{ $backupConnection }}</h2>
                    <span class="badge">Connessione attiva</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome File</th>
                                <th>Dimensione</th>
                                <th>Ultima Modifica</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if (empty($backups))
                                <tr><td colspan="4">Nessun file presente.</td></tr>
                            @else
                                @foreach ($backups as $backup)
                                    <tr>
                                        <td style="font-family: monospace; font-size: 0.9rem;">{{ $backup['name'] }}</td>
                                        <td>{{ number_format($backup['size'] / 1024 / 1024, 2) }} MB</td>
                                        <td>{{ $backup['modified'] }}</td>
                                        <td class="actions-cell">
                                            <button type="button" class="btn btn-success btn-sm" onclick="restoreBackup('{{ $backup['name'] }}', '{{ $backupConnection }}', event)">Restore</button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteBackup('{{ $backup['name'] }}', '{{ $backupConnection }}')">Delete</button>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0;">Daily Activity Logs</h2>
            <button type="button" class="btn btn-primary btn-sm" onclick="loadLogs()">Refresh Logs</button>
        </div>
        <div id="daily-logs" style="background: #1e293b; color: #f8fafc; padding: 1rem; border-radius: 6px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto; font-size: 0.85rem;">
            Caricamento log...
        </div>
    </div>
</section>

{{-- TAB 2: IMPOSTAZIONI EMAIL & SMTP --}}
<section id="panel-settings" class="tab-panel" role="tabpanel" aria-labelledby="tab-settings" hidden>
    <div class="card">
        <h2 style="margin: 0 0 0.25rem 0; font-size: 1.25rem;">Notifiche Email &amp; Impostazioni SMTP</h2>
        <span class="form-hint">Scegli una tipologia di notifica, gestisci i destinatari e seleziona la sorgente dei parametri SMTP.</span>

        <form id="mail-settings-form" onsubmit="saveMailSettings(event)" style="margin-top: 1.25rem;">
            {{-- 1 of 3 Notification Type --}}
            <h3 style="margin: 0 0 0.5rem 0; font-size: 1rem;">Tipologia di Email (seleziona 1 delle 3)</h3>
            <div class="radio-grid">
                <label class="radio-card">
                    <input type="radio" name="notification_type" value="success" {{ $notificationType === 'success' ? 'checked' : '' }}>
                    <span>
                        <span class="radio-title">Email quando va tutto bene</span>
                        <span class="radio-desc">Invia conferma solo se tutti i backup della sessione hanno successo.</span>
                    </span>
                </label>

                <label class="radio-card">
                    <input type="radio" name="notification_type" value="failure" {{ $notificationType === 'failure' ? 'checked' : '' }}>
                    <span>
                        <span class="radio-title">Email quando va male</span>
                        <span class="radio-desc">Invia subito un avviso in caso di errore su una connessione.</span>
                    </span>
                </label>

                <label class="radio-card">
                    <input type="radio" name="notification_type" value="summary" {{ $notificationType === 'summary' ? 'checked' : '' }}>
                    <span>
                        <span class="radio-title">Email riepilogativa</span>
                        <span class="radio-desc">Invia sempre il riepilogo completo al termine dell'esecuzione.</span>
                    </span>
                </label>
            </div>

            <hr class="section-divider">

            {{-- Recipients DataTable --}}
            <div class="datatable-toolbar">
                <div>
                    <h3 style="margin: 0; font-size: 1rem;">Indirizzi Destinatari</h3>
                    <span class="form-hint">
                        Aggiungi, modifica o rimuovi gli indirizzi email dei destinatari.
                        @if (!empty($env['recipients']))
                            Se l'elenco è vuoto verrà usato <code>BACKUP_NOTIFICATION_EMAIL</code> (<code>{{ implode(', ', $env['recipients']) }}</code>).
                        @endif
                    </span>
                </div>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <input type="search" id="recipients-search" class="form-control datatable-search" placeholder="Cerca destinatario..." oninput="renderRecipientsTable()">
                    <button type="button" id="btn-add-recipient" class="btn btn-primary" onclick="startAddRecipient()">+ Aggiungi</button>
                </div>
            </div>

            <div id="recipient-inline-error" class="alert" role="alert" style="margin-top: 0.5rem; margin-bottom: 0.5rem;"></div>

            <div class="table-wrap">
                <table id="recipients-datatable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Indirizzo Email</th>
                            <th style="width: 210px;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="recipients-tbody"></tbody>
                </table>
            </div>

            <hr class="section-divider">

            {{-- SMTP Mode Selector (ALL parameters: .env vs Manual) --}}
            <h3 style="margin: 0 0 0.5rem 0; font-size: 1rem;">Impostazioni SMTP (tutti i parametri)</h3>
            <div class="radio-grid">
                <label class="radio-card">
                    <input type="radio" name="smtp_mode" value="env" {{ $smtpMode === 'env' ? 'checked' : '' }} onchange="toggleSmtpMode()">
                    <span>
                        <span class="radio-title">Usa parametri da <code>.env</code></span>
                        <span class="radio-desc">Tutti i parametri SMTP e mittente vengono letti dal file <code>.env</code> di Laravel.</span>
                    </span>
                </label>

                <label class="radio-card">
                    <input type="radio" name="smtp_mode" value="manual" {{ $smtpMode === 'manual' ? 'checked' : '' }} onchange="toggleSmtpMode()">
                    <span>
                        <span class="radio-title">Immissione manuale</span>
                        <span class="radio-desc">Imposta manualmente tutti i parametri SMTP e mittente direttamente dal pannello.</span>
                    </span>
                </label>
            </div>

            {{-- Read-only .env preview --}}
            <div id="smtp-env-panel" class="env-summary" style="{{ $smtpMode === 'env' ? '' : 'display: none;' }}">
                <div style="font-weight: 600; font-size: 0.9rem; color: #111827;">Parametri attivi da <code>.env</code></div>
                <div class="env-summary-grid">
                    <div class="env-kv"><span>Mailer</span><strong>{{ $env['mailer'] ?: 'non impostato' }}</strong></div>
                    <div class="env-kv"><span>Host</span><strong>{{ $env['host'] ?: 'non impostato' }}</strong></div>
                    <div class="env-kv"><span>Porta</span><strong>{{ $env['port'] ?: 'non impostata' }}</strong></div>
                    <div class="env-kv"><span>Crittografia</span><strong>{{ $env['encryption'] ?: 'nessuna' }}</strong></div>
                    <div class="env-kv"><span>Username</span><strong>{{ $env['username'] ?: 'non impostato' }}</strong></div>
                    <div class="env-kv"><span>Password</span><strong>{{ !empty($env['has_password']) ? '•••••••• (impostata)' : 'non impostata' }}</strong></div>
                    <div class="env-kv"><span>Mittente (From)</span><strong>{{ $env['from_address'] ?: 'non impostato' }}</strong></div>
                    <div class="env-kv"><span>Nome Mittente</span><strong>{{ $env['from_name'] ?: 'non impostato' }}</strong></div>
                </div>
            </div>

            {{-- Manual SMTP inputs --}}
            <div id="smtp-manual-panel" style="{{ $smtpMode === 'manual' ? '' : 'display: none;' }}">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="smtp_host">Host SMTP</label>
                        <input
                            type="text"
                            id="smtp_host"
                            name="smtp_host"
                            class="form-control"
                            value="{{ $ms['smtp_host'] ?? '' }}"
                            placeholder="es. smtp.example.com"
                        >
                    </div>

                    <div class="form-group">
                        <label for="smtp_port">Porta SMTP</label>
                        <input
                            type="number"
                            id="smtp_port"
                            name="smtp_port"
                            class="form-control"
                            min="1"
                            max="65535"
                            value="{{ $ms['smtp_port'] ?? '' }}"
                            placeholder="es. 587"
                        >
                    </div>

                    <div class="form-group">
                        <label for="smtp_encryption">Crittografia</label>
                        <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                            <option value="tls" {{ ($ms['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="ssl" {{ ($ms['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : '' }}>SSL</option>
                            <option value="none" {{ ($ms['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : '' }}>Nessuna</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="smtp_username">Username SMTP</label>
                        <input
                            type="text"
                            id="smtp_username"
                            name="smtp_username"
                            class="form-control"
                            autocomplete="off"
                            value="{{ $ms['smtp_username'] ?? '' }}"
                            placeholder="es. user@example.com"
                        >
                    </div>

                    <div class="form-group">
                        <label for="smtp_password">Password SMTP</label>
                        <input
                            type="password"
                            id="smtp_password"
                            name="smtp_password"
                            class="form-control"
                            autocomplete="new-password"
                            placeholder="{{ !empty($ms['has_smtp_password']) ? '•••••••• (salvata nel pannello)' : 'Inserisci password SMTP' }}"
                        >
                        <label id="clear-password-wrapper" style="display: {{ !empty($ms['has_smtp_password']) ? 'flex' : 'none' }}; align-items: center; gap: 6px; font-weight: 400; font-size: 0.8rem; color: #6b7280; cursor: pointer;">
                            <input type="checkbox" id="clear_smtp_password" name="clear_smtp_password">
                            Rimuovi password salvata
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="from_address">Indirizzo Mittente (From)</label>
                        <input
                            type="email"
                            id="from_address"
                            name="from_address"
                            class="form-control"
                            value="{{ $ms['from_address'] ?? '' }}"
                            placeholder="es. backup@example.com"
                        >
                    </div>

                    <div class="form-group">
                        <label for="from_name">Nome Mittente</label>
                        <input
                            type="text"
                            id="from_name"
                            name="from_name"
                            class="form-control"
                            value="{{ $ms['from_name'] ?? '' }}"
                            placeholder="es. Backup Database"
                        >
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 1.25rem;">
                <button type="submit" id="btn-save-mail" class="btn btn-primary">Salva Impostazioni</button>
            </div>

            {{-- Dedicated Single-Recipient Test Email --}}
            <div class="test-mail-box">
                <label for="test_mail_recipient" style="font-weight: 600; font-size: 0.9rem; color: #111827; display: block;">Test Invio Email</label>
                <span class="form-hint">Inserisci una singola email di test. Il test NON viene inviato a tutti i destinatari configurati.</span>
                <div class="test-mail-row">
                    <input
                        type="email"
                        id="test_mail_recipient"
                        class="form-control"
                        placeholder="es. test@example.com"
                        autocomplete="email"
                    >
                    <button type="button" id="btn-test-mail" class="btn btn-success" onclick="sendTestMail()">Invia Email di Test</button>
                </div>
            </div>

            <div id="mail-settings-feedback" class="alert" role="status" aria-live="polite"></div>
        </form>
    </div>
</section>

{{-- TAB 3: ELENCO EMAIL INVIATE --}}
<section id="panel-history" class="tab-panel" role="tabpanel" aria-labelledby="tab-history" hidden>
    <div class="card">
        <div class="datatable-toolbar">
            <div>
                <h2 style="margin: 0; font-size: 1.25rem;">Elenco Email Inviate</h2>
                <span class="form-hint">Storico delle email inviate dal sistema di backup e dei test SMTP.</span>
            </div>
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <input type="search" id="history-search" class="form-control datatable-search" placeholder="Cerca per email, oggetto, tipo..." oninput="renderHistoryTable()">
                <button type="button" id="btn-refresh-history" class="btn btn-primary btn-sm" onclick="loadMailHistory()">Aggiorna</button>
            </div>
        </div>

        <div class="table-wrap">
            <table id="history-datatable">
                <thead>
                    <tr>
                        <th>Data / Ora</th>
                        <th>Tipo</th>
                        <th>Oggetto</th>
                        <th>Destinatari</th>
                        <th>SMTP</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody id="history-tbody"></tbody>
            </table>
        </div>
    </div>
</section>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // State
    let recipientsList = @json(array_values($initialRecipients));
    let editingRecipientIndex = null;
    let isAddingRecipient = false;
    let mailHistoryList = @json($initialHistory);

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function switchTab(tabName) {
        const tabs = ['backups', 'settings', 'history'];
        tabs.forEach(name => {
            const btn = document.getElementById('tab-' + name);
            const panel = document.getElementById('panel-' + name);
            const isActive = name === tabName;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            panel.hidden = !isActive;
        });

        if (tabName === 'history') {
            loadMailHistory();
        }
    }

    async function apiRequest(url, options = {}) {
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            ...(options.headers || {})
        };
        if (options.body && typeof options.body === 'object') {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        const response = await fetch(url, { ...options, headers });
        const contentType = response.headers.get('content-type') || '';
        const data = contentType.includes('application/json') ? await response.json() : await response.text();
        if (!response.ok) {
            let errorMsg = (typeof data === 'object' && data?.message) ? data.message : (typeof data === 'string' && data ? data : response.statusText);
            if (typeof data === 'object' && data?.errors) {
                const details = Object.values(data.errors).flat().join(' ');
                if (details) errorMsg = details;
            }
            throw new Error(errorMsg || `Errore HTTP ${response.status}`);
        }
        return data;
    }

    function showMailFeedback(message, isSuccess) {
        const box = document.getElementById('mail-settings-feedback');
        box.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        box.textContent = message;
    }

    function showRecipientError(message) {
        const box = document.getElementById('recipient-inline-error');
        if (!message) {
            box.className = 'alert';
            box.textContent = '';
            return;
        }
        box.className = 'alert alert-danger';
        box.textContent = message;
    }

    // =========================================================================
    // RECIPIENTS DATATABLE
    // =========================================================================

    function startAddRecipient() {
        showRecipientError('');
        editingRecipientIndex = null;
        isAddingRecipient = true;
        renderRecipientsTable();
        const input = document.getElementById('new-recipient-input');
        if (input) input.focus();
    }

    function cancelAddRecipient() {
        showRecipientError('');
        isAddingRecipient = false;
        renderRecipientsTable();
    }

    function confirmAddRecipient() {
        const input = document.getElementById('new-recipient-input');
        const value = (input?.value || '').trim();

        if (!value || !emailRegex.test(value)) {
            showRecipientError('Inserisci un indirizzo email valido.');
            input?.focus();
            return;
        }

        const exists = recipientsList.some(item => item.toLowerCase() === value.toLowerCase());
        if (exists) {
            showRecipientError('Questo indirizzo email è già presente nella lista.');
            input?.focus();
            return;
        }

        recipientsList.push(value);
        isAddingRecipient = false;
        showRecipientError('');
        renderRecipientsTable();
    }

    function startEditRecipient(index) {
        showRecipientError('');
        isAddingRecipient = false;
        editingRecipientIndex = index;
        renderRecipientsTable();
        const input = document.getElementById('edit-recipient-input');
        if (input) {
            input.focus();
            input.select();
        }
    }

    function cancelEditRecipient() {
        showRecipientError('');
        editingRecipientIndex = null;
        renderRecipientsTable();
    }

    function confirmEditRecipient(index) {
        const input = document.getElementById('edit-recipient-input');
        const value = (input?.value || '').trim();

        if (!value || !emailRegex.test(value)) {
            showRecipientError('Inserisci un indirizzo email valido.');
            input?.focus();
            return;
        }

        const duplicate = recipientsList.some((item, i) => i !== index && item.toLowerCase() === value.toLowerCase());
        if (duplicate) {
            showRecipientError('Questo indirizzo email è già presente nella lista.');
            input?.focus();
            return;
        }

        recipientsList[index] = value;
        editingRecipientIndex = null;
        showRecipientError('');
        renderRecipientsTable();
    }

    function deleteRecipient(index) {
        showRecipientError('');
        if (editingRecipientIndex === index) {
            editingRecipientIndex = null;
        }
        recipientsList.splice(index, 1);
        renderRecipientsTable();
    }

    function renderRecipientsTable() {
        const tbody = document.getElementById('recipients-tbody');
        const query = (document.getElementById('recipients-search')?.value || '').trim().toLowerCase();
        tbody.innerHTML = '';

        if (isAddingRecipient) {
            const tr = document.createElement('tr');
            tr.style.background = '#eef2ff';

            const tdNum = document.createElement('td');
            tdNum.textContent = '+';

            const tdInput = document.createElement('td');
            const input = document.createElement('input');
            input.type = 'email';
            input.id = 'new-recipient-input';
            input.className = 'form-control';
            input.placeholder = 'es. admin@example.com';
            input.style.width = '100%';
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); confirmAddRecipient(); }
                if (e.key === 'Escape') { e.preventDefault(); cancelAddRecipient(); }
            });
            tdInput.appendChild(input);

            const tdActions = document.createElement('td');
            tdActions.className = 'actions-cell';
            const btnSave = document.createElement('button');
            btnSave.type = 'button';
            btnSave.className = 'btn btn-success btn-sm';
            btnSave.textContent = 'Conferma';
            btnSave.onclick = confirmAddRecipient;

            const btnCancel = document.createElement('button');
            btnCancel.type = 'button';
            btnCancel.className = 'btn btn-outline btn-sm';
            btnCancel.textContent = 'Annulla';
            btnCancel.onclick = cancelAddRecipient;

            tdActions.append(btnSave, btnCancel);
            tr.append(tdNum, tdInput, tdActions);
            tbody.appendChild(tr);
        }

        const filtered = recipientsList
            .map((email, index) => ({ email, index }))
            .filter(item => !query || item.email.toLowerCase().includes(query));

        if (filtered.length === 0 && !isAddingRecipient) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 3;
            td.style.color = '#6b7280';
            td.textContent = recipientsList.length === 0
                ? 'Nessun destinatario configurato. Clicca "+ Aggiungi" per inserire un indirizzo email.'
                : 'Nessun destinatario corrisponde alla ricerca.';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }

        filtered.forEach(({ email, index }, visibleIdx) => {
            const tr = document.createElement('tr');

            const tdNum = document.createElement('td');
            tdNum.textContent = String(visibleIdx + 1);

            const tdEmail = document.createElement('td');
            const tdActions = document.createElement('td');
            tdActions.className = 'actions-cell';

            if (editingRecipientIndex === index) {
                const input = document.createElement('input');
                input.type = 'email';
                input.id = 'edit-recipient-input';
                input.className = 'form-control';
                input.value = email;
                input.style.width = '100%';
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { e.preventDefault(); confirmEditRecipient(index); }
                    if (e.key === 'Escape') { e.preventDefault(); cancelEditRecipient(); }
                });
                tdEmail.appendChild(input);

                const btnSave = document.createElement('button');
                btnSave.type = 'button';
                btnSave.className = 'btn btn-success btn-sm';
                btnSave.textContent = 'Salva';
                btnSave.onclick = () => confirmEditRecipient(index);

                const btnCancel = document.createElement('button');
                btnCancel.type = 'button';
                btnCancel.className = 'btn btn-outline btn-sm';
                btnCancel.textContent = 'Annulla';
                btnCancel.onclick = cancelEditRecipient;

                tdActions.append(btnSave, btnCancel);
            } else {
                tdEmail.style.fontFamily = 'monospace';
                tdEmail.textContent = email;

                const btnEdit = document.createElement('button');
                btnEdit.type = 'button';
                btnEdit.className = 'btn btn-warning btn-sm';
                btnEdit.textContent = 'Modifica';
                btnEdit.onclick = () => startEditRecipient(index);

                const btnDel = document.createElement('button');
                btnDel.type = 'button';
                btnDel.className = 'btn btn-danger btn-sm';
                btnDel.textContent = 'Elimina';
                btnDel.onclick = () => deleteRecipient(index);

                tdActions.append(btnEdit, btnDel);
            }

            tr.append(tdNum, tdEmail, tdActions);
            tbody.appendChild(tr);
        });
    }

    // =========================================================================
    // SMTP MODE & SETTINGS SAVE / TEST
    // =========================================================================

    function getSelectedSmtpMode() {
        return document.querySelector('input[name="smtp_mode"]:checked')?.value || 'env';
    }

    function getSelectedNotificationType() {
        return document.querySelector('input[name="notification_type"]:checked')?.value || 'failure';
    }

    function toggleSmtpMode() {
        const mode = getSelectedSmtpMode();
        document.getElementById('smtp-env-panel').style.display = mode === 'env' ? '' : 'none';
        document.getElementById('smtp-manual-panel').style.display = mode === 'manual' ? '' : 'none';
    }

    function updateSmtpStatusBadge(settings) {
        const badge = document.getElementById('smtp-status-badge');
        const source = settings.smtp_source || 'unconfigured';
        const envMailer = settings.env_fallback?.mailer || 'default';

        if (source === 'panel') {
            badge.className = 'badge';
            badge.textContent = 'SMTP: Manuale';
        } else if (source === 'env') {
            badge.className = 'badge badge-success';
            badge.textContent = `SMTP: .env (${envMailer})`;
        } else {
            badge.className = 'badge badge-warning';
            badge.textContent = 'SMTP: Non configurato';
        }

        const passwordInput = document.getElementById('smtp_password');
        const clearWrapper = document.getElementById('clear-password-wrapper');
        const clearCheckbox = document.getElementById('clear_smtp_password');
        passwordInput.value = '';
        clearCheckbox.checked = false;

        if (settings.has_smtp_password) {
            passwordInput.placeholder = '•••••••• (salvata nel pannello)';
            clearWrapper.style.display = 'flex';
        } else {
            passwordInput.placeholder = 'Inserisci password SMTP';
            clearWrapper.style.display = 'none';
        }

        if (Array.isArray(settings.recipients)) {
            recipientsList = [...settings.recipients];
            renderRecipientsTable();
        }
    }

    function saveMailSettings(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-mail');
        btn.disabled = true;
        btn.textContent = 'Salvataggio...';

        const smtpMode = getSelectedSmtpMode();
        const portVal = document.getElementById('smtp_port').value.trim();

        const payload = {
            notification_type: getSelectedNotificationType(),
            recipients: recipientsList,
            smtp_mode: smtpMode,
        };

        if (smtpMode === 'manual') {
            payload.smtp_host = document.getElementById('smtp_host').value.trim();
            payload.smtp_port = portVal === '' ? null : parseInt(portVal, 10);
            payload.smtp_encryption = document.getElementById('smtp_encryption').value;
            payload.smtp_username = document.getElementById('smtp_username').value.trim();
            payload.smtp_password = document.getElementById('smtp_password').value;
            payload.clear_smtp_password = document.getElementById('clear_smtp_password').checked;
            payload.from_address = document.getElementById('from_address').value.trim();
            payload.from_name = document.getElementById('from_name').value.trim();
        }

        apiRequest("{{ route('backup.settings.mail.save') }}", { method: 'POST', body: payload })
            .then(res => {
                showMailFeedback(res.message || 'Impostazioni salvate.', true);
                if (res.settings) {
                    updateSmtpStatusBadge(res.settings);
                }
            })
            .catch(err => {
                showMailFeedback('Errore salvataggio: ' + err.message, false);
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Salva Impostazioni';
            });
    }

    function sendTestMail() {
        const emailInput = document.getElementById('test_mail_recipient');
        const recipient = (emailInput?.value || '').trim();

        if (!recipient || !emailRegex.test(recipient)) {
            showMailFeedback('Inserisci un indirizzo email valido nel campo Test Invio Email.', false);
            emailInput?.focus();
            return;
        }

        const btn = document.getElementById('btn-test-mail');
        btn.disabled = true;
        btn.textContent = 'Invio in corso...';

        apiRequest("{{ route('backup.settings.mail.test') }}", { method: 'POST', body: { recipient } })
            .then(res => {
                showMailFeedback(res.message || 'Email di test inviata.', !!res.status);
                loadMailHistory();
            })
            .catch(err => {
                showMailFeedback(err.message, false);
                loadMailHistory();
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Invia Email di Test';
            });
    }

    // =========================================================================
    // SENT EMAIL HISTORY DATATABLE
    // =========================================================================

    function loadMailHistory() {
        const btn = document.getElementById('btn-refresh-history');
        if (btn) btn.disabled = true;

        apiRequest("{{ route('backup.settings.mail.history') }}")
            .then(res => {
                mailHistoryList = Array.isArray(res?.data) ? res.data : [];
                renderHistoryTable();
            })
            .catch(() => {
                // Keep existing list on transient error
            })
            .finally(() => {
                if (btn) btn.disabled = false;
            });
    }

    function formatDateTime(iso) {
        if (!iso) return '-';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return iso;
        return d.toLocaleString('it-IT', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }

    function renderHistoryTable() {
        const tbody = document.getElementById('history-tbody');
        const countBadge = document.getElementById('history-count-badge');
        const query = (document.getElementById('history-search')?.value || '').trim().toLowerCase();

        if (countBadge) {
            countBadge.textContent = String(mailHistoryList.length);
        }

        tbody.innerHTML = '';

        const filtered = mailHistoryList.filter(item => {
            if (!query) return true;
            const hay = [
                item.type_label || item.type || '',
                item.subject || '',
                (item.recipients || []).join(', '),
                item.smtp_source || '',
                item.status || '',
                item.error || ''
            ].join(' ').toLowerCase();
            return hay.includes(query);
        });

        if (filtered.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 6;
            td.style.color = '#6b7280';
            td.textContent = mailHistoryList.length === 0
                ? 'Nessuna email inviata finora.'
                : 'Nessun risultato corrisponde alla ricerca.';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }

        filtered.forEach(item => {
            const tr = document.createElement('tr');

            const tdDate = document.createElement('td');
            tdDate.style.whiteSpace = 'nowrap';
            tdDate.textContent = formatDateTime(item.sent_at);

            const tdType = document.createElement('td');
            const typeBadge = document.createElement('span');
            typeBadge.className = 'badge ' + (
                item.type === 'success' ? 'badge-success' :
                item.type === 'failure' ? 'badge-danger' :
                item.type === 'test' ? 'badge-warning' : ''
            );
            typeBadge.textContent = item.type_label || item.type || 'Notifica';
            tdType.appendChild(typeBadge);

            const tdSubject = document.createElement('td');
            tdSubject.textContent = item.subject || '-';

            const tdRecipients = document.createElement('td');
            tdRecipients.style.fontFamily = 'monospace';
            tdRecipients.style.fontSize = '0.84rem';
            tdRecipients.textContent = (item.recipients || []).join(', ');

            const tdSmtp = document.createElement('td');
            const smtpBadge = document.createElement('span');
            smtpBadge.className = 'badge ' + (item.smtp_source === 'env' ? 'badge-success' : 'badge-neutral');
            smtpBadge.textContent = item.smtp_source === 'panel' ? 'Manuale' : (item.smtp_source || '-');
            tdSmtp.appendChild(smtpBadge);

            const tdStatus = document.createElement('td');
            const statusBadge = document.createElement('span');
            statusBadge.className = 'badge ' + (item.status === 'sent' ? 'badge-success' : 'badge-danger');
            statusBadge.textContent = item.status === 'sent' ? 'Inviata' : 'Errore';
            tdStatus.appendChild(statusBadge);
            if (item.error) {
                const errDiv = document.createElement('div');
                errDiv.style.fontSize = '0.78rem';
                errDiv.style.color = '#991b1b';
                errDiv.style.marginTop = '4px';
                errDiv.textContent = item.error;
                tdStatus.appendChild(errDiv);
            }

            tr.append(tdDate, tdType, tdSubject, tdRecipients, tdSmtp, tdStatus);
            tbody.appendChild(tr);
        });
    }

    // =========================================================================
    // BACKUP OPERATIONS & LOGS
    // =========================================================================

    function deleteBackup(fileName, connection) {
        if (confirm("Sei sicuro di voler eliminare il backup '" + fileName + "'?")) {
            apiRequest("{{ route('backup.delete') }}", { method: 'POST', body: { file: fileName, connection: connection } })
                .then(res => {
                    if (res.status) {
                        alert(res.message || "Backup eliminato.");
                        location.reload();
                    } else {
                        alert("Errore: " + (res.message || "Impossibile eliminare il backup."));
                    }
                })
                .catch(err => alert("Errore: " + err.message));
        }
    }

    function loadLogs() {
        apiRequest("{{ route('backup.logs') }}")
            .then(data => {
                document.getElementById('daily-logs').textContent = data;
            })
            .catch(() => {
                document.getElementById('daily-logs').textContent = "Impossibile caricare i log.";
            });
    }

    function restoreBackup(fileName, connection, e) {
        if (confirm("ATTENZIONE: Il database " + connection + " verrà sovrascritto con il backup '" + fileName + "'. Procedere?")) {
            const btn = e ? e.target : event.target;
            btn.disabled = true;
            btn.textContent = "Ripristino...";

            apiRequest("{{ route('backup.restore') }}", { method: 'POST', body: { file: fileName, connection: connection } })
                .then(res => {
                    if (res.status) {
                        alert("Ripristino completato con successo!");
                        loadLogs();
                    } else {
                        alert("Errore: " + res.message);
                    }
                })
                .catch(err => alert("Errore critico durante il ripristino: " + err.message))
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = "Restore";
                });
        }
    }

    function createAllBackup(e) {
        const btn = e ? e.target : event.target;
        btn.disabled = true;
        btn.textContent = "Backup in corso...";
        switchTab('backups');
        const logContainer = document.getElementById('backup-logs');
        logContainer.innerHTML = '<div class="card"><strong>Avvio backup in corso...</strong></div>';

        apiRequest("{{ route('backup.create') }}", { method: 'POST' })
            .then(data => {
                logContainer.innerHTML = '';
                let hasFailure = false;
                data.forEach(result => {
                    if (!result.status) hasFailure = true;
                    const div = document.createElement('div');
                    div.className = 'card';
                    div.style.borderLeft = result.status ? '4px solid var(--success)' : '4px solid var(--danger)';

                    const header = document.createElement('div');
                    header.style.marginBottom = '6px';
                    const strong = document.createElement('strong');
                    strong.textContent = result.status ? '✅ Successo' : '❌ Fallito';
                    header.appendChild(strong);
                    if (result.file) {
                        const sep = document.createTextNode(' — ');
                        const small = document.createElement('small');
                        small.textContent = 'File: ';
                        const code = document.createElement('code');
                        code.textContent = result.file;
                        small.appendChild(code);
                        header.append(sep, small);
                    }

                    const pre = document.createElement('pre');
                    pre.style.cssText = 'margin: 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; font-size: 0.85rem; font-family: monospace; white-space: pre-wrap; word-break: break-all;';
                    pre.textContent = result.message || 'Nessun messaggio restituito.';

                    div.append(header, pre);
                    logContainer.appendChild(div);
                });
                loadLogs();
                loadMailHistory();
                if (!hasFailure) {
                    setTimeout(() => location.reload(), 3000);
                }
            })
            .catch(err => {
                logContainer.innerHTML = '';
                const div = document.createElement('div');
                div.className = 'card';
                div.style.borderLeft = '4px solid var(--danger)';
                const strong = document.createElement('strong');
                strong.textContent = '❌ Errore Critico';
                const pre = document.createElement('pre');
                pre.style.cssText = 'margin: 8px 0 0 0; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 4px; padding: 10px; font-size: 0.85rem; font-family: monospace; white-space: pre-wrap;';
                pre.textContent = err.message;
                div.append(strong, pre);
                logContainer.appendChild(div);
                loadLogs();
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = "Create All Backups";
            });
    }

    // Initialize on load
    window.addEventListener('DOMContentLoaded', () => {
        renderRecipientsTable();
        renderHistoryTable();
        toggleSmtpMode();
        loadLogs();
    });
</script>
