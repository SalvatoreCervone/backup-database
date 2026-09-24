# LARAVEL BACKUP-DATABASE

[![Laravel Backup Database](https://github.com/SalvatoreCervone/backup-database/blob/main/images/backup-database.jpeg)](https://github.com/SalvatoreCervone/backup-database)

A powerful and flexible Laravel package to manage your database backups and restorations. **The missing MSSQL (SQL Server) backup solution for Laravel** — also supports MySQL, with the ability to save backups to local or remote shares via **SMB**.

> **Why this package?** Popular backup packages like `spatie/laravel-backup` do **not support Microsoft SQL Server**. If you work with MSSQL in a Laravel environment, this is the package for you.

## Key Features

- [x] **First-Class MSSQL Support**: Full backup and restore via `sqlcmd`, with `SINGLE_USER` mode for safe restores.
- [x] **Multi-Database Support**: Backup one or more databases in a single operation.
- [x] **Driver Pattern**: Native support for MSSQL and MySQL.
- [x] **Database Restoration**: Restore your databases directly from the dashboard or via code.
- [x] **SMB Integration**: Save and manage backups on SMB shares (via `smbclient`).
- [x] **MSSQL Compression**: Optional native `WITH COMPRESSION` for smaller backup files.
- [x] **Backup Verification**: Verify backup integrity with `RESTORE VERIFYONLY` (MSSQL).
- [x] **Automatic Cleanup**: Backup rotation with "Soft Delete" support (moving to a trash folder).
- [x] **Email Notifications**: Receive automatic email alerts when a backup fails.
- [x] **Activity Logging**: Daily log files to track every operation (backup, restore, delete).
- [x] **Modern Dashboard**: Web interface to monitor, create, restore backups, and view logs.
- [x] **Security**: Configurable middleware, authorization gates, and path traversal protection.

---

## Installation

You can install the package via composer:

```bash
composer require salvatorecervone/backup-database
```

Next, publish the configuration file:

```bash
php artisan vendor:publish --tag="backup-database-config"
```

---

## Configuration

The configuration file is located at `config/backup-database.php`.

### Route Protection (Middleware)

By default, the dashboard and all backup routes are protected by `web` and `auth` middleware. You can customize this:

```php
'middleware' => ['web', 'auth', 'can:admin'],

'route_prefix' => 'backups',

// Optional: gate-based authorization (define the gate in your AuthServiceProvider)
'authorization_gate' => 'viewBackups',
```

### Connections
You can configure multiple connections:

```php
'listconnections' => [
    [
        'connection' => 'sqlsrv', // Connection name in your database.php
        'daily' => true,
        'datetimeFormat' => 'Y-m-d_H-i',
        'destinationpath' => '//192.168.1.100/backups/', // Supports SMB shares or local paths
        'days_for_delete' => 7, // Keep backups for 7 days
        'soft_delete' => true, // Move to /trash instead of permanent deletion
    ],
],
```

### Binary Paths
If the required tools are not in your system PATH, you can specify their locations:

```php
'bin_paths' => [
    'sqlcmd' => '/opt/mssql-tools18/bin/sqlcmd',
    'mysqldump' => 'mysqldump',
    'mysql' => 'mysql',
    'smbclient' => 'smbclient',
],
```

### Email Notifications
Set an email address to receive alerts on backup failure. Ensure your Laravel SMTP settings are configured in your app's `.env`.

```php
'notification_email' => 'admin@example.com',
```

### Logging
By default, the package logs all activities to `storage/logs/backup-database-YYYY-MM-DD.log`. You can toggle this feature:

```php
'enable_logging' => true,
```

---

## Usage

### Web Dashboard
Access the dashboard by navigating to:
`http://your-app.test/backups`

From the dashboard you can:
- View all existing backups.
- Manually run backups for all connections.
- **Restore** a database from a specific file.
- Delete old backups.

### Artisan Command
To automate backups (e.g., via Laravel's scheduler):

```bash
php artisan backup-database:backup
```

The command returns a non-zero exit code if any backup fails, making it suitable for monitoring tools.

### Scheduling
Add the command to your `routes/console.php` (or `Kernel.php` for older Laravel versions):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup-database:backup')->dailyAt('03:00');
```

### Programmatic Usage

You can also use the service directly in your code:

```php
use SalvatoreCervone\BackupDatabase\Facades\BackupDatabase;

// Run backup for all connections
$results = BackupDatabase::backup();

// Get status/file list
$status = BackupDatabase::getStatus();

// Restore a specific backup (connection name + file basename)
$result = BackupDatabase::restore('sqlsrv', 'MyDB_2026-09-24.bak');

// Delete a backup file
$result = BackupDatabase::delete('sqlsrv', 'MyDB_old.bak');
```

---

## Important Notes for MSSQL Users

### How MSSQL Backup Works

The `BACKUP DATABASE ... TO DISK` command is executed **by the SQL Server engine**, not by PHP. This means:

- The **destination path** must be accessible to the **SQL Server service account** (e.g., `NT SERVICE\MSSQLSERVER` on Windows, or `mssql` on Linux).
- If Laravel and SQL Server are on **different machines**, the destination path must be a **network share (UNC path)** that SQL Server can write to (e.g., `\\192.168.1.100\backups\`).
- If they are on the **same machine**, a local path works fine.

### MSSQL Compression

Enable native backup compression by adding `'compression' => true` to your connection config. This uses SQL Server's `WITH COMPRESSION` option, which can reduce backup sizes by 50-80%.

---

## System Requirements

- PHP 8.3 or higher
- Laravel 10.x, 11.x, or 12.x
- **MSSQL**: `sqlcmd` installed on the server.
- **MySQL**: `mysqldump` and `mysql` client installed.
- **SMB**: `smbclient` installed (required for SMB shares on Linux).

---

## Credits

- [Salvatore Cervone](https://github.com/SalvatoreCervone)
- [All Contributors](https://github.com/SalvatoreCervone/backup-database/contributors)

## License

MIT License. Please see the [LICENSE](LICENSE.md) file for more information.
