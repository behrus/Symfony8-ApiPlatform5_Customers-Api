<?php

namespace App\Tests\Security\Voter;

use App\Entity\Customer;
use App\Entity\User;
use App\Security\Voter\CustomerVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class CustomerVoterTest extends TestCase
{
    public function testAdminMayDelete(): void
    {
        $result = (new CustomerVoter())->vote($this->token(['ROLE_ADMIN']), new Customer(), [CustomerVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testNormalUserIsDeniedWithReason(): void
    {
        $vote = new Vote();

        $result = (new CustomerVoter())->vote($this->token(['ROLE_USER']), new Customer(), [CustomerVoter::DELETE], $vote);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
        $this->assertSame(['Only admins may delete customers.'], $vote->reasons);
    }

    public function testAbstainsForOtherAttribute(): void
    {
        $result = (new CustomerVoter())->vote($this->token(['ROLE_ADMIN']), new Customer(), ['CUSTOMER_VIEW']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForOtherSubject(): void
    {
        $result = (new CustomerVoter())->vote($this->token(['ROLE_ADMIN']), new \stdClass(), [CustomerVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /**
     * @param list<string> $roles
     */
    private function token(array $roles): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new User(), 'main', $roles);
    }
}
