<?php


namespace Nicodinus\PhpAsync\EventQueue;


use Amp\Promise;

interface EventSubscriberInterface
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
     * @return Promise<object|null>
     */
    public function await(): Promise;

    /**
     * @return string|null
     */
    public function getChannel(): ?string;
}