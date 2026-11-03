<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;

/**
 * "Deletes" a customer by marking it as deleted. The row stays for traceability and analysis.
 *
 * @implements ProcessorInterface<Customer, void>
 */
final readonly class SoftDeleteProcessor implements ProcessorInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $data->markAsDeleted();
        $this->entityManager->flush();
    }
}
