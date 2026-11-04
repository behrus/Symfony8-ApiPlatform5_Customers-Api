<?php

namespace App\DataFixtures;

use App\Entity\Address;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\Customer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CustomerFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $berlin = $this->getReference('city', City::class);

        $hamburg = new City();
        $hamburg->setName('Hamburg');
        $hamburg->setCountry($this->getReference('country', Country::class));
        $manager->persist($hamburg);

        // Not in alphabetical order, so that the order filter has something to prove.
        $this->createCustomer($manager, 'Clara Meier', 'clara@example.com', [$hamburg]);
        $this->createCustomer($manager, 'Anna Schmidt', 'anna@example.com', [$berlin]);
        $this->createCustomer($manager, 'Dora Müller', 'dora@example.com', []);
        $this->createCustomer($manager, 'Bernd Schmitt', 'bernd@example.org', [$berlin, $hamburg]);

        $manager->flush();
    }

    /**
     * @param list<City> $cities
     */
    private function createCustomer(ObjectManager $manager, string $name, string $email, array $cities): void
    {
        $customer = new Customer();
        $customer->setName($name);
        $customer->setEmail($email);
        $customer->setPhone('0301234567');

        foreach ($cities as $city) {
            $address = new Address();
            $address->setStreet('Teststraße');
            $address->setStreetnumber('1');
            $address->setZipcode('12345');
            $address->setCity($city);
            $customer->addAddress($address);
        }

        $manager->persist($customer);
    }

    public function getOrder(): int
    {
        return 4;
    }
}
