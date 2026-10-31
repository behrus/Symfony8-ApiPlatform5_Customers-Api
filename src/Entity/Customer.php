<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['customer:read']],
    denormalizationContext: ['groups' => ['customer:write', 'address:write']],
    security: 'is_granted("ROLE_USER")',
    ),
    ApiFilter(
        SearchFilter::class,
        properties: [
            'name' => SearchFilter::STRATEGY_PARTIAL,
            'email' => SearchFilter::STRATEGY_PARTIAL,
            'address.city.name' => SearchFilter::STRATEGY_EXACT
        ]
    ),
    ApiFilter(
        OrderFilter::class,
        properties: ['name']
    )]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Groups(['customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(length: 20)]
    #[Groups(['customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    private string $phone;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['address:read', 'city:write', 'city:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['address:read', 'city:write', 'city:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, Address> */
    #[ORM\OneToMany(
        targetEntity: Address::class,
        mappedBy: 'customer',
        cascade: ['persist'],
        orphanRemoval: true
    )
    ]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['customer:read', 'customer:write', 'address:write'])]
    #[Assert\Valid]
    private Collection $address;

    public function __construct()
    {
        $this->address = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
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
            $address->setCustomer($this);
        }

        return $this;
    }

    public function removeAddress(Address $address): static
    {
        $this->address->removeElement($address);

        return $this;
    }
}
