<?php

declare(strict_types=1);

namespace Baraja\Session;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="core__session_storage")
 */
class SessionEntity
{
	use UuidIdentifier;

	/**
	 * @var string
	 * @ORM\Column(type="text")
	 */
	private $haystack;

	/**
	 * @var \DateTime|null
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $lastUpdate;
}