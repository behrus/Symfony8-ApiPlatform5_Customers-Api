<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\City;
use App\Repository\CountryRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * This API handles only Germany: a city always belongs to it, so the country is never part of the request.
 *
 * @implements ProcessorInterface<City, City>
 */
final readonly class GermanCityProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<City, City> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private CountryRepository $countries,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): City
    {
        $germany = $this->countries->findOneBy(['name' => 'Germany'])
            ?? throw new \LogicException('The country "Germany" is missing. Run the migrations.');
        $data->setCountry($germany);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
