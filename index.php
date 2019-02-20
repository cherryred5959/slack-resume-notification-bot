<?php

declare(strict_types = 1);

require_once __DIR__ . '/vendor/autoload.php';

use Slack\Channel;
use Slack\Message\Attachment;
use Slack\Message\AttachmentField;
use Workerman\Worker;
use Workerman\Lib\Timer;
use Dotenv\Dotenv;

Dotenv::create(__DIR__)->load();

$task = new Worker();
$task->onWorkerStart = function () {
    $loop = Worker::getEventLoop();

    $client = new \Slack\ApiClient($loop);
    $client->setToken(getenv('SLACK_TOKEN'));
    Timer::add(
        getenv('WORKERMAN_TIME_INTERVAL') ?? 300,
        function () use ($client) {
            $client->getChannelByName(getenv('SLACK_CHANNEL'))->then(function (Channel $channel) use ($client) {
                $message = $client->getMessageBuilder()
                    ->setChannel($channel)
                    ->setText('Hello, all!')
                    ->addAttachment(new Attachment('My Attachment', 'attachment text'))
                    ->addAttachment(new Attachment('Build Status', 'Build failed! :/', 'build failed', 'danger'))
                    ->addAttachment(new Attachment('Some Fields', 'fields', null, '#BADA55', [
                            new AttachmentField('Title1', 'Text', false),
                            new AttachmentField('Title2', 'Some other text', true)]
                    ))
                    ->create();

                $client->postMessage($message);
            });
        }
    );
};

Worker::runAll();