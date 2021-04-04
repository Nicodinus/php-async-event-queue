<?php


namespace Nicodinus\PhpAsync\EventQueue;


use Throwable;
use function Amp\asyncCall;
use function Amp\call;


/**
 * @param EventSubscriberInterface $subscriber
 * @param callable $callable callable(object $event)
 * @param callable|null $errorHandler callable(Throwable $throwable, ?object $event = null)
 *
 * @return void
 */
function listenEvent(EventSubscriberInterface $subscriber, callable $callable, ?callable $errorHandler = null): void
{
    asyncCall(static function () use (
        $subscriber,
        $callable,
        $errorHandler
    ) {

        $event = null;

        try {

            while (!$subscriber->isReleased()) {

                $event = yield $subscriber->await();
                if (!$event) {
                    continue;
                }

                //dump(get_class($event));

                yield call($callable, $event);

            }

        } catch (Throwable $throwable) {

            if (!empty($errorHandler)) {
                yield call($errorHandler, $throwable, $event);
            } else {
                throw $throwable;
            }

        }

        $subscriber->release();

    });
}

/**
 * @param EventDispatcherInterface $eventDispatcher
 * @param string $eventName
 * @param callable $callable callable(object $event)
 * @param callable|null $errorHandler callable(Throwable $throwable, ?object $event = null)
 *
 * @return EventSubscriberInterface
 */
function subscribeEvent(EventDispatcherInterface $eventDispatcher, string $eventName, callable $callable, ?callable $errorHandler = null): EventSubscriberInterface
{
    $listener = $eventDispatcher->subscribe($eventName);

    listenEvent($listener, $callable, $errorHandler);

    return $listener;
}