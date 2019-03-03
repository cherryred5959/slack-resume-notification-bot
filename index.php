<?php

declare(strict_types = 1);

require_once __DIR__ . '/vendor/autoload.php';

use Cherryred5959\SlackResumeNotificationBot\Drivers\JobKoreaDriver;
use Cherryred5959\SlackResumeNotificationBot\SlackBotManager;
use Dotenv\Dotenv;
use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use Slack\ApiClient;
use Workerman\Worker;
use Workerman\Lib\Timer;

$task = new Worker();

$task->onWorkerStart = function () {
    /**
     * @var \React\EventLoop\LoopInterface $loop
     */
    $loop = Worker::getEventLoop();

    Dotenv::create(__DIR__)->load();

    Timer::add(
        getenv('WORKERMAN_TIME_INTERVAL') ?? 300,
        function () use ($loop) {
            $client = (new Client())->setClient(
                new GuzzleClient()
            );

            $slackClient = new ApiClient($loop);
            $slackClient->setToken(getenv('SLACK_TOKEN'));

            (new SlackBotManager(
                $slackClient,
                getenv('SLACK_CHANNEL'),
                getenv('NOTIFICATION_MESSAGE')
            ))->addDriver(
                new JobKoreaDriver(
                    getenv('JOBKOREA_ID'),
                    getenv('JOBKOREA_PASSWORD'),
                    $client
                )
            )->run(explode(',', getenv('JOBKOREA_JOB_ANNOUNCEMENT_NUMBERS')));

            gc_collect_cycles();
        }
    );
};

Worker::runAll();