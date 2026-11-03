<?php

namespace App\Tests\Support;

/**
 * Looks up the id of a fixture by its name, so tests do not depend on auto increment values.
 * The using class needs an $entityManager property.
 */
trait FindsFixtureIds
{
    /**
     * @param class-string $class
     */
    private function idOf(string $class, string $name): int
    {
        $entity = $this->entityManager->getRepository($class)->findOneBy(['name' => $name]);
        \assert(null !== $entity && method_exists($entity, 'getId'));

        return $entity->getId();
    }
}
