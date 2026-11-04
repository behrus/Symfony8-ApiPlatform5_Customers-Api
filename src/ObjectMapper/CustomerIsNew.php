<?php

namespace App\ObjectMapper;

use App\Entity\Customer;
use Symfony\Component\ObjectMapper\ConditionCallableInterface;

/**
 * Maps a property only onto a customer that has no id yet, which means a create (POST).
 *
 * On PUT and PATCH API Platform maps onto the stored entity. Replacing its addresses there would delete
 * the existing ones (orphanRemoval), so those requests never touch them.
 *
 * @implements ConditionCallableInterface<object, Customer>
 */
final class CustomerIsNew implements ConditionCallableInterface
{
    public function __invoke(mixed $value, object $source, ?object $target): bool
    {
        return $target instanceof Customer && null === $target->getId();
    }
}
