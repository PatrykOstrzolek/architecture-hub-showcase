<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\ContactBundle\Entity\Contact;

/**
 * Creates a second author contact (Jane Kowalski) for use in article fixtures.
 * Adam Ministrator (contact ID 1) is created by sulu:build dev and used directly.
 * Run as part of: php -d memory_limit=1G bin/console doctrine:fixtures:load --group=dev.
 */
class ContactFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function load(ObjectManager $manager): void
    {
        $existing = $manager->getRepository(Contact::class)->findOneBy([
            'firstName' => 'Jane',
            'lastName' => 'Kowalski',
        ]);
        if (null !== $existing) {
            $this->addReference('contact-jane', $existing);

            return;
        }

        $contact = new Contact();
        $contact->setFirstName('Jane');
        $contact->setLastName('Kowalski');
        $contact->setFormOfAddress(0);

        $manager->persist($contact);
        $manager->flush();

        $this->addReference('contact-jane', $contact);
    }
}
