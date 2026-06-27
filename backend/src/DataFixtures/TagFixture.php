<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Sulu\Bundle\TagBundle\Tag\Exception\TagNotFoundException;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;

/**
 * Creates all tags used across the dev article fixtures.
 * Run as part of: php -d memory_limit=1G bin/console doctrine:fixtures:load --group=dev
 */
class TagFixture extends Fixture implements FixtureGroupInterface
{
    /** Maps reference key → tag name. */
    private const TAGS = [
        'cap-theorem'          => 'cap-theorem',
        'consistency'          => 'consistency',
        'availability'         => 'availability',
        'partition-tolerance'  => 'partition-tolerance',
        'event-driven'         => 'event-driven',
        'event-sourcing'       => 'event-sourcing',
        'microservices'        => 'microservices',
        'saga'                 => 'saga',
        'outbox'               => 'outbox',
        'cqrs'                 => 'cqrs',
        'monolith'             => 'monolith',
        'distributed-systems'  => 'distributed-systems',
        'ddd'                  => 'ddd',
        'aggregates'           => 'aggregates',
        'bounded-context'      => 'bounded-context',
        'value-objects'        => 'value-objects',
        'design-patterns'      => 'design-patterns',
        'hexagonal'            => 'hexagonal',
        'clean-architecture'   => 'clean-architecture',
        'circuit-breaker'      => 'circuit-breaker',
        'repository-pattern'   => 'repository-pattern',
        'factory-pattern'      => 'factory-pattern',
        'strategy-pattern'     => 'strategy-pattern',
        'observer-pattern'     => 'observer-pattern',
        'dependency-injection' => 'dependency-injection',
        'api-gateway'          => 'api-gateway',
    ];

    public function __construct(private readonly TagManagerInterface $tagManager) {}

    public static function getGroups(): array
    {
        return ['dev'];
    }

    /** @throws TagNotFoundException */
    public function load(ObjectManager $manager): void
    {
        foreach (self::TAGS as $refKey => $name) {
            $tag = $this->tagManager->findByName($name) ?? $this->tagManager->save(['name' => $name]);
            $this->addReference('tag-' . $refKey, $tag);
        }

        $manager->flush();
    }
}
