<?php

namespace App\DataFixtures;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture implements OrderedFixtureInterface
{
    private const API_TOKEN = '12f3600378cbb476613d4cec52b56730bc0bcf77d5925538eeffd8dde2d3c4d71e8bb741f0b8cd6f090c328a7535340bf0e7121786566f1f60501d29';

    private const ADMIN_API_TOKEN = '9a1f0c5e7b3d4826a0e1c7f35d8b2a64e91c0d7f3b5a8e2146c9d0f7a3b5e8c1d2f4a6b8c0e2d4f6a8b0c2e4d6f8a0b2c4e6d8f0a2b4c6e8d0f2a4b6c8e0d2f4';

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);
        $manager->persist($user);
        $manager->persist($this->createApiToken($user, self::API_TOKEN));

        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setPassword('password');
        $admin->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin);
        $manager->persist($this->createApiToken($admin, self::ADMIN_API_TOKEN));

        $manager->flush();
    }

    private function createApiToken(User $user, string $token): ApiToken
    {
        $apiToken = new ApiToken();
        $apiToken->setToken($token);
        $apiToken->setUser($user);

        return $apiToken;
    }

    public function getOrder(): int
    {
        return 1;
    }
}
