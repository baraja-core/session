<?php

declare(strict_types=1);

namespace Baraja\Session;


use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="core__session_storage")
 */
class SessionEntity
{
	/**
	 * @ORM\Id
	 * @ORM\Column(type="string", unique=true, length=26)
	 */
	private string $id;

	/** @ORM\Column(type="text") */
	private string $haystack;

	/** @ORM\Column(type="datetime", nullable=true) */
	private ?\DateTime $lastUpdate = null;


	public function __construct(string $id)
	{
		$this->id = $id;
	}


	public function getId(): string
	{
		return $this->id;
	}


	public function getHaystack(): string
	{
		return $this->haystack;
	}


	public function getLastUpdate(): ?\DateTime
	{
		return $this->lastUpdate;
	}
}
