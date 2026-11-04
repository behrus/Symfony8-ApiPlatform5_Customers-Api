<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Only the SHA-256 hash of a token is stored; the token itself is shown once when it is created
 * (`app:api-token:create`). The API can list and delete tokens, but never returns or accepts one.
 */
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ApiResource(
    operations: [new GetCollection(), new Get(), new Delete()],
    normalizationContext: ['groups' => ['api_token:read']],
    security: "is_granted('ROLE_ADMIN')",
)]
class ApiToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api_token:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    private string $tokenHash;

    #[ORM\ManyToOne(inversedBy: 'apiTokens')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api_token:read'])]
    private User $user;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): void
    {
        $this->tokenHash = $tokenHash;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }
}
