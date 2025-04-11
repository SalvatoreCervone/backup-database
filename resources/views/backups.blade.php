<style>
    table {
        border: 1px solid #ddd;
        font-family: Arial, sans-serif;
        font-size: 14px;
    }

    th,
    td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background-color: #f2f2f2;
    }

    tr:hover {
        background-color: #f1f1f1;
    }
</style>


<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
    function openDialog(file) {
        let result = confirm("Are you sure you want to delete this backup?");
        if (result) {

            axios.post("{{ route('backup.delete') }}", {
                    file: file
                })
                .then(function(response) {
                    if (response.status === 200) {
                        alert("Backup deleted successfully.");
                        location.reload();
                    } else {
                        alert("Error deleting backup: " + response.data.message);
                    }
                })
                .catch(function(error) {
                    console.error(error);
                    alert("An error occurred while deleting the backup.");
                });
        }
        // window.open("{{ route('backup.delete') }}?file=" + file, "_blank", "width=400,height=300");
    }

    function createAllBackup() {
        axios.post("{{ route('backup.create') }}")
            .then(function(response) {
                if (response.status === 200) {
                    alert("Backup created successfully.");
                    location.reload();
                } else {
                    alert("Error creating backup: " + response.data.message);
                }
            })
            .catch(function(error) {
                console.error(error);
                alert("An error occurred while creating the backup.");
            });
    }
</script>
<input type="button" onclick="createAllBackup()" value="Create all Backup" />
@if (!$listBackups)
    <h1>No backups found</h1>
@else
    @foreach ($listBackups as $backupConnection => $backups)
        <h2>{{ $backupConnection }}</h2>
        <h3>Backups</h3>
        <table>
            <thead>
                <tr>
                    <th>Backup Name</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Destination</th>
                    <th>Action</th>

                </tr>
            </thead>
            <tbody>
                @if (!$backups)
                    <tr>
                        <td colspan="5">No backups found</td>
                    </tr>
                @else
                    @foreach ($backups as $backup)
                        <tr>
                            <td>{{ $backup['name'] }}</td>
                            <td>{{ $backup['size'] }}</td>
                            <td>{{ $backup['modified'] }}</td>
                            <td>{{ $backup['destination'] }}</td>
                            <td>

                                <input type="button" onclick="openDialog('<?php echo str_replace('\\', '\\\\', $backup['destination'] . $backup['name']); ?>')" value="Delete" />
                            </td>
                        </tr>
                    @endforeach
                @endif

            </tbody>
        </table>
    @endforeach
@endif
