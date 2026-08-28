
nova.hive.pizza {
    root * /var/www/HiveNova
    file_server
    tls internal
    php_fastcgi unix//run/php/php7.4-fpm.sock


    # Block sensitive extensions/paths, but allow /robots.txt for crawlers.
    @blocked {
        path *.txt *.md /cache/* /includes/* /cache/* /includes/* /styles/* /tests/* /language/* /install/* /.git/* /external/*
        not path /robots.txt
    }
    respond @blocked 403
}

