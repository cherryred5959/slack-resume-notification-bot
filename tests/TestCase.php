<?php

namespace Cherryred5959\SlackResumeNotificationBot\Tests;

use Faker\Factory;
use Faker\Generator;
use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * @var MockHandler
     */
    protected $mock;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Generator
     */
    protected $faker;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->mock = new MockHandler();
        $handler = HandlerStack::create($this->mock);

        $this->client = (new Client())->setClient(
            new GuzzleClient([
                'handler' => $handler,
            ])
        );

        $this->faker = Factory::create();
    }
}