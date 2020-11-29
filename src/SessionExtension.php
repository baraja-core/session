<?php

declare(strict_types=1);

namespace Baraja\Session;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Doctrine\DBAL\Connection;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Http\Session;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class SessionExtension extends CompilerExtension
{
	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		if (\class_exists(OrmAnnotationsExtension::class)) {
			return [OrmAnnotationsExtension::class];
		}

		return [];
	}


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'host' => Expect::string(),
			'dbName' => Expect::string(),
			'username' => Expect::string(),
			'password' => Expect::string(),
			'table' => Expect::string(),
		])->castTo('array');
	}


	public function beforeCompile(): void
	{
		if (\class_exists(OrmAnnotationsExtension::class)) {
			OrmAnnotationsExtension::addAnnotationPath('Baraja\Session', __DIR__ . '/Entity');
		}

		/** @var mixed[] $config */
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();

		$connection = [
			'host' => $config['host'],
			'dbName' => $config['dbName'],
			'username' => $config['username'],
			'password' => $config['password'],
		];

		if ($connection['host'] === null) {
			if (\class_exists(Connection::class)) {
				/** @var ServiceDefinition $dbalConnection */
				$dbalConnection = $builder->getDefinitionByType(Connection::class);
				$dbalConnectionArguments = $dbalConnection->getFactory()->arguments;
				$connection = [
					'host' => $dbalConnectionArguments[0]['host'] ?? null,
					'dbName' => $dbalConnectionArguments[0]['dbname'] ?? null,
					'username' => $dbalConnectionArguments[0]['user'] ?? null,
					'password' => $dbalConnectionArguments[0]['password'] ?? null,
				];
			} else {
				throw new \RuntimeException('Configuration options "host", "dbName", "username" and "password" are required.');
			}
		}

		$builder->addDefinition($this->prefix('sessionStorage'))
			->setFactory(SessionStorage::class)
			->setArguments([
				'host' => $connection['host'],
				'dbName' => $connection['dbName'],
				'username' => $connection['username'],
				'password' => $connection['password'],
				'table' => $config['table'] ?? null,
			]);

		/** @var ServiceDefinition $netteSession */
		$netteSession = $builder->getDefinitionByType(Session::class);
		$netteSession->addSetup('?->setHandler($this->getByType(?))', ['@self', SessionStorage::class]);
	}
}
