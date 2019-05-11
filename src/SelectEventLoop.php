<?php

declare(strict_types=1);


namespace Zaloopa;

class SelectEventLoop
{
    private const MAX_TIME_FOR_ECECUTION_MILISEC = 200000;
    /**
     * @var array
     */
    private $readListeners;

    /**
     * @var array
     */
    private $writeListeners;
    /**
     * @var array
     */
    private $readStreams;

    /**
     * @var array
     */
    private $writeStreams;

    public function __construct()
    {
        $this->readListeners = [];
        $this->writeListeners = [];
        $this->readStreams = [];
        $this->writeStreams = [];
    }


    /**
     * @param resource $stream
     * @param callable $functor
     */
    public function registerReadStream($stream, callable $functor): void
    {
        $streamId = (int)$stream;
        $this->readListeners[$streamId] = $functor;
        $this->readStreams[$streamId] = $stream;
    }

    public function registerWriteStream($stream, callable $functor): void
    {
        $streamId = (int)$stream;
        $this->writeListeners[$streamId] = $functor;
        $this->writeStreams[$streamId] = $stream;
    }

    /**
     * @param resource $stream
     */
    public function removeReadStream($stream): void
    {
        $streamId = (int)$stream;
        unset($this->readStreams[$streamId], $this->readListeners[$streamId]);
    }

    /**
     * @param resource $stream
     */
    public function removeWriteStream($stream): void
    {
        $streamId = (int)$stream;
        unset($this->writeListeners[$streamId], $this->writeStreams[$streamId]);
    }

    //Note that you should change the calctimeout function below to divide the outcome by 1.000.000 otherwise you'll be waiting for two years instead of one minute for the socket to timeout...
    private function calculateTimeout(int $maxTime, float $startTime): float
    {
        return $maxTime - ((microtime(true) - $startTime) * 1000000);
    }

    public function run(): void
    {
        $maxTime = self::MAX_TIME_FOR_ECECUTION_MILISEC;
        $startTime = microtime(true);

        // Need copy, because stream_select modified arrays with streams.
        // Just removed read stream if we register two streams (to read and to write). I don't know why...
        $readStreams = array_values($this->readStreams);
        $writeStreams = array_values($this->readListeners);
        $except = []; //todo: need to do something with that :^|

        while (true) {
            $streamsModified = @stream_select($readStreams, $writeStreams, $except, 0, (int)$this->calculateTimeout($maxTime, $startTime));

            //Has not new i/o events, successful complete
            if ($streamsModified === false) {
                exit(0);
            }

            //Demultiplexing
            if ($streamsModified > 0) {
                foreach ($this->readStreams as $stream) {
                    $functor = $this->readListeners[(int)$stream];
                    $functor($stream);
                }

                foreach ($this->writeStreams as $stream) {
                    $functor = $this->writeListeners[(int)$stream];
                    $functor($stream);
                }
            }
        }

    }
}