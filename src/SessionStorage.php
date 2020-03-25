<?php

declare(strict_types=1);

namespace Baraja\Session;


use Tracy\Debugger;

class SessionStorage implements \SessionHandlerInterface
{

	/** @var \PDO */
	private $pdo;

	/** @var string */
	private $table;

	/** @var string[] */
	private $checkedIds = [];

	/** @var bool */
	private $cli;


	/**
	 * @param string $host
	 * @param string $dbName
	 * @param string $username
	 * @param string|null $password
	 * @param string|null $table
	 */
	public function __construct(string $host, string $dbName, string $username, ?string $password = null, ?string $table = null)
	{
		$this->cli = isset($_SERVER['REMOTE_ADDR']) === false;
		$this->table = $table ?: 'core__session_storage';
		$this->pdo = new \PDO('mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8',
			$username, $password, [
				\PDO::ATTR_EMULATE_PREPARES => false,
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			]
		);

		if (mt_rand() / mt_getrandmax() < 0.001) {
			$this->gc(1209600);
		}
	}


	/**
	 * @param string $table
	 */
	public function setTable(string $table): void
	{
		$this->table = $table;
	}


	public function open($savePath, $sessionName): bool
	{
		return true;
	}


	public function close(): bool
	{
		return true;
	}


	public function read($id): string
	{
		if ($this->cli) {
			return '';
		}

		return $this->loadById($id);
	}


	public function write($id, $data): bool
	{
		if ($this->cli) {
			return false;
		}

		$this->updateById($id, $data);

		return true;
	}


	public function destroy($id): bool
	{
		if ($this->cli) {
			return false;
		}

		$this->pdo->exec('DELETE FROM `' . $this->table . '` WHERE `id` = \'' . $id . '\'');
		unset($this->checkedIds[$id]);

		return true;
	}


	public function gc($maxlifetime): bool
	{
		if ($this->cli) {
			return false;
		}

		$this->pdo->prepare('DELETE FROM `' . $this->table . '` WHERE `last_update` < :dateTime LIMIT 500')
			->execute([':dateTime' => date('Y-m-d H:i:s', (int) strtotime('now - 14 days'))]);

		return true;
	}


	/**
	 * @param string $id
	 * @param int $attempts
	 * @return string
	 */
	private function loadById(string $id, int $attempts = 1): string
	{
		if ($attempts >= 5) {
			return '';
		}

		$this->checkedIds[$id] = true;
		$data = $this->pdo->query('SELECT * FROM `' . $this->table . '` WHERE `id` = \'' . $id . '\' LIMIT 1')->fetch();

		if ($data === false) {
			try {
				$this->pdo->exec(
					'INSERT INTO `' . $this->table . '` (`id`, `haystack`, `last_update`) '
					. 'VALUES (\'' . $id . '\', \'\', \'' . date('Y-m-d H:i:s') . '\');');
			} catch (\PDOException $e) {
				$this->destroy($id);
				$this->loadById($id, $attempts + 1);
			}

			return '';
		}

		$haystack = $data['haystack'];

		if (strncmp($haystack, '_BASE:', 6) === 0) {
			if (function_exists('mb_substr')) {
				$subHaystack = mb_substr($haystack, 6, null, 'UTF-8'); // MB is much faster
			} else {
				$subHaystack = iconv_substr($haystack, 6, strlen(utf8_decode($haystack)), 'UTF-8');
			}

			$haystack = base64_decode($subHaystack);
		}

		return $haystack;
	}


	/**
	 * @param string $id
	 * @param string $data
	 */
	private function updateById(string $id, string $data): void
	{
		if (isset($this->checkedIds[$id]) === false) {
			$this->loadById($id);
		}

		try {
			$this->saveHaystack($id, $data);
		} catch (\PDOException $e) {
			if (preg_match('/Incorrect string value: .+? for column .haystack. at row 1$/', $e->getMessage())) {
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


	/**
	 * @param string $id
	 * @param string $data
	 */
	private function saveHaystack(string $id, string $data): void
	{
		$this->pdo->prepare(
			'UPDATE `' . $this->table . '` '
			. 'SET `haystack` = :haystack, '
			. '`last_update` = \'' . date('Y-m-d H:i:s') . '\''
			. 'WHERE `id` = \'' . $id . '\' '
			. 'LIMIT 1;'
		)->execute([':haystack' => $data]);
	}
}