<?php

declare(strict_type=1);

namespace Cherryred5959\SlackResumeNotificationBot;

use Cherryred5959\SlackResumeNotificationBot\Drivers\Driver;
use Slack\ApiClient;
use Slack\Channel;
use Slack\Message\Message;

/**
 * Class SlackClient
 * @package Cherryred5959\SlackResumeNotificationBot
 */
class SlackBotManager
{
    /**
     * @var ApiClient
     */
    protected $client;

    /**
     * @var array|Driver[]
     */
    protected $drivers;

    /**
     * @var string
     */
    protected $channelName;

    /**
     * @var string
     */
    protected $messageTitle;

        /**
     * SlackBotManager constructor.
     * @param ApiClient $client
     * @param string $channelName
     * @param string $messageTitle
     */
    public function __construct(ApiClient $client, string $channelName = 'general', string $messageTitle = 'New!')
    {
        $this->client = $client;
        $this->drivers = [];
        $this->channelName = $channelName;
        $this->messageTitle = $messageTitle;
    }

    /**
     * @param Driver $driver
     * @return SlackBotManager
     */
    public function addDriver(Driver $driver): self
    {
        $this->drivers[] = $driver;
        return $this;
    }

    /**
     * @param array $channels
     * @return void
     */
    public function run(array $channels): void
    {
        foreach ($this->drivers as $driver) {
            $data =  $driver->newNotificationMessageData($channels);
            $this->client->getChannelByName($this->channelName)->then(function (Channel $channel) use ($data) {
                $data['channel'] = $channel->getId();
                $data['text'] = $this->messageTitle;
                $this->client->postMessage(new Message($this->client, $data));
            });
        }
    }
}