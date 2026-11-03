<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Customer;
use App\Message\CustomerCreated;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Persists the customer with API Platform's own processor, then announces it on the message bus.
 *
 * @implements ProcessorInterface<Customer, Customer>
 */
final readonly class CustomerCreatedProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Customer, Customer> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private MessageBusInterface $bus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Customer
    {
        $customer = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->bus->dispatch(new CustomerCreated($customer->getId()));

        return $customer;
    }
}
