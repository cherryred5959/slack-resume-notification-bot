<?php

declare(strict_types = 1);

require_once __DIR__ . '/vendor/autoload.php';

use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\Drivers\Slack\SlackDriver;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;
use Workerman\Lib\Timer;
use Dotenv\Dotenv;
use BotMan\BotMan\Drivers\DriverManager;

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

DriverManager::loadDriver(SlackDriver::class);

$port = getenv('WORKERMAN_PORT') ?? 8000;
$http = new Worker("http://0.0.0.0:{$port}");
$http->count = 1;
$http->onMessage = function (TcpConnection $connection, array $data) {
    $botman = BotManFactory::create([
        'slack' => [
            'token' => getenv('SLACK_TOKEN')
        ],
        null,
        new \Symfony\Component\HttpFoundation\Request(
            $data['get'],
            $data['post'],
            [],
            $data['cookie'],
            $data['files'],
            $data['server'],
            []
        )
    ]);

   /* $botman->fallback(function (BotMan $bot) {
        $bot->reply('I heard you! :)');
    });*/

    $botman->listen();
    $botman->reply('test');

    $connection->send("hello world \n");
};


Worker::runAll();