Caddy does not use `.htaccess`. `/react/` is a static Vite SPA; it must **not** go through `php_fastcgi`.

Do not use `handle_path /react*` — that strips the `/react` prefix. Do not put `php_fastcgi` at the site root next to `file_server`; Caddy’s shortcut rewrites a missing PHP index to root `index.php`, which is how `/react/` used to serve classic login HTML.

```
nova.hive.pizza {
    root * /var/www/HiveNova
    encode gzip
    file_server

    @blocked {
        path *.txt *.md /cache/* /includes/* /tests/* /language/* /install/* /.git/* /external/*
        not path /robots.txt
    }
    respond @blocked 403

    handle /react* {
        try_files {path} /react/index.html
        file_server
    }

    handle {
        php_fastcgi unix//run/php/php8.5-fpm.sock
        file_server
    }
}
```

SPA files are gitignored. Deploy them to `<root>/react/` (for NextMoon: `/var/www/NextMoon/react/`) so `index.html` exists on disk, then `caddy reload`.

Sanity check: `curl -sS https://HOST/react/` should be Vite (`<div id="root">`, `/react/assets/…`), not `styles/resource/css/login/main.css`. `api.php` stays on PHP.
