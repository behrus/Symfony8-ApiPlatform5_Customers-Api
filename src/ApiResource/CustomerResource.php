<?php

namespace App\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Address;
use App\Entity\Customer;
use App\ObjectMapper\CustomerIsNew;
use App\State\CustomerCreatedProcessor;
use App\State\SoftDeleteProcessor;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\SourceClass;
use Symfony\Component\ObjectMapper\Transform\MapCollection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The API representation of a customer. The Doctrine entity {@see Customer} is only used for storage.
 */
#[ApiResource(
    shortName: 'Customer',
    operations: [
        new Get(),
        new GetCollection(parameters: [
            'name' => new QueryParameter(filter: new PartialSearchFilter()),
            'email' => new QueryParameter(filter: new PartialSearchFilter()),
            'address.city.name' => new QueryParameter(filter: new ExactFilter(), property: 'address.city.name'),
            'order[:property]' => new QueryParameter(filter: new SortFilter(), properties: ['name']),
        ]),
        new Post(
            denormalizationContext: ['groups' => ['customer:write', 'customer:create']],
            processor: CustomerCreatedProcessor::class,
        ),
        new Put(),
        new Patch(),
        new Delete(security: 'is_granted("CUSTOMER_DELETE", object)', processor: SoftDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['customer:read']],
    denormalizationContext: ['groups' => ['customer:write']],
    security: 'is_granted("ROLE_USER")',
    stateOptions: new Options(entityClass: Customer::class),
)]
#[Map(source: Customer::class)]
#[Map(target: Customer::class)]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email', entityClass: Customer::class, identifierFieldNames: ['id'])]
final class CustomerResource
{
    #[ApiProperty(identifier: true)]
    public ?int $id = null;

    #[Groups(['customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    public string $name = '';

    #[Groups(['customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Groups(['customer:read', 'customer:write'])]
    #[Assert\NotBlank]
    public string $phone = '';

    #[Groups(['customer:read'])]
    #[Map(if: new SourceClass(Customer::class))]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['customer:read'])]
    #[Map(if: new SourceClass(Customer::class))]
    public ?\DateTimeImmutable $updatedAt = null;

    /** @var list<AddressResource> */
    #[Groups(['customer:read', 'customer:create'])]
    #[Assert\Valid]
    #[Map(if: new CustomerIsNew(), transform: new MapCollection(targetClass: Address::class))]
    #[Map(if: new SourceClass(Customer::class), transform: new MapCollection(targetClass: AddressResource::class))]
    public array $address = [];
}
