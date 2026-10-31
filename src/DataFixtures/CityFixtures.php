<?php

namespace App\DataFixtures;

use App\Entity\City;
use App\Entity\Country;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Constraint\Count;
use Psr\Log\LoggerInterface;

class CityFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $country = $this->getReference('country', Country::class);

        $city = new City();
        $city->setName("Berlin");
        $city->setCountry($country);
        $manager->persist($city);
        $this->addReference('city', $city);
        $manager->flush();
    }
    public function getOrder(): int
    {
        return 3;
    }
}
