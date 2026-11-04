<?php

namespace App\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Entity\Address;
use App\Entity\City;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\IsNotNull;
use Symfony\Component\ObjectMapper\Condition\SourceClass;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The API representation of an address. The Doctrine entity {@see Address} is only used for storage.
 */
#[ApiResource(
    shortName: 'Address',
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
        'groups' => ['address:read'],
    ],
    denormalizationContext: [
        'groups' => ['address:write', 'customer:write'],
    ],
    security: 'is_granted("ROLE_USER")',
    stateOptions: new Options(entityClass: Address::class),
)]
#[Map(source: Address::class)]
#[Map(target: Address::class)]
final class AddressResource
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    public string $street = '';

    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    public string $streetnumber = '';

    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    public string $zipcode = '';

    #[Groups(['address:read'])]
    #[Map(if: new SourceClass(Address::class))]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['address:read'])]
    #[Map(if: new SourceClass(Address::class))]
    public ?\DateTimeImmutable $updatedAt = null;

    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Map(if: new IsNotNull())]
    public ?CustomerResource $customer = null;

    #[Groups(['address:read', 'address:write', 'customer:read', 'customer:write'])]
    #[Map(if: new IsNotNull())]
    public ?City $city = null;
}
