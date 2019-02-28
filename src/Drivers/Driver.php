<?php

declare(strict_types = 1);

namespace Cherryred5959\SlackResumeNotificationBot\Drivers;

/**
 * Interface Driver
 * @package Drivers
 */
interface Driver
{
    /**
     * @param array $channels
     * @return array
     */
    public function newNotificationMessageData(array $channels): array;
}