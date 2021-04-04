<?php


namespace Nicodinus\PhpAsync\EventQueue;


use Amp\Emitter;
use Amp\Promise;
use function Amp\call;

class EventSupplier implements EventSupplierInterface
{
    /** @var string|null */
    private ?string $channel;

    /** @var Emitter */
    private Emitter $emitter;

    /** @var bool */
    private bool $isReleased;

    /** @var callable */
    private $onReleasedCallable;

    //

    /**
     * EventSupplier constructor.
     *
     * @param callable $onReleasedCallable
     * @param string|null $channel
     */
    public function __construct(callable $onReleasedCallable, ?string $channel = null)
    {
        $this->onReleasedCallable = $onReleasedCallable;
        $this->channel = $channel;

        $this->isReleased = false;
        $this->emitter = new Emitter();
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

        $this->emitter->complete();

        ($this->onReleasedCallable)($this);
    }

    /**
     * @inheritDoc
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @inheritDoc
     */
    public function await(): Promise
    {
        return call(function () {

            if (yield $this->getEmitter()->iterate()->advance()) {
                return $this->getEmitter()->iterate()->getCurrent();
            }

            return null;

        });
    }

    /**
     * @return Emitter
     */
    public function getEmitter(): Emitter
    {
        return $this->emitter;
    }
}