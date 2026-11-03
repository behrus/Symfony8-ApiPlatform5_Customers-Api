<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Deletes a resource with API Platform's own processor. A resource that still has children is
 * refused by the database (foreign key without ON DELETE CASCADE); this turns that error into a 409.
 *
 * @implements ProcessorInterface<object, void>
 */
final readonly class RestrictedDeleteProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<object, void> $removeProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        try {
            $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        } catch (ForeignKeyConstraintViolationException $exception) {
            throw new ConflictHttpException('This resource still has related resources and cannot be deleted.', $exception);
        }
    }
}
