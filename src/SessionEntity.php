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

	/** @ORM\Column(type="text") */
	private string $haystack;

	/** @ORM\Column(type="datetime", nullable=true) */
	private ?\DateTime $lastUpdate;
}
