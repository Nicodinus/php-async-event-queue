<?php


namespace Nicodinus\PhpAsync\EventQueue;


interface EventInterface
{
    /**
     * @return bool
     */
    public function isPropagationStopped(): bool;

    /**
     * @return void
     */
    public function stopPropagation(): void;
}