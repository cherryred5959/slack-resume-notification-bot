<?php

declare(strict_types = 1);

require_once __DIR__ . '/vendor/autoload.php';

use Goutte\Client;
use Slack\Channel;
use Slack\Message\Attachment;
use Slack\Message\AttachmentField;
use Symfony\Component\DomCrawler\Crawler;
use Workerman\Worker;
use Workerman\Lib\Timer;
use Dotenv\Dotenv;

Dotenv::create(__DIR__)->load();

$inMemoryCache = [];

$task = new Worker();
$task->onWorkerStart = function () {

    $loop = Worker::getEventLoop();

    $client = new \Slack\ApiClient($loop);
    $client->setToken(getenv('SLACK_TOKEN'));

    Timer::add(
        getenv('WORKERMAN_TIME_INTERVAL') ?? 300,
        function () use ($client) {
            $goutteClient = new Client();

            $crawler = $goutteClient->request('GET', 'http://www.jobkorea.co.kr/Corp/Main');
            $loginForm = $crawler->filter('#loginForm')->first()->form();

            $goutteClient->submit($loginForm, [
                'DB_Name' => 'GI',
                'M_ID' => getenv('JOBKOREA_ID'),
                'M_PWD' => getenv('JOBKOREA_PASSWORD'),
                'IP_ONOFF' => 'N',
            ]);

            $jobAnnouncementPage = $goutteClient->request('GET', 'http://www.jobkorea.co.kr/Corp/GiMng/List');

            $numberOfNonReadList = $jobAnnouncementPage->filter('form#form div.giListRow')->each(function (Crawler $node) use ($goutteClient) {
                $jobAnnouncementNumber = trim((clone $node)->filter('div.jobTitWrap span.tahoma')->first()->text());
                $selectedJobAnnouncementNumbers = explode(',', getenv('JOBKOREA_JOB_ANNOUNCEMENT_NUMBERS'));

                if (! in_array($jobAnnouncementNumber, $selectedJobAnnouncementNumbers, true)) {
                    return null;
                }

                $numberOfNonReads = (int) trim((clone $node)->filter('div.apyStatusBoard li.apyStatusNotRead a.itemNum')->first()->text());
                if (! ($numberOfNonReads > 0)) {
                    return null;
                }

                $resumePage = $goutteClient->click((clone $node)->filter('div.apyStatusBoard li.apyStatusNotRead a.itemNum')->first()->link());
                $gno = (clone $resumePage)->filter('input[name=GI_No]')->attr('value');
                $query = http_build_query(['GI_No' => $gno]);

                $resumes = (clone $resumePage)->filter('div.schListWrap div.mtcSchListTb tbody tr:not(.infoBx)')->each(function (Crawler $node) use ($query) {
                    return [
                        'name' => trim((clone $node)->filter('span.name')->first()->text()),
                        'age' => trim((clone $node)->filter('span.nmAge')->first()->text()),
                        'link' => (clone $node)->filter('a.devTypeAplctHref')->first()->link()->getUri() . "&{$query}",
                    ];
                });

                $title = trim((clone $node)->filter('div.jobTitWrap a.tit')->first()->text());

                return compact('jobAnnouncementNumber', 'numberOfNonReads', 'title', 'resumes');
            });

            $numberOfNonReadList = array_filter($numberOfNonReadList, function ($numberOfNonRead) {
                global $inMemoryCache;

                if (empty($numberOfNonRead)) {
                    return false;
                }


                if (($inMemoryCache[$numberOfNonRead['jobAnnouncementNumber']] ?? null) === $numberOfNonRead['numberOfNonReads']) {
                    return false;
                }

                $inMemoryCache[$numberOfNonRead['jobAnnouncementNumber']] = $numberOfNonRead['numberOfNonReads'];

                return true;
            });

            if (empty($numberOfNonReadList)) {
                return;
            }

            $client->getChannelByName(getenv('SLACK_CHANNEL'))
                ->then(function (Channel $channel) use ($client, $numberOfNonReadList) {
                    $message = $client->getMessageBuilder()
                        ->setChannel($channel)
                        ->setText(getenv('NOTIFICATION_MESSAGE') ?? '새로운 미열람 이력서가 있습니다.');

                    foreach ($numberOfNonReadList as $numberOfNonRead) {
                        $message->addAttachment(new Attachment(
                            $numberOfNonRead['title'],
                            "{$numberOfNonRead['numberOfNonReads']}건",
                            null,
                            'danger',
                            null,
                            array_map(function (array $resume) {
                                return new AttachmentField("{$resume['name']}({$resume['age']})", "<{$resume['link']}|이력서 보러 가기>", true);
                            }, $numberOfNonRead['resumes'])
                        ));
                    }

                    $client->postMessage($message->create());
                });
        }
    );
};

Worker::runAll();