PHP native PDO Session storage
==============================

Simple performance package for storage your sessions to database by native `\PDO`.

Install
-------

Install by Composer:

```shell
composer require baraja-core/session
```

And create database table `core__session_storage` (table name can be configured) or use Doctrine for automatic generating.

MySql table schema:

```sql
SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `core__session_storage`;
CREATE TABLE `core__session_storage` (
  `id` char(36) COLLATE utf8_unicode_ci NOT NULL COMMENT '(DC2Type:uuid)',
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
services:
	sessionStorage: Baraja\Session\SessionStorage(
		%database.primary.host%,
		%database.primary.dbname%,
		%database.primary.user%,
		%database.primary.password%
	)
	session:
		setup:
			- setHandler(@sessionStorage)
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

- Constructor 5. parameter
- Setter `setTable()`

Default table name is `core__session_storage`.

Table name can be rewritten in runtime, but it's not recommended.
