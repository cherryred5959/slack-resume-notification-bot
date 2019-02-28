<?php

declare(strict_types = 1);

namespace Cherryred5959\SlackResumeNotificationBot\Drivers;

use Slack\Message\Attachment;
use Slack\Message\AttachmentField;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;

/**
 * Class JobKoreaDriver
 * @package Drivers
 */
class JobKoreaDriver implements Driver
{
    protected const BASE_URL = 'http://www.jobkorea.co.kr';

    protected const LOGIN_URL = self::BASE_URL . '/Corp/Main';

    protected const JOB_ANNOUNCEMENT_URL = self::BASE_URL . '/Corp/GiMng/List';

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var Client
     */
    protected $client;

    /**
     * JobKoreaDriver constructor.
     * @param string $id
     * @param string $password
     * @param Client $client
     */
    public function __construct(string $id, string $password, Client $client)
    {
        $this->id = $id;
        $this->password = $password;
        $this->client = $client;
    }

    /**
     * @param array $channels
     * @return array
     */
    public function newNotificationMessageData(array $channels): array
    {
        $this->login();

        $data = [];

        foreach ($this->getChannels($channels) as $channel) {
            $data['attachments'][] = new Attachment(
                $channel['jobAnnouncementTitle'],
                "{$channel['numberOfNonReads']}건",
                null,
                'danger',
                null,
                array_map(function (array $resume) {
                    return new AttachmentField(
                        "{$resume['name']}({$resume['age']})",
                        "<{$resume['link']}|이력서 보러 가기>",
                        true
                    );
                }, $channel['resumes'])
            );
        };

        return $data;
    }

    /**
     * @return void
     */
    protected function login(): void
    {
        $loginForm = $this->goLoginPage()->filter('#loginForm')->first()->form();

        $this->client->submit($loginForm, [
            'DB_Name' => 'GI',
            'M_ID' => $this->id,
            'M_PWD' => $this->password,
            'IP_ONOFF' => 'N',
        ]);
    }

    /**
     * @return Crawler
     */
    protected function goLoginPage(): Crawler
    {
        return $this->client->request('GET', self::LOGIN_URL);
    }

    /**
     * @param array $channels
     * @return array
     */
    protected function getChannels(array $channels): array
    {
        $jobAnnouncements = $this->goJobAnnouncementPage()->filter('form#form div.giListRow');

        $numberOfNonReadList = $jobAnnouncements->each(function (Crawler $node) use ($channels) {
            $jobAnnouncementNumber = $this->getJobAnnouncementNumber($node);
            if (! in_array($jobAnnouncementNumber, $channels, true)) {
                return null;
            }

            $numberOfNonReads = $this->getNumberOfNonReads($node);
            if (! ($numberOfNonReads > 0)) {
                return null;
            }

            $jobAnnouncementTitle = $this->getJobAnnouncementTitle($node);

            $resumes = $this->getResumes($this->getResumePageLink($node));

            return compact(
                'jobAnnouncementNumber',
                'jobAnnouncementTitle',
                'numberOfNonReads',
                'resumes'
            );
        });

        return $this->prepareResponse($numberOfNonReadList);
    }

    /**
     * @return Crawler
     */
    protected function goJobAnnouncementPage(): Crawler
    {
        return $this->client->request('GET', self::JOB_ANNOUNCEMENT_URL);
    }

    /**
     * @param Crawler $node
     * @return int
     */
    protected function getJobAnnouncementNumber(Crawler $node): int
    {
        return (int) $node->filter('div.jobTitWrap span.tahoma')->first()->text();
    }

    /**
     * @param Crawler $node
     * @return int
     */
    protected function getNumberOfNonReads(Crawler $node): int
    {
        return (int) $node->filter('div.apyStatusBoard li.apyStatusNotRead a.itemNum')->first()->text();
    }

    /**
     * @param Crawler $node
     * @return string
     */
    protected function getJobAnnouncementTitle(Crawler $node): string
    {
        return trim($node->filter('div.jobTitWrap a.tit')->first()->text());
    }

    /**
     * @param Crawler $node
     * @return Link
     */
    protected function getResumePageLink(Crawler $node): Link
    {
        return $node->filter('div.apyStatusBoard li.apyStatusNotRead a.itemNum')->first()->link();
    }

    /**
     * @param Link $link
     * @return array
     */
    protected function getResumes(Link $link)
    {
        $resumePage = $this->goResumePage($link);

        $httpQuery = http_build_query([
            'GI_No' => $this->getGno($resumePage),
        ]);

        return $resumePage->filter('div.schListWrap div.mtcSchListTb tbody tr:not(.infoBx)')
            ->each(function (Crawler $node) use ($httpQuery) {
                return [
                    'name' => $this->getResumeName($node),
                    'age' => $this->getResumeAge($node),
                    'link' => "{$this->getResumeLink($node)}&{$httpQuery}",
                    'annual' => $this->getResumeAnnual($node),
                ];
        });
    }

    /**
     * @param Link $link
     * @return Crawler
     */
    protected function goResumePage(Link $link): Crawler
    {
        return $this->client->click($link);
    }

    /**
     * @param Crawler $resumePage
     * @return string
     */
    protected function getGno(Crawler $resumePage): string
    {
        return (string) $resumePage->filter('input[name=GI_No]')->attr('value');
    }

    /**
     * @param Crawler $node
     * @return string
     */
    protected function getResumeName(Crawler $node): string
    {
        return trim($node->filter('span.name')->first()->text());
    }

    /**
     * @param Crawler $node
     * @return string
     */
    protected function getResumeAge(Crawler $node): string
    {
        return trim($node->filter('span.nmAge')->first()->text());
    }

    /**
     * @param Crawler $node
     * @return string
     */
    protected function getResumeLink(Crawler $node): string
    {
        return $node->filter('a.devTypeAplctHref')->first()->link()->getUri();
    }

    /**
     * @param Crawler $node
     * @return string
     */
    protected function getResumeAnnual(Crawler $node): string
    {
        $annualNode = $node->filter('span.eduInfo a span.item');
        return trim($annualNode->eq(0)->text()) . trim($annualNode->eq(1)->text());
    }

    /**
     * @param array $numberOfNonReadList
     * @return array
     */
    protected function prepareResponse(array $numberOfNonReadList): array
    {
        return array_filter($numberOfNonReadList, function ($numberOfNonRead) {
            if (is_null($numberOfNonRead)) {
                return false;
            }

            return true;
        });
    }
}