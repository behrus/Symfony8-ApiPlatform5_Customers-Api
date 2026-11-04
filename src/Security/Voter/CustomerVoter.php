<?php

namespace App\Security\Voter;

use App\ApiResource\CustomerResource;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, CustomerResource>
 */
class CustomerVoter extends Voter
{
    public const DELETE = 'CUSTOMER_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof CustomerResource;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        $vote?->addReason('Only admins may delete customers.');

        return false;
    }
}
