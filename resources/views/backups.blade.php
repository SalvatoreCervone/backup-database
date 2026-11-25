<style>
    :root {
        --primary: #4f46e5;
        --danger: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
        --bg: #f9fafb;
    }
    body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: var(--bg); padding: 2rem; color: #1f2937; }
    .card { background: white; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 1.5rem; margin-bottom: 2rem; }
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    th { background: #f3f4f6; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
    td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
    .btn { padding: 8px 16px; border-radius: 6px; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; color: white; font-size: 0.9rem; }
    .btn-primary { background: var(--primary); }
    .btn-danger { background: var(--danger); }
    .btn-success { background: var(--success); }
    .btn-warning { background: var(--warning); color: #1f2937; }
    .btn-secondary { background: #4b5563; }
    .btn:hover { opacity: 0.9; transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .badge { padding: 4px 10px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #e0e7ff; color: var(--primary); display: inline-block; }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-size: 0.85rem; font-weight: 600; color: #374151; }
    .form-control { padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; font-family: inherit; background: #fff; color: #111827; }
    .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15); }
    .form-hint { font-size: 0.78rem; color: #6b7280; }
    .flag-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem; }
    .flag-item { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; cursor: pointer; }
    .flag-item input[type="checkbox"] { margin-top: 3px; width: 16px; height: 16px; accent-color: var(--primary); cursor: pointer; }
    .flag-title { font-weight: 600; font-size: 0.9rem; display: block; color: #111827; }
    .flag-desc { font-size: 0.78rem; color: #6b7280; display: block; margin-top: 2px; }
    .section-divider { border: 0; border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }
    .alert { padding: 10px 14px; border-radius: 6px; font-size: 0.88rem; margin-top: 1rem; display: none; }
    .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; display: block; }
    .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; display: block; }
    details.settings-card > summary { list-style: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    details.settings-card > summary::-webkit-details-marker { display: none; }
    .settings-summary-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
</style>

{{-- CSRF token for AJAX requests --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <h1 style="margin: 0;">Database Backups</h1>
        <button class="btn btn-primary" onclick="createAllBackup(event)">Create All Backups</button>
    </div>
</div>

@php
    $ms = $mailSettings ?? [];
    $env = $ms['env_fallback'] ?? [];
    $smtpSource = $ms['smtp_source'] ?? 'unconfigured';
@endphp

<details class="card settings-card" id="mail-settings-panel">
    <summary>
        <div>
            <h2 style="margin: 0; font-size: 1.25rem;">Notifiche Email &amp; Impostazioni SMTP</h2>
            <span class="form-hint">Configura i flag di invio, i destinatari e i parametri SMTP (se vuoti usa <code>.env</code>)</span>
        </div>
        <div class="settings-summary-meta">
            <span id="smtp-status-badge" class="badge {{ $smtpSource === 'panel' ? '' : ($smtpSource === 'env' ? 'badge-success' : 'badge-warning') }}">
                @if ($smtpSource === 'panel')
                    SMTP: Pannello
                @elseif ($smtpSource === 'env')
                    SMTP: .env ({{ $env['mailer'] ?: 'default' }})
                @else
                    SMTP: Non configurato
                @endif
            </span>
            <span class="btn btn-secondary" style="pointer-events: none; padding: 6px 12px; font-size: 0.82rem;">Apri / Chiudi</span>
        </div>
    </summary>

    <form id="mail-settings-form" onsubmit="saveMailSettings(event)" style="margin-top: 1.5rem;">
        <h3 style="margin: 0 0 0.75rem 0; font-size: 1rem;">Tipologie di Email</h3>
        <div class="flag-list">
            <label class="flag-item">
                <input type="checkbox" name="notify_on_success" id="notify_on_success" {{ !empty($ms['notify_on_success']) ? 'checked' : '' }}>
                <span>
                    <span class="flag-title">Email quando va tutto bene</span>
                    <span class="flag-desc">Invia conferma solo se tutti i backup della sessione hanno successo.</span>
                </span>
            </label>

            <label class="flag-item">
                <input type="checkbox" name="notify_on_failure" id="notify_on_failure" {{ !empty($ms['notify_on_failure']) ? 'checked' : '' }}>
                <span>
                    <span class="flag-title">Email quando va male</span>
                    <span class="flag-desc">Invia subito un avviso in caso di errore su una connessione.</span>
                </span>
            </label>

            <label class="flag-item">
                <input type="checkbox" name="notify_summary" id="notify_summary" {{ !empty($ms['notify_summary']) ? 'checked' : '' }}>
                <span>
                    <span class="flag-title">Email riepilogativa</span>
                    <span class="flag-desc">Invia sempre il riepilogo completo al termine dell'esecuzione.</span>
                </span>
            </label>
        </div>

        <div class="form-group">
            <label for="mail_recipients">Indirizzi destinatari</label>
            <textarea
                id="mail_recipients"
                name="recipients"
                class="form-control"
                rows="2"
                placeholder="{{ !empty($env['recipients']) ? 'Fallback da .env: '.$env['recipients'] : 'es. admin@example.com, ops@example.com' }}"
            >{{ $ms['recipients'] ?? '' }}</textarea>
            <span class="form-hint">
                Inserisci uno o più indirizzi separati da virgola, punto e virgola o a capo.
                @if (!empty($env['recipients']))
                    Se lasciato vuoto verrà usato <code>BACKUP_NOTIFICATION_EMAIL</code> (<code>{{ $env['recipients'] }}</code>).
                @endif
            </span>
        </div>

        <hr class="section-divider">

        <div style="display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; margin-bottom: 0.75rem;">
            <h3 style="margin: 0; font-size: 1rem;">Impostazioni SMTP</h3>
            <span class="form-hint">Lascia vuoti i campi per verificare e usare automaticamente il file <code>.env</code> di Laravel.</span>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="smtp_host">Host SMTP</label>
                <input
                    type="text"
                    id="smtp_host"
                    name="smtp_host"
                    class="form-control"
                    value="{{ $ms['smtp_host'] ?? '' }}"
                    placeholder="{{ !empty($env['host']) ? 'Da .env: '.$env['host'] : 'es. smtp.example.com' }}"
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
                    placeholder="{{ !empty($env['port']) ? 'Da .env: '.$env['port'] : 'es. 587' }}"
                >
            </div>

            <div class="form-group">
                <label for="smtp_encryption">Crittografia</label>
                <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                    <option value="" {{ ($ms['smtp_encryption'] ?? '') === '' ? 'selected' : '' }}>
                        Usa .env ({{ !empty($env['encryption']) ? $env['encryption'] : 'nessuna' }})
                    </option>
                    <option value="tls" {{ ($ms['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ ($ms['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                    <option value="none" {{ ($ms['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' }}>Nessuna</option>
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
                    placeholder="{{ !empty($env['username']) ? 'Da .env: '.$env['username'] : 'es. user@example.com' }}"
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
                    placeholder="{{ !empty($ms['has_smtp_password']) ? '•••••••• (salvata nel pannello)' : (!empty($env['has_password']) ? 'Impostata in .env' : 'Non impostata') }}"
                >
                <label id="clear-password-wrapper" style="display: {{ !empty($ms['has_smtp_password']) ? 'flex' : 'none' }}; align-items: center; gap: 6px; font-weight: 400; font-size: 0.8rem; color: #6b7280; cursor: pointer;">
                    <input type="checkbox" id="clear_smtp_password" name="clear_smtp_password">
                    Rimuovi password salvata nel pannello (usa <code>.env</code>)
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
                    placeholder="{{ !empty($env['from_address']) ? 'Da .env: '.$env['from_address'] : 'es. backup@example.com' }}"
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
                    placeholder="{{ !empty($env['from_name']) ? 'Da .env: '.$env['from_name'] : 'es. Backup Database' }}"
                >
            </div>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 1.25rem;">
            <button type="submit" id="btn-save-mail" class="btn btn-primary">Salva Impostazioni</button>
            <button type="button" id="btn-test-mail" class="btn btn-success" onclick="sendTestMail()">Invia Email di Test</button>
        </div>

        <div id="mail-settings-feedback" class="alert" role="status" aria-live="polite"></div>
    </form>
</details>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

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
        box.innerText = message;
    }

    function updateSmtpStatusBadge(settings) {
        const badge = document.getElementById('smtp-status-badge');
        const source = settings.smtp_source || 'unconfigured';
        const envMailer = settings.env_fallback?.mailer || 'default';

        if (source === 'panel') {
            badge.className = 'badge';
            badge.innerText = 'SMTP: Pannello';
        } else if (source === 'env') {
            badge.className = 'badge badge-success';
            badge.innerText = `SMTP: .env (${envMailer})`;
        } else {
            badge.className = 'badge badge-warning';
            badge.innerText = 'SMTP: Non configurato';
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
            passwordInput.placeholder = settings.env_fallback?.has_password ? 'Impostata in .env' : 'Non impostata';
            clearWrapper.style.display = 'none';
        }
    }

    function saveMailSettings(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-save-mail');
        btn.disabled = true;
        btn.innerText = 'Salvataggio...';

        const portVal = document.getElementById('smtp_port').value.trim();
        const encryptionVal = document.getElementById('smtp_encryption').value;

        const payload = {
            notify_on_success: document.getElementById('notify_on_success').checked,
            notify_on_failure: document.getElementById('notify_on_failure').checked,
            notify_summary: document.getElementById('notify_summary').checked,
            recipients: document.getElementById('mail_recipients').value.trim(),
            smtp_host: document.getElementById('smtp_host').value.trim(),
            smtp_port: portVal === '' ? null : parseInt(portVal, 10),
            smtp_encryption: encryptionVal === '' ? null : encryptionVal,
            smtp_username: document.getElementById('smtp_username').value.trim(),
            smtp_password: document.getElementById('smtp_password').value,
            clear_smtp_password: document.getElementById('clear_smtp_password').checked,
            from_address: document.getElementById('from_address').value.trim(),
            from_name: document.getElementById('from_name').value.trim(),
        };

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
                btn.innerText = 'Salva Impostazioni';
            });
    }

    function sendTestMail() {
        const btn = document.getElementById('btn-test-mail');
        btn.disabled = true;
        btn.innerText = 'Invio in corso...';

        apiRequest("{{ route('backup.settings.mail.test') }}", { method: 'POST', body: {} })
            .then(res => {
                showMailFeedback(res.message || 'Email di test inviata.', !!res.status);
            })
            .catch(err => {
                showMailFeedback(err.message, false);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerText = 'Invia Email di Test';
            });
    }

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
                document.getElementById('daily-logs').innerText = data;
            })
            .catch(() => {
                document.getElementById('daily-logs').innerText = "Impossibile caricare i log.";
            });
    }

    // Carica i log all'avvio
    window.onload = loadLogs;

    function restoreBackup(fileName, connection) {
        if (confirm("ATTENZIONE: Il database " + connection + " verrà sovrascritto con il backup '" + fileName + "'. Procedere?")) {
            const btn = event.target;
            btn.disabled = true;
            btn.innerText = "Ripristino...";
            
            apiRequest("{{ route('backup.restore') }}", { method: 'POST', body: { file: fileName, connection: connection } })
                .then(res => {
                    if (res.status) {
                        alert("Ripristino completato con successo!");
                        loadLogs();
                    }
                    else alert("Errore: " + res.message);
                })
                .catch(err => alert("Errore critico durante il ripristino: " + err.message))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerText = "Restore";
                });
        }
    }

    function createAllBackup(e) {
        const btn = e ? e.target : event.target;
        btn.disabled = true;
        btn.innerText = "Backup in corso...";
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
                    div.innerHTML = `
                        <div style="margin-bottom: 6px;">
                            <strong>${result.status ? '✅ Successo' : '❌ Fallito'}</strong>
                            ${result.file ? ` &mdash; <small>File: <code>${result.file}</code></small>` : ''}
                        </div>
                        <pre style="margin: 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px; font-size: 0.85rem; font-family: monospace; white-space: pre-wrap; word-break: break-all;">${result.message || 'Nessun messaggio restituito.'}</pre>
                    `;
                    logContainer.appendChild(div);
                });
                loadLogs();
                // Ricarica solo se tutti i backup hanno avuto successo
                if (!hasFailure) {
                    setTimeout(() => location.reload(), 3000);
                }
            })
            .catch(err => {
                logContainer.innerHTML = `
                    <div class="card" style="border-left: 4px solid var(--danger)">
                        <strong>❌ Errore Critico</strong>
                        <pre style="margin: 8px 0 0 0; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 4px; padding: 10px; font-size: 0.85rem; font-family: monospace; white-space: pre-wrap;">${err.message}</pre>
                    </div>
                `;
                loadLogs();
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerText = "Create All Backups";
            });
    }
</script>

<div id="backup-logs"></div>

@if (!$listBackups || count($listBackups) === 0)
    <div class="card"><h3>Nessun backup trovato.</h3></div>
@else
    @foreach ($listBackups as $backupConnection => $backups)
        <div class="card">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h2 style="margin: 0;">{{ $backupConnection }}</h2>
                <span class="badge">Connessione attiva</span>
            </div>
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
                                <td style="display: flex; gap: 8px;">
                                    <button class="btn btn-success" onclick="restoreBackup('{{ $backup['name'] }}', '{{ $backupConnection }}')">Restore</button>
                                    <button class="btn btn-danger" onclick="deleteBackup('{{ $backup['name'] }}', '{{ $backupConnection }}')">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    @endforeach
@endif

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2 style="margin: 0;">Daily Activity Logs</h2>
        <button class="btn btn-primary" onclick="loadLogs()">Refresh Logs</button>
    </div>
    <div id="daily-logs" style="background: #1e293b; color: #f8fafc; padding: 1rem; border-radius: 6px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto; font-size: 0.85rem;">
        Caricamento log...
    </div>
</div>
