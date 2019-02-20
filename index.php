<?php

declare(strict_types = 1);

require_once __DIR__ . '/vendor/autoload.php';

use BotMan\Drivers\Slack\Factory;
use Workerman\Worker;
use Workerman\Lib\Timer;
use Dotenv\Dotenv;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Slack\SlackRTMDriver;

Dotenv::create(__DIR__)->load();

$task = new Worker();
$task->onWorkerStart = function () {
    Timer::add(
        getenv('WORKERMAN_TIME_INTERVAL') ?? 300,
        function () {
            echo getenv('TIME_INTERVAL');
        }
    );
};

$port = getenv('WORKERMAN_PORT') ?? 6000;
$io = new Worker("websocket://0.0.0.0:{$port}");
$io->count = 1;
$io->onWorkerStart = function () {
    global $botman;

    $loop = Worker::getEventLoop();
    DriverManager::loadDriver(SlackRTMDriver::class);

    $botman = (new Factory)->createForRTM([
        'slack' => [
            'token' => getenv('SLACK_TOKEN'),
        ],
    ], $loop);
};
$io->onMessage = function($connection, $data) {
    global $botman;

    dump(dump($data));
    dump($connection);
};


Worker::runAll();