<?php


namespace Nicodinus\PhpAsync\EventQueue;


use Amp\Promise;
use SplObjectStorage;
use function Amp\call;

class EventProvider implements EventProviderInterface
{
    /** @var string */
    public const DEFAULT_CHANNEL_NAME = '__default__';

    /** @var string */
    public const BROADCAST_CHANNEL_NAME = '__broadcast__';

    //

    /** @var SplObjectStorage<EventSupplier>[] */
    private array $registry;

    /** @var bool */
    private bool $isReleased;

    /** @var bool */
    private bool $isQueueRunning;

    /** @var callable[] */
    private array $deferQueueCallables;

    //

    /**
     * EventProvider constructor.
     */
    public function __construct()
    {
        $this->registry = [
            self::BROADCAST_CHANNEL_NAME => new SplObjectStorage(),
            self::DEFAULT_CHANNEL_NAME => new SplObjectStorage(),
        ];

        $this->isReleased = false;
        $this->isQueueRunning = false;

        $this->deferQueueCallables = [];
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->release();
    }

    /**
     * @inheritDoc
     */
    public function isReleased(): bool
    {
        return $this->isReleased;
    }

    /**
     * @inheritDoc
     */
    public function release(): void
    {
        if ($this->isReleased()) {
            return;
        }
        $this->isReleased = true;

        foreach ($this->registry as $storage) {

            /** @var EventSupplier $eventSupplier */
            foreach ($storage as $eventSupplier) {
                $eventSupplier->release();
            }
            $storage->removeAll($storage);

        }
        $this->registry = [];
    }

    /**
     * @inheritDoc
     */
    public function getSupplier(?string $channel = self::DEFAULT_CHANNEL_NAME): ?EventSupplierInterface
    {
        if ($this->isReleased()) {
            return null;
        }

        if ($channel === null) {
            $channel = self::BROADCAST_CHANNEL_NAME;
        }

        $eventSupplier = new EventSupplier(function (EventSupplier $eventSupplier) {

            if ($this->isReleased()) {
                return;
            }

            if (isset($this->registry[$eventSupplier->getChannel()])) {

                $callable = function () use ($eventSupplier) {
                    $this->registry[$eventSupplier->getChannel()]->detach($eventSupplier);
                };

                if (!$this->isQueueRunning) {
                    $callable();
                } else {
                    $this->deferQueueCallables[] = $callable;
                }

            }

        }, $channel);

        $callable = function () use ($channel, $eventSupplier) {

            if (!isset($this->registry[$channel])) {
                $this->registry[$channel] = new SplObjectStorage();
            }

            $this->registry[$channel]->attach($eventSupplier);

        };

        if (!$this->isQueueRunning) {
            $callable();
        } else {
            $this->deferQueueCallables[] = $callable;
        }

        return $eventSupplier;
    }

    /**
     * @inheritDoc
     */
    public function queue(object $event, ?string $channel = self::DEFAULT_CHANNEL_NAME): Promise
    {
        return call(function () use (&$event, &$channel) {

            if ($this->isReleased()) {
                return null;
            }

            $this->isQueueRunning = true;

            if ($channel === self::BROADCAST_CHANNEL_NAME) {

                $queueChannels = array_keys($this->registry);

            } else {

                $queueChannels = [
                    self::BROADCAST_CHANNEL_NAME,
                ];

                if (isset($this->registry[$channel])) {
                    array_unshift($queueChannels, $channel);
                }

            }

            $counter = 0;

            foreach ($queueChannels as $currentChannel) {

                if ($this->isReleased()) {
                    break;
                }

                /** @var EventSupplier $eventSupplier */
                foreach ($this->registry[$currentChannel] as $eventSupplier) {

                    if ($this->isReleased()) {
                        break 2;
                    }

                    if ($eventSupplier->isReleased()) {
                        continue;
                    }

                    if ($event instanceof EventInterface && $event->isPropagationStopped()) {
                        break 2;
                    }

                    yield $eventSupplier->getEmitter()->emit($event);

                    $counter += 1;

                }

            }

            $this->isQueueRunning = false;
            foreach ($this->deferQueueCallables as $callable) {
                $callable();
            }
            $this->deferQueueCallables = [];

            return $counter;

        });
    }
}