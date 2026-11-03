<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Customer;
use Doctrine\ORM\QueryBuilder;

/**
 * Hides soft-deleted customers: they are not listed and answer 404 when requested by id.
 */
final class ExcludeDeletedCustomersExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->exclude($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->exclude($queryBuilder, $resourceClass);
    }

    private function exclude(QueryBuilder $queryBuilder, string $resourceClass): void
    {
        if (Customer::class !== $resourceClass) {
            return;
        }

        $queryBuilder->andWhere(sprintf('%s.deletedAt IS NULL', $queryBuilder->getRootAliases()[0]));
    }
}
