<?php

declare(strict_types=1);

namespace bitpvp\replay\data;

use Generator;
use pocketmine\promise\PromiseResolver;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\Task;
use pocketmine\timings\TimingsHandler;
use Throwable;

/** @internal Runs replay serialization work in bounded batches on the main thread. */
final class ReplayDataTask extends Task {

    private bool $finished = false;
    private bool $started = false;
    private bool $completed = false;
    private mixed $result = null;
    private ?Throwable $error = null;

    /**
     * @param Generator<mixed, mixed, mixed, mixed> $steps
     * @param PromiseResolver<mixed> $resolver
     * @param \Closure(Throwable): void $onError
     */
    public function __construct(
        private Generator $steps,
        private int $itemsPerTick,
        private TimingsHandler $timings,
        private PromiseResolver $resolver,
        private \Closure $onError
    ) {}

    public function onRun(): void {
        $result = null;
        $completed = false;
        $error = null;

        $this->timings->startTiming();
        try {
            $processed = 0;
            if (!$this->started) {
                $this->started = true;
                if (!$this->steps->valid()) {
                    $result = $this->steps->getReturn();
                    $completed = true;
                } else {
                    // priming a generator runs through its first yielded work item.
                    ++$processed;
                }
            }
            while (!$completed && $processed < $this->itemsPerTick) {
                $this->steps->next();
                if (!$this->steps->valid()) {
                    $result = $this->steps->getReturn();
                    $completed = true;
                    break;
                }
                ++$processed;
            }
        } catch (Throwable $throwable) {
            $error = $throwable;
        } finally {
            $this->timings->stopTiming();
        }

        if ($error !== null) {
            $this->error = $error;
            throw new CancelTaskException();
        } elseif ($completed) {
            $this->result = $result;
            $this->completed = true;
            throw new CancelTaskException();
        }
    }

    public function onCancel(): void {
        if ($this->finished) {
            return;
        }

        $this->finished = true;

        if ($this->error !== null) {
            try {
                ($this->onError)($this->error);
            } catch (Throwable) {
                // Reject the promise even if logging itself fails.
            }
            $this->resolver->reject();
            return;
        }

        if ($this->completed) {
            $this->resolver->resolve($this->result);
        } else {
            // The task was cancelled before it could finish its work.
            $this->resolver->reject();
        }
    }
}
