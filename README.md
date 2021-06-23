PHP native PDO Session storage
==============================

Simple performance package for storage your sessions to database by native `\PDO`.

📦 Installation & Basic Usage
-----------------------------

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/session) and
[GitHub](https://github.com/baraja-core/session).

To install, simply use the command:

```
$ composer require baraja-core/session
```

You can use the package manually by creating an instance of the internal classes, or register a DIC extension to link the services directly to the Nette Framework.

Install database structure
--------------------------

And create database table `core__session_storage` (table name can be configured) or use Doctrine for automatic generating.

MySql table schema:

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

Table can be used with Doctrine or alone.

How to use
----------

In case of Nette framework simply use NEON configuration (defined in `common.neon` file):

```yaml
extensions:
   barajaPdoSession: Baraja\Session\SessionExtension
```

Session storage will be configured automatically.

In case of native PHP simply create new `SessionStorage` instance and create handler:

```php
$handler = new \Baraja\Session\SessionStorage(
   '127.0.0.1', // host
   'my_application', // database name
   'root', // user
   '****' // password
);

session_set_save_handler($handler);
```

> **Warning:** Session handler must be set before session has been started!

Define custom table name
------------------------

In case of custom table name you can rewrite default table name by 2 ways:

- Constructor `$table` argument
- Setter `setTable()`

Default table name is `core__session_storage`.

Table name can be rewritten in runtime, but it's not recommended.

📄 License
-----------

`baraja-core/session` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/session/blob/master/LICENSE) file for more details.
