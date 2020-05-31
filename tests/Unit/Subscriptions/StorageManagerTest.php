<?php

namespace Tests\Unit\Subscriptions;

use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use Nuwave\Lighthouse\Subscriptions\StorageManager;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class StorageManagerTest extends SubscriptionTestCase
{
    /**
     * @var \Nuwave\Lighthouse\Subscriptions\StorageManager
     */
    protected $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = app(StorageManager::class);
    }

    /**
     * Construct a dummy subscriber for testing.
     */
    protected function subscriber(string $queryString): Subscriber
    {
        /** @var \Nuwave\Lighthouse\Subscriptions\Subscriber $subscriber */
        $subscriber = $this->createMock(Subscriber::class);

        $subscriber->channel = Subscriber::uniqueChannelName();
        $subscriber->query = Parser::parse($queryString);

        return $subscriber;
    }

    public function testStoreAndRetrieveByChannel(): void
    {
        $subscriber = $this->subscriber(/** @lang GraphQL */ '{ me }');
        $this->storage->storeSubscriber($subscriber, 'foo');

        $this->assertSubscriberIsSame(
            $subscriber,
            $this->storage->subscriberByChannel($subscriber->channel)
        );

        $this->assertSubscriberIsSame(
            $subscriber,
            $this->storage->subscriberByRequest(['channel_name' => $subscriber->channel], [])
        );
    }

    public function testStoreAndRetrieveByTopics(): void
    {
        $fooTopic = 'foo';
        $fooSubscriber1 = $this->subscriber(/** @lang GraphQL */ '{ me }');
        $fooSubscriber2 = $this->subscriber(/** @lang GraphQL */ '{ viewer }');
        $this->storage->storeSubscriber($fooSubscriber1, $fooTopic);
        $this->storage->storeSubscriber($fooSubscriber2, $fooTopic);

        $barTopic = 'bar';
        $barSubscriber = $this->subscriber(/** @lang GraphQL */ '{ bar }');
        $this->storage->storeSubscriber($barSubscriber, $barTopic);

        $fooSubscribers = $this->storage->subscribersByTopic($fooTopic);
        $this->assertCount(2, $fooSubscribers);

        $barSubscribers = $this->storage->subscribersByTopic($barTopic);
        $this->assertCount(1, $barSubscribers);
    }

    public function testDeleteSubscribersInCache(): void
    {
        $subscriber1 = $this->subscriber(/** @lang GraphQL */ '{ me }');
        $subscriber2 = $this->subscriber(/** @lang GraphQL */ '{ viewer }');

        $topic = 'foo';
        $this->storage->storeSubscriber($subscriber1, $topic);
        $this->assertCount(1, $this->storage->subscribersByTopic($topic));

        $this->storage->storeSubscriber($subscriber2, $topic);
        $this->assertCount(2, $this->storage->subscribersByTopic($topic));

        $this->storage->deleteSubscriber($subscriber1->channel);
        $this->assertNull($this->storage->subscriberByChannel($subscriber1->channel));
        $this->assertCount(1, $this->storage->subscribersByTopic($topic));

        $this->storage->deleteSubscriber($subscriber2->channel);
        $this->assertNull($this->storage->subscriberByChannel($subscriber2->channel));
        $this->assertCount(0, $this->storage->subscribersByTopic($topic));
    }

    protected function assertSubscriberIsSame(Subscriber $expected, ?Subscriber $actual): void
    {
        $this->assertNotNull($actual);
        /** @var \Nuwave\Lighthouse\Subscriptions\Subscriber $actual */

        $this->assertSame(
            AST::toArray($expected->query),
            AST::toArray($actual->query)
        );
    }
}
