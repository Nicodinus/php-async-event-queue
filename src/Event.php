<?php


namespace Nicodinus\PhpAsync\EventQueue;


class Event implements EventInterface
{
    /** @var bool */
    private bool $isPropagationStopped;

    //

    /**
     * Event constructor.
     */
    public function __construct()
    {
        $this->isPropagationStopped = false;
    }

    /**
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->isPropagationStopped;
    }

    /**
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->isPropagationStopped = true;
    }
}