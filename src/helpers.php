<?php


namespace Nicodinus\PhpAsync\EventQueue;


use Throwable;
use function Amp\asyncCall;
use function Amp\call;


/**
 * @param EventSupplierInterface $listener
 * @param callable $callable callable(object $event)
 * @param callable|null $errorHandler callable(Throwable $throwable, ?object $event = null)
 *
 * @return void
 */
function listenEvent(EventSupplierInterface $listener, callable $callable, ?callable $errorHandler = null): void
{
    asyncCall(static function () use (
        $listener,
        $callable,
        $errorHandler
    ) {

        $event = null;

        try {

            while (!$listener->isReleased()) {

                $event = yield $listener->await();
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

        $listener->release();

    });
}

/**
 * @param EventProviderInterface $eventProvider
 * @param string $eventName
 * @param callable $callable callable(object $event)
 * @param callable|null $errorHandler callable(Throwable $throwable, ?object $event = null)
 *
 * @return EventSupplierInterface
 */
function listenProvidedEvent(EventProviderInterface $eventProvider, string $eventName, callable $callable, ?callable $errorHandler = null): EventSupplierInterface
{
    $listener = $eventProvider->getSupplier($eventName);

    listenEvent($listener, $callable, $errorHandler);

    return $listener;
}