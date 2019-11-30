# Slack Resume Notification Bot

### 미열람 이력서 알림을 위한 슬랙봇

## Usage
```
git clone git@github.com:cherryred5959/slack-resume-notification-bot.git
cd slack-resume-notification-bot/docker
docker-compose up -d --build
docker-compose exec php bash
cd /var/www/html
composer run post-root-package-install
composer install
php index.php start -d
```

## Supported Job Sites 
* [잡코리아](http://www.jobkorea.co.kr)

## Note
* https://github.com/walkor/Workerman
* https://api.slack.com/bot-users
