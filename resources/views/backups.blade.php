<style>
    :root {
        --primary: #4f46e5;
        --danger: #ef4444;
        --success: #10b981;
        --warning: #f59e0b;
        --bg: #f9fafb;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); padding: 2rem; color: #1f2937; }
    .card { background: white; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 1.5rem; margin-bottom: 2rem; }
    table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    th { background: #f3f4f6; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
    td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
    .btn { padding: 8px 16px; border-radius: 6px; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; color: white; }
    .btn-primary { background: var(--primary); }
    .btn-danger { background: var(--danger); }
    .btn-success { background: var(--success); }
    .btn-warning { background: var(--warning); color: #1f2937; }
    .btn:hover { opacity: 0.9; transform: translateY(-1px); }
    .btn:active { transform: translateY(0); }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .badge { padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #e0e7ff; color: var(--primary); }
</style>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

{{-- CSRF token for axios --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1 style="margin: 0;">Database Backups</h1>
        <button class="btn btn-primary" onclick="createAllBackup()">Create All Backups</button>
    </div>
</div>

<script>
    // Set CSRF token for all axios requests
    axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

    function deleteBackup(fileName, connection) {
        if (confirm("Sei sicuro di voler eliminare il backup '" + fileName + "'?")) {
            axios.post("{{ route('backup.delete') }}", { file: fileName, connection: connection })
                .then(() => { alert("Backup eliminato."); location.reload(); })
                .catch(err => alert("Errore: " + (err.response?.data?.message || err.message)));
        }
    }

    function loadLogs() {
        axios.get("{{ route('backup.logs') }}")
            .then(res => {
                document.getElementById('daily-logs').innerText = res.data;
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
            
            axios.post("{{ route('backup.restore') }}", { file: fileName, connection: connection })
                .then(res => {
                    if (res.data.status) {
                        alert("Ripristino completato con successo!");
                        loadLogs();
                    }
                    else alert("Errore: " + res.data.message);
                })
                .catch(err => alert("Errore critico durante il ripristino: " + (err.response?.data?.message || err.message)))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerText = "Restore";
                });
        }
    }

    function createAllBackup() {
        const btn = event.target;
        btn.disabled = true;
        btn.innerText = "Backup in corso...";
        const logContainer = document.getElementById('backup-logs');
        logContainer.innerHTML = '<div class="card"><strong>Avvio backup in corso...</strong></div>';
        
        axios.post("{{ route('backup.create') }}")
            .then(res => {
                logContainer.innerHTML = '';
                res.data.forEach(result => {
                    const div = document.createElement('div');
                    div.className = 'card';
                    div.style.borderLeft = result.status ? '4px solid var(--success)' : '4px solid var(--danger)';
                    div.innerHTML = `
                        <strong>${result.status ? '✅ Successo' : '❌ Fallito'}</strong>: ${result.message}
                        ${result.file ? `<br><small>File: ${result.file}</small>` : ''}
                    `;
                    logContainer.appendChild(div);
                });
                // Ricarica la pagina dopo 3 secondi per vedere i nuovi file nella tabella
                setTimeout(() => location.reload(), 3000);
            })
            .catch(err => {
                logContainer.innerHTML = `<div class="card" style="border-left: 4px solid var(--danger)"><strong>Errore Critico</strong>: ${err.response?.data?.message || err.message}</div>`;
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
