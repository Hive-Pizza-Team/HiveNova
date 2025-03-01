
nova.hive.pizza {
    root * /var/www/HiveNova
    file_server
    tls internal
    php_fastcgi unix//run/php/php7.4-fpm.sock


    @blocked {
        path *.txt *.md /cache/* /includes/* /cache/* /includes/* /styles/* /tests/* /language/* /install/* /.git/* /external/*
    }
    respond @blocked 403
}

