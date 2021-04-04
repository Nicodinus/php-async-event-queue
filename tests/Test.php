<?php


namespace Nicodinus\PhpAsync\EventQueue\Tests;


use Amp\Deferred;
use Amp\Promise;
use Amp\PHPUnit\AsyncTestCase;
use Generator;
use Nicodinus\PhpAsync\EventQueue\Event;
use Nicodinus\PhpAsync\EventQueue\EventDispatcher;
use PHPUnit\Framework\AssertionFailedError;
use function Amp\asyncCall;
use function Amp\delay;
use function Nicodinus\PhpAsync\EventQueue\listenEvent;
use function Nicodinus\PhpAsync\EventQueue\subscribeEvent;

class Test extends AsyncTestCase
{
    /**
     * @return EventDispatcher
     */
    protected function createEventProvider()
    {
        return new EventDispatcher();
    }

    /**
     * @return Generator
     */
    public function testDefaultChannel()
    {
        $eventProvider = $this->createEventProvider();

        $listener = $eventProvider->subscribe();

        $event = new Event();

        //

        asyncCall(function () use ($eventProvider, $event) {

            yield delay(0);

            yield $eventProvider->queue($event);

        });

        $eventProvided = yield $listener->await();
        $this->assertNotNull($eventProvided);

        $this->assertSame($event, $eventProvided);

        $eventProvider->release();
    }

    /**
     * @return Generator
     */
    public function testMultipleSuppliersSameChannel()
    {
        $eventProvider = $this->createEventProvider();
        $event = new Event();

        $promises = [];
        for ($i = 0; $i < 2; $i++) {

            $defer = new Deferred();

            $listener = $eventProvider->subscribe();
            listenEvent($listener, function (Event $event) use ($listener, $defer) {

                $defer->resolve($event);

                $listener->release();

            });

            $promises[] = $defer->promise();

        }

        yield $eventProvider->queue($event);

        $result = yield Promise\all($promises);

        foreach ($result as $eventResult) {
            $this->assertTrue($eventResult instanceof Event);
            $this->assertSame($event, $eventResult);
        }

        $eventProvider->release();
    }

    /**
     * @return Generator
     */
    public function testChannelNamed()
    {
        $channelName = "channel1";

        $eventProvider = $this->createEventProvider();

        $listener = $eventProvider->subscribe($channelName);

        $event = new Event();

        //

        asyncCall(function () use (&$eventProvider, &$channelName, &$event) {

            yield delay(0);

            yield $eventProvider->queue($event, $channelName);

        });

        $eventProvided = yield $listener->await();
        $this->assertNotNull($eventProvided);

        $this->assertSame($event, $eventProvided);

        $eventProvider->release();
    }

    /**
     * @return Generator
     */
    public function testSupplierListenAllChannels()
    {
        $eventProvider = $this->createEventProvider();

        $listener = $eventProvider->subscribe(EventDispatcher::BROADCAST_CHANNEL_NAME);

        $event = new Event();

        //

        asyncCall(function () use (&$eventProvider, &$event) {

            yield delay(0);

            yield $eventProvider->queue($event);

        });

        $eventProvided = yield $listener->await();
        $this->assertNotNull($eventProvided);

        $this->assertSame($event, $eventProvided);

        $eventProvider->release();
    }

    /**
     * @return Generator
     */
    public function testProviderSupplyAllChannels()
    {
        $eventProvider = $this->createEventProvider();

        $listener = $eventProvider->subscribe();

        $event = new Event();

        //

        asyncCall(function () use (&$eventProvider, &$event) {

            yield delay(0);

            yield $eventProvider->queue($event, EventDispatcher::BROADCAST_CHANNEL_NAME);

        });

        $eventProvided = yield $listener->await();
        $this->assertNotNull($eventProvided);

        $this->assertSame($event, $eventProvided);

        $eventProvider->release();
    }

    /**
     * @return Generator
     */
    public function testMismatch()
    {
        $eventProvider = $this->createEventProvider();

        $listener = $eventProvider->subscribe("channel1");

        $event = new Event();

        //

        asyncCall(function () use (&$eventProvider, &$event) {

            yield delay(0);

            yield $eventProvider->queue($event, "channel2");

        });

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Loop stopped without resolving promise or coroutine returned from test method');

        $eventProvided = yield $listener->await();

        $this->assertNull($eventProvided);

        $eventProvider->release();
    }

    /**
     * @return Generator
     */
    public function testEventPropagationStopped()
    {
        $channelName = "channel1";

        $eventProvider = $this->createEventProvider();

        $listener = $eventProvider->subscribe($channelName);

        $event = new Event();

        //

        asyncCall(function () use (&$eventProvider, &$channelName, &$event) {

            yield delay(0);

            yield $eventProvider->queue($event, $channelName);

        });

        $eventProvided = yield $listener->await();
        $this->assertNotNull($eventProvided);

        $this->assertSame($event, $eventProvided);

        $event->stopPropagation();

        asyncCall(function () use (&$eventProvider, &$channelName, &$event) {

            yield delay(0);

            yield $eventProvider->queue($event, $channelName);

        });

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Loop stopped without resolving promise or coroutine returned from test method');

        $eventProvided = yield $listener->await();

        $this->assertNull($eventProvided);

        $eventProvider->release();
    }

    /**
     * @return Generator
     */
    public function testReleasedSupplier()
    {
        $eventProvider = $this->createEventProvider();

        $listener = $eventProvider->subscribe(EventDispatcher::BROADCAST_CHANNEL_NAME);
        $listener->release();

        $event = new Event();

        //

        asyncCall(function () use (&$eventProvider, &$event) {

            yield delay(0);

            yield $eventProvider->queue($event);

        });

        $eventProvided = yield $listener->await();
        $this->assertNull($eventProvided);

        $eventProvider->release();
    }

    /**
     * @return Generator
     */
    public function testListenProvidedEventHelper()
    {
        $eventProvider = $this->createEventProvider();

        $event = new Event();

        $defer = new Deferred();

        $listener = subscribeEvent($eventProvider, "event1", function (Event $event) use (&$defer) {

            $defer->resolve($event);

        });

        yield $eventProvider->queue($event, "event1");

        $result = yield $defer->promise();

        $this->assertSame($event, $result);

        $listener->release();
    }

    /**
     * @return Generator
     */
    public function testListenProvidedEventHelperReleased()
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Loop stopped without resolving promise or coroutine returned from test method');

        $eventProvider = $this->createEventProvider();

        $event = new Event();

        $defer = new Deferred();

        $listener = subscribeEvent($eventProvider, "event1", function (Event $event) use (&$defer) {

            $defer->resolve($event);

        });

        $listener->release();

        yield $eventProvider->queue($event, "event1");

        $result = yield $defer->promise();

        $this->assertSame($event, $result);
    }
}