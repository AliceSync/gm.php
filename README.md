# gm.php
Manage your server with a single php script file

> php version >= 8.0.0 !

> Support php-fpm swoole

> implementation php-cli

> Support kiwivm api and solusvm api

> display images

![image](https://github.com/AliceSync/gm.php/blob/master/README_images/php_info.png)
![image](https://github.com/AliceSync/gm.php/blob/master/README_images/system_info.png)
![image](https://github.com/AliceSync/gm.php/blob/master/README_images/realtime_info.png)
![image](https://github.com/AliceSync/gm.php/blob/master/README_images/realtime_info1.png)
![image](https://github.com/AliceSync/gm.php/blob/master/README_images/vps_info.png)
![image](https://github.com/AliceSync/gm.php/blob/master/README_images/log_display_info.png)
![image](https://github.com/AliceSync/gm.php/blob/master/README_images/log_info.png)
![image](https://github.com/AliceSync/gm.php/blob/master/README_images/home.png)

> if you need use nginx log display

```nginx config
underscores_in_headers on;

log_format info_cf '[
    time:$year-$month-$day $hour:$minutes:$seconds
    from:$remote_addr
    cf:$http_cf_connecting_ip
    ua:$http_user_agent
    url:$http_host$request_uri
    fromurl:$http_referer
]';
service {
    if ($time_iso8601 ~ "^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})") {
        set $year $1;
        set $month $2;
        set $day $3;
        set $hour $4;
        set $minutes $5;
        set $seconds $6;
    }
}
access_log    /youlogdir/youlogname-$year-$month-$day.log info_cf;
```

- use layui and jquery

- Cdnjs.com provides acceleration

- Note that the git committer "HarukaMa" added "root@vultr.guest" to his account by using github's behavior of not verifying the email address. Submissions made by accounts without mail settings will be associated with this user "HarukaMa". It's a very annoying behavior.

- If your git-config doesn't modify the email address, then I suggest you modify it to your own.