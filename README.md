# PHP Native PDO Session Storage

Simple, high-performance package for storing PHP sessions in a MySQL database using native `\PDO`. This package provides a robust database-backed session handler that works seamlessly with both native PHP applications and the Nette Framework.

## 🎯 Key Features

- **Native PDO Implementation** - Uses PHP's native PDO extension for reliable database connections with prepared statements
- **Framework Agnostic** - Works with native PHP applications out of the box
- **Nette Framework Integration** - Includes DI extension for seamless Nette integration
- **Doctrine ORM Support** - Optional entity mapping for Doctrine-based applications
- **Automatic Garbage Collection** - Probabilistic GC with configurable session lifetime (14 days default)
- **CLI Safe** - Automatically skips session operations in CLI mode to prevent errors
- **Binary Data Support** - Automatic Base64 encoding fallback for problematic session data
- **Tracy Debugger Integration** - Logs errors to Tracy when available
- **Custom Table Names** - Configurable database table name for multi-tenant applications

## 🏗️ Architecture Overview

The package consists of three main components that work together to provide database session storage:

```
┌─────────────────────────────────────────────────────────────────┐
│                     Application Layer                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   Native PHP                          Nette Framework           │
│   ───────────                         ───────────────           │
│   session_set_save_handler()          SessionExtension          │
│           │                                  │                  │
│           │                                  │                  │
│           ▼                                  ▼                  │
│   ┌───────────────────────────────────────────────────┐        │
│   │              SessionStorage                        │        │
│   │         implements \SessionHandlerInterface        │        │
│   │                                                    │        │
│   │  • open()    - Initialize connection               │        │
│   │  • close()   - Cleanup                             │        │
│   │  • read()    - Load session data                   │        │
│   │  • write()   - Persist session data                │        │
│   │  • destroy() - Remove session                      │        │
│   │  • gc()      - Garbage collection                  │        │
│   └───────────────────────────────────────────────────┘        │
│                           │                                     │
│                           │ PDO                                 │
│                           ▼                                     │
│   ┌───────────────────────────────────────────────────┐        │
│   │              MySQL Database                        │        │
│   │         core__session_storage table                │        │
│   │                                                    │        │
│   │  • id (varchar 26) - Session identifier            │        │
│   │  • haystack (longtext) - Serialized data           │        │
│   │  • last_update (datetime) - Last modification      │        │
│   └───────────────────────────────────────────────────┘        │
│                                                                 │
│   Optional: SessionEntity (Doctrine ORM mapping)                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## 🧩 Components

### SessionStorage

The core component implementing PHP's `\SessionHandlerInterface`. It handles all session operations:

- **Connection Management**: Creates a dedicated PDO connection with proper character encoding (UTF-8) and error handling
- **Session Reading**: Retrieves session data from database, handles Base64-encoded data transparently
- **Session Writing**: Persists session data with automatic timestamp updates, falls back to Base64 encoding for binary data
- **Session Destruction**: Removes session records from the database
- **Garbage Collection**: Probabilistic cleanup (0.1% chance per request) removing sessions older than 14 days, limited to 500 records per run

### SessionExtension

A Nette Framework DI extension that provides:

- **Automatic Service Registration**: Registers `SessionStorage` as a service
- **Doctrine Integration**: Automatically registers entity paths when Doctrine is present
- **DBAL Connection Reuse**: Can inherit database credentials from existing Doctrine DBAL connection
- **Session Handler Setup**: Automatically configures Nette's Session to use the database handler

### SessionEntity

A Doctrine ORM entity class providing:

- **Entity Mapping**: Proper ORM annotations for the session table
- **Type Safety**: Strongly typed properties for session data
- **Immutable Timestamps**: Uses `DateTimeImmutable` for the last update field

## 📦 Installation

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/session) and
[GitHub](https://github.com/baraja-core/session).

To install, simply use the command:

```shell
$ composer require baraja-core/session
```

You can use the package manually by creating an instance of the internal classes, or register a DIC extension to link the services directly to the Nette Framework.

### Requirements

- PHP 8.1 or higher
- PDO extension (`ext-PDO`)
- MySQL database

## 🗄️ Database Setup

Create the database table `core__session_storage` (table name can be configured) or use Doctrine for automatic schema generation.

### MySQL Table Schema

```sql
SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE `core__session_storage` (
  `id` varchar(26) COLLATE utf8_unicode_ci NOT NULL,
  `haystack` longtext COLLATE utf8_unicode_ci NOT NULL,
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
```

> **Note**: The table can be used with Doctrine ORM or as a standalone MySQL table.

### Using Doctrine Migrations

If you're using Doctrine ORM, the schema can be generated automatically from the `SessionEntity` class. Simply run your standard Doctrine migration commands:

```shell
$ php bin/console doctrine:schema:update --force
```

## ⚙️ Configuration

### Native PHP Usage

For standalone PHP applications, create a `SessionStorage` instance and register it as the session handler:

```php
$handler = new \Baraja\Session\SessionStorage(
   '127.0.0.1',      // MySQL host
   'my_application', // Database name
   'root',           // Username
   '****'            // Password
);

session_set_save_handler($handler);
session_start();
```

> **Warning**: The session handler must be registered **before** calling `session_start()`!

### Nette Framework Usage

Register the extension in your NEON configuration file:

```yaml
extensions:
   barajaPdoSession: Baraja\Session\SessionExtension
```

The session storage will be configured automatically.

#### Manual Configuration

If you need to specify database credentials explicitly:

```yaml
extensions:
   barajaPdoSession: Baraja\Session\SessionExtension

barajaPdoSession:
   host: 127.0.0.1
   dbName: my_application
   username: root
   password: ****
   table: core__session_storage  # optional, this is the default
```

#### Automatic Doctrine DBAL Integration

When using the `baraja-core/doctrine` package, the extension automatically inherits database credentials from your existing DBAL connection. No additional configuration is required!

## 🔧 Advanced Configuration

### Custom Table Name

You can customize the database table name in two ways:

**1. Via Constructor Parameter:**

```php
$handler = new \Baraja\Session\SessionStorage(
   'localhost',
   'mydb',
   'user',
   'password',
   'my_custom_sessions_table'  // Custom table name
);
```

**2. Via Setter Method:**

```php
$handler = new \Baraja\Session\SessionStorage(/* ... */);
$handler->setTable('my_custom_sessions_table');
```

**3. Via NEON Configuration (Nette):**

```yaml
barajaPdoSession:
   table: my_custom_sessions_table
```

> **Note**: The default table name is `core__session_storage`. While the table name can be changed at runtime, it is not recommended.

### Garbage Collection

The garbage collector runs automatically with a 0.1% probability on each request. It:

- Removes sessions that haven't been updated in 14 days
- Processes a maximum of 500 records per run to prevent long-running queries
- Is skipped entirely in CLI mode

## 🔒 Security Considerations

### Session Data Encoding

When session data contains characters incompatible with MySQL's UTF-8 encoding, the package automatically:

1. Detects the encoding failure via PDO exception
2. Re-encodes the data using Base64 with a `_BASE:` prefix
3. Transparently decodes on read

This ensures binary data and special characters are safely stored without data loss.

### CLI Mode Protection

Session operations are automatically skipped in CLI mode (detected by absence of `$_SERVER['REMOTE_ADDR']`). This prevents:

- Errors when running console commands
- Unnecessary database connections in CLI scripts
- Accidental session manipulation from cron jobs

### Error Handling

- Database query failures throw `\RuntimeException` with helpful error messages
- Session corruption errors are logged to Tracy (if available) and display a user-friendly error message
- Failed session writes gracefully degrade without crashing the application

## 🔍 How It Works

### Session Lifecycle

1. **Initialization**: When a request arrives, `SessionStorage` creates a PDO connection
2. **Reading**: The `read()` method fetches session data by ID, creating a new record if none exists
3. **Processing**: Your application uses `$_SESSION` as normal
4. **Writing**: At request end, `write()` updates the session record with new data and timestamp
5. **Cleanup**: GC randomly triggers to remove old sessions (0.1% probability)

### Data Flow

```
┌─────────────┐     ┌──────────────────┐     ┌──────────────┐
│   Request   │────▶│  SessionStorage  │────▶│   Database   │
│             │     │                  │     │              │
│  read SID   │     │  SELECT by ID    │     │  Return row  │
└─────────────┘     └──────────────────┘     └──────────────┘
      │                     │                       │
      │                     ▼                       │
      │             ┌──────────────────┐           │
      │             │ Decode haystack  │           │
      │             │ (Base64 if needed)│          │
      │             └──────────────────┘           │
      │                     │                       │
      ▼                     ▼                       ▼
┌─────────────┐     ┌──────────────────┐     ┌──────────────┐
│  Response   │◀────│  SessionStorage  │◀────│   Database   │
│             │     │                  │     │              │
│ write data  │     │  UPDATE record   │     │  Store row   │
└─────────────┘     └──────────────────┘     └──────────────┘
```

### Retry Mechanism

The `loadById()` method includes a retry mechanism (up to 5 attempts) for handling race conditions when creating new session records. This ensures reliability under concurrent access.

## 📚 API Reference

### SessionStorage

```php
class SessionStorage implements \SessionHandlerInterface
{
    public function __construct(
        string $host,
        string $dbName,
        string $username,
        ?string $password = null,
        ?string $table = null,
    );

    public function setTable(string $table): void;
    public function open($savePath, $sessionName): bool;
    public function close(): bool;
    public function read($id): string;
    public function write($id, $data): bool;
    public function destroy($id): bool;
    public function gc($maxlifetime): int|false;
}
```

### SessionExtension (Nette)

Configuration schema:

| Option     | Type     | Required | Default                | Description           |
|------------|----------|----------|------------------------|-----------------------|
| `host`     | `string` | No*      | -                      | MySQL host            |
| `dbName`   | `string` | No*      | -                      | Database name         |
| `username` | `string` | No*      | -                      | Database username     |
| `password` | `string` | No*      | -                      | Database password     |
| `table`    | `string` | No       | `core__session_storage`| Table name            |

\* Required unless Doctrine DBAL is available (credentials are then inherited automatically).

## 🐛 Troubleshooting

### "Session was corrupted" Error

This error appears when session data cannot be written due to encoding issues. The package attempts to re-encode using Base64, but if this also fails:

1. Check your MySQL character set configuration
2. Ensure the `haystack` column uses `utf8_unicode_ci` or `utf8mb4_unicode_ci`
3. Clear corrupted sessions: `DELETE FROM core__session_storage WHERE id = 'problematic_id'`

### "mb_substr" Function Not Available

The error `Function "mb_substr" is not available` indicates the `mbstring` extension is not installed. Install it:

```shell
# Debian/Ubuntu
sudo apt-get install php-mbstring

# CentOS/RHEL
sudo yum install php-mbstring
```

### Session Not Persisting

1. Verify the handler is registered before `session_start()`
2. Check database credentials and connectivity
3. Ensure the session table exists with correct schema
4. Verify you're not in CLI mode (sessions are disabled in CLI)

## 👤 Author

**Jan Barášek**

- Website: [https://baraja.cz](https://baraja.cz)
- GitHub: [https://github.com/baraja-core](https://github.com/baraja-core)

## 📄 License

`baraja-core/session` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/session/blob/master/LICENSE) file for more details.
