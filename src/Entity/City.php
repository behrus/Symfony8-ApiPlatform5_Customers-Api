<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\CityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    description: 'City',
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: [
        'groups' => ['address:read', 'city:read'],
    ],
    denormalizationContext: [
        'groups' => ['city:write'],
    ],
    security: 'is_granted("ROLE_USER")',
)]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['address:read', 'city:write', 'city:read'])]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['address:read', 'city:write', 'city:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['address:read', 'city:write', 'city:read'])]
    private ?\DateTimeImmutable $updatedAt = null;


    #[ORM\ManyToOne(inversedBy: 'city')]
    #[Groups(['address:read', 'city:read', 'city:write'])]
    private Country $country;

    /** @var Collection<int, Address> */
    #[ORM\OneToMany(
        targetEntity: Address::class,
        mappedBy: 'city',
        cascade: ['remove'],
        orphanRemoval: true,
    )
    ]
    private Collection $address;

    public function __construct()
    {
        $this->address = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function setCountry(Country $country): void
    {
        $this->country = $country;
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

    /**
     * @return Collection<int, Address>
     */
    public function getAddress(): Collection
    {
        return $this->address;
    }

    public function addAddress(Address $address): static
    {
        if (!$this->address->contains($address)) {
            $this->address->add($address);
            $address->setCity($this);
        }

        return $this;
    }

    public function removeAddress(Address $address): static
    {
        $this->address->removeElement($address);

        return $this;
    }
}
