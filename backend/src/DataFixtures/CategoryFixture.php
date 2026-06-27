<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\Category as CategoryEntity;

/**
 * Creates the content categories used across the dev article fixtures.
 * Run as part of: php -d memory_limit=1G bin/console doctrine:fixtures:load --group=dev
 */
class CategoryFixture extends Fixture implements FixtureGroupInterface
{
    /** Maps reference key → [name, key]. Admin user (ID 1) is used as creator. */
    private const CATEGORIES = [
        'distributed-systems' => ['name' => 'Distributed Systems',  'key' => 'distributed-systems'],
        'design-patterns'     => ['name' => 'Design Patterns',       'key' => 'design-patterns'],
        'ddd'                 => ['name' => 'Domain-Driven Design',  'key' => 'ddd'],
        'architecture-styles' => ['name' => 'Architecture Styles',   'key' => 'architecture-styles'],
        'messaging-patterns'  => ['name' => 'Messaging Patterns',    'key' => 'messaging-patterns'],
    ];

    public function __construct(private readonly CategoryManagerInterface $categoryManager) {}

    public static function getGroups(): array
    {
        return ['dev'];
    }

    /** @throws Exception */
    public function load(ObjectManager $manager): void
    {
        foreach (self::CATEGORIES as $refKey => $data) {
            $existing = $manager->getRepository(CategoryEntity::class)->findOneBy(['key' => $data['key']]);
            if ($existing !== null) {
                $this->addReference('category-' . $refKey, $existing);
                continue;
            }
            // save() persists + flushes internally; userId 1 = Adam Ministrator.
            $category = $this->categoryManager->save($data, 1, 'en');
            $this->addReference('category-' . $refKey, $category);
        }
    }
}
