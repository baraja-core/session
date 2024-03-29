<?php

declare(strict_types=1);

namespace Baraja\Session;


use Tracy\Debugger;

class SessionStorage implements \SessionHandlerInterface
{
	private \PDO $pdo;

	private string $table;

	/** @var bool[] */
	private array $checkedIds = [];

	private bool $cli;


	public function __construct(
		string $host,
		string $dbName,
		string $username,
		?string $password = null,
		?string $table = null,
	) {
		$this->cli = isset($_SERVER['REMOTE_ADDR']) === false;
		$this->table = $table ?? 'core__session_storage';
		$this->pdo = new \PDO(
			'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8',
			$username,
			$password,
			[
				\PDO::ATTR_EMULATE_PREPARES => false,
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			],
		);

		if (mt_rand() / mt_getrandmax() < 0.001) {
			$this->gc(1_209_600);
		}
	}


	public function setTable(string $table): void
	{
		$this->table = $table;
	}


	/**
	 * @param string $savePath
	 * @param string $sessionName
	 */
	public function open($savePath, $sessionName): bool
	{
		return true;
	}


	public function close(): bool
	{
		return true;
	}


	/**
	 * @param string $id
	 */
	public function read($id): string
	{
		if ($this->cli) {
			return '';
		}

		return $this->loadById($id);
	}


	/**
	 * @param string $id
	 * @param string $data
	 */
	public function write($id, $data): bool
	{
		if ($this->cli) {
			return false;
		}

		$this->updateById($id, $data);

		return true;
	}


	/**
	 * @param string $id
	 */
	public function destroy($id): bool
	{
		if ($this->cli) {
			return false;
		}

		$this->pdo->exec('DELETE FROM `' . $this->table . '` WHERE `id` = \'' . $id . '\'');
		unset($this->checkedIds[$id]);

		return true;
	}


	/**
	 * @param int $maxlifetime
	 */
	public function gc($maxlifetime): int|false
	{
		if ($this->cli) {
			return 0;
		}

		$this->pdo->prepare('DELETE FROM `' . $this->table . '` WHERE `last_update` < :dateTime LIMIT 500')
			->execute([':dateTime' => date('Y-m-d H:i:s', strtotime('now - 14 days'))]);

		return 1;
	}


	private function loadById(string $id, int $attempts = 1): string
	{
		if ($attempts >= 5) {
			return '';
		}

		$this->checkedIds[$id] = true;
		$query = 'SELECT * FROM `' . $this->table . '` WHERE `id` = \'' . $id . '\' LIMIT 1';
		$processedQuery = $this->pdo->query($query);
		if ($processedQuery === false) {
			throw new \RuntimeException('Can not process query. Please try run this SQL manually.' . "\n\n" . 'SQL given: ' . $query);
		}
		$data = $processedQuery->fetch();
		if ($data === false) {
			try {
				$this->pdo->exec(
					'INSERT INTO `' . $this->table . '` (`id`, `haystack`, `last_update`) '
					. 'VALUES (\'' . $id . '\', \'\', \'' . date('Y-m-d H:i:s') . '\');',
				);
			} catch (\PDOException) {
				$this->destroy($id);
				$this->loadById($id, $attempts + 1);
			}

			return '';
		}
		$haystack = $data['haystack'];
		if (str_starts_with($haystack, '_BASE:')) {
			if (function_exists('mb_substr')) {
				$haystack = base64_decode(mb_substr($haystack, 6, null, 'UTF-8'), true);
			} else {
				throw new \RuntimeException('Function "mb_substr" is not available. Please install "mb" extension.');
			}
		}

		return $haystack;
	}


	private function updateById(string $id, string $data): void
	{
		if (isset($this->checkedIds[$id]) === false) {
			$this->loadById($id);
		}

		try {
			$this->saveHaystack($id, $data);
		} catch (\PDOException $e) {
			if (preg_match('/Incorrect string value: .+? for column .haystack. at row 1$/', $e->getMessage()) === 1) {
				$this->saveHaystack($id, '_BASE:' . base64_encode($data));
			} else {
				echo '<h1>Internal server error.</h1>' . "\n";
				echo '<p>Session was corrupted. Please try reload page.</p>' . "\n";
				echo '<!-- Baraja session storage -->';

				if (class_exists(Debugger::class) === true) {
					Debugger::log($e);
				}
			}
		}
	}


	private function saveHaystack(string $id, string $data): void
	{
		$this->pdo->prepare(
			'UPDATE `' . $this->table . '` '
			. 'SET `haystack` = :haystack, '
			. '`last_update` = \'' . date('Y-m-d H:i:s') . '\''
			. 'WHERE `id` = \'' . $id . '\' '
			. 'LIMIT 1;',
		)->execute([':haystack' => $data]);
	}
}
