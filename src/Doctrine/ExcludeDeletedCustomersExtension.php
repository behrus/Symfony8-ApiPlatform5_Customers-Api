<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Address;
use App\Entity\Customer;
use Doctrine\ORM\QueryBuilder;

/**
 * Hides soft-deleted customers and their addresses: they are not listed and answer 404 when requested by id.
 */
final class ExcludeDeletedCustomersExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->exclude($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->exclude($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function exclude(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (Customer::class === $resourceClass) {
            $queryBuilder->andWhere(sprintf('%s.deletedAt IS NULL', $rootAlias));
        } elseif (Address::class === $resourceClass) {
            $customerAlias = $queryNameGenerator->generateJoinAlias('customer');
            $queryBuilder
                ->innerJoin(sprintf('%s.customer', $rootAlias), $customerAlias)
                ->andWhere(sprintf('%s.deletedAt IS NULL', $customerAlias));
        }
    }
}
