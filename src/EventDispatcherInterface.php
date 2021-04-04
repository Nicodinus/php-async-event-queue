<?php


namespace Nicodinus\PhpAsync\EventQueue;


use Amp\Promise;

interface EventDispatcherInterface
{
    /**
     * @return bool
     */
    public function isReleased(): bool;

    /**
     * @return void
     */
    public function release(): void;

    /**
     * @param string|null $channel When null -> listen all channels
     *
     * @return EventSubscriberInterface|null
     */
    public function subscribe(?string $channel = null): ?EventSubscriberInterface;

    /**
     * @param object $event
     * @param string|null $channel When null -> broadcast event to all event suppliers
     *
     * @return Promise<int|null>
     */
    public function queue(object $event, ?string $channel = null): Promise;
}