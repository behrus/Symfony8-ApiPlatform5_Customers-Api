<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\AddressRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AddressRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    description: 'Customer address.',
    operations: [
        new Get(
            normalizationContext: [
                'groups' => ['address:read', 'address:item:get'],
            ],
        ),
        new GetCollection(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: [
        'groups' => ['address:read', 'customer:read'],
    ],
    denormalizationContext: [
        'groups' => ['address:write', 'customer:write'],
    ],
    security: 'is_granted("ROLE_USER")',
)]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    private string $street;

    #[ORM\Column(length: 5)]
    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    private string $streetnumber;

    #[ORM\Column(length: 5)]
    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    private string $zipcode;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['address:read', 'city:write', 'city:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['address:read', 'city:write', 'city:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'address')]
    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Assert\Valid]
    private Customer $customer;

    #[ORM\ManyToOne(inversedBy: 'address')]
    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Assert\Valid]
    private City $city;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function setStreet(string $street): void
    {
        $this->street = $street;
    }

    public function getStreetnumber(): string
    {
        return $this->streetnumber;
    }

    public function setStreetnumber(string $streetnumber): void
    {
        $this->streetnumber = $streetnumber;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    public function setZipcode(string $zipcode): void
    {
        $this->zipcode = $zipcode;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updatedTimestamps(): void
    {
        $dateTimeNow = new \DateTimeImmutable();
        $this->setUpdatedAt();
        if ($this->getCreatedAt() === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function setCity(City $city): void
    {
        $this->city = $city;
    }
}
