<?php

namespace Cherryred5959\SlackResumeNotificationBot\Drivers\Tests\Drivers;

use Cherryred5959\SlackResumeNotificationBot\Drivers\JobKoreaDriver;
use Cherryred5959\SlackResumeNotificationBot\Tests\TestCase;
use GuzzleHttp\Psr7\Response;
use Slack\Message\Attachment;

/**
 * Class JobKoreaDriverTest
 * @package Drivers
 */
class JobKoreaDriverTest extends TestCase
{
    /**
     * @return void
     */
    public function testNewNotification(): void
    {
        $channel = $this->faker->numberBetween();
        $nonRead = $this->faker->numberBetween(0, 5);

        $this->setUpNewNotificationMock($channel, $nonRead);

        $jobKorea = (new JobKoreaDriver('test', 'test1234', $this->client));
        $result = $jobKorea->newNotificationMessageData([$channel,]);

        $this->assertArrayHasKey('attachments', $result);
        $this->assertCount(1, $result['attachments']);

        /**
         * @var Attachment $attachment
         */
        $attachment = array_pop($result['attachments']);
        $this->assertCount($nonRead, $attachment->getFields());
    }

    /**
     * @param int $channel
     * @param int $nonRead
     * @return void
     */
    protected function setUpNewNotificationMock(int $channel, int $nonRead): void
    {
        $this->mock->append(new Response(200, [], '
            <form id="loginForm">
                <input type="text" name="DB_Name">
                <input type="text" name="M_ID">
                <input type="password" name="M_PWD">
                <input type="text" name="IP_ONOFF">
            </form>
        '));

        $this->mock->append(new Response(200, [], ''));

        $this->mock->append(new Response(200, [], "
            <form id=\"form\">
                <div class=\"giListRow\">
                    <div class=\"jobTitWrap\">
                        <a class=\"tit\">{$this->faker->title}</a>
                        <span class=\"tahoma\">{$channel}</span>
                    </div>
                    <div class=\"apyStatusBoard\">
                        <ul>
                            <li class=\"apyStatusNotRead\">
                                <a class=\"itemNum\" href=\"{$this->faker->imageUrl()}\">{$nonRead}</a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class=\"giListRow\">
                    <div class=\"jobTitWrap\">
                        <a class=\"tit\">{$this->faker->title}</a>
                        <span class=\"tahoma\">1{$channel}</span>
                    </div>
                    <div class=\"apyStatusBoard\">
                        <ul>
                            <li class=\"apyStatusNotRead\">
                                <a class=\"itemNum\" 
                                   href=\"{$this->faker->imageUrl()}\"
                                >{$this->faker->numberBetween(0, 10)}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </form>
        "));

        $resumeText = '';
        for ($i = 0; $i < $nonRead; $i++) {
            $resumeText .= "
                <div class=\"schListWrap\">
                    <div class=\"mtcSchListTb\">
                        <table>
                            <tbody>
                            <tr>
                                <td>
                                    <span class=\"name\">{$this->faker->name}</span>
                                    <span class=\"nmAge\">{$this->faker->numberBetween(20, 50)}</span>
                                    <span class=\"eduInfo\">
                                        <a class=\"devTypeAplctHref\" href=\"{$this->faker->imageUrl()}\">
                                            <span class=\"item\">{$this->faker->text}</span>
                                            <span class=\"item\">{$this->faker->text}</span>                            
                                        </a>
                                    </span>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            ";
        }
        $this->mock->append(new Response(200, [], "
            <input name=\"GI_No\" value=\"{$this->faker->numberBetween()}\">
            {$resumeText}
        "));
    }
}
