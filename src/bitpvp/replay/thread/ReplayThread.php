<?php

declare(strict_types=1);

namespace bitpvp\replay\thread;

use pmmp\thread\ThreadSafeArray;
use pocketmine\snooze\SleeperHandlerEntry;
use pocketmine\thread\Thread;

final class ReplayThread extends Thread {

    /** @var ThreadSafeArray<int, ThreadPacket> */
    private ThreadSafeArray $inQueue;
    /** @var ThreadSafeArray<int, ThreadPacket> */
    private ThreadSafeArray $outQueue;

    public function __construct(
        protected SleeperHandlerEntry $entry
    ) {
        $this->inQueue  = new ThreadSafeArray();
        $this->outQueue = new ThreadSafeArray();
    }

    protected function onRun(): void {
        $notifier = $this->entry->createNotifier();
        while (!$this->isKilled) {
            /** @var ThreadPacket|null $packet */
            $packet = $this->synchronized(function (): ?ThreadPacket {
                while (!$this->isKilled && $this->inQueue->count() === 0) {
                    $this->wait();
                }
                return $this->inQueue->shift();
            });

            if ($packet !== null) {
                try {
                    $result = $packet->execute();
                    if ($result !== null) {
                        $this->synchronized(function (ThreadPacket $result) use ($notifier): void {
                            $this->outQueue[] = $result;
                            $notifier->wakeupSleeper();
                        }, $result);
                    }
                } catch (\Throwable) {

                }
            }
        }
    }

    public function submit(ThreadPacket $packet): void {
        $this->synchronized(function () use ($packet): void {
            $this->inQueue[] = $packet;
            $this->notify();
        });
    }

    /**
     * @return ThreadPacket[]
     */
    public function collectResults(): array {
        return $this->synchronized(function(): array {
            $results = [];
            while(($r = $this->outQueue->shift()) !== null) {
                $results[] = $r;
            }
            return $results;
        });
    }
}
