Nginx does not support .htaccess files, which means that by using nginx you are exposed to data leakage from your server instance.

Insert the content below into your nginx configuration file.

If you also front the site with a reverse proxy that blocks `*.txt`, add an exception so `/robots.txt` remains publicly crawlable (see `caddy.md`).

Public lobby SEO canonical host is `https://moonsiege.com` (see `PublicSeo::CANONICAL_BASE`). Prefer edge/CDN rules so `http://` returns 301 to HTTPS (not 500), and so `moon.hive.pizza` / `www.moonsiege.com` lobby URLs 301 to `https://moonsiege.com` while keeping path and query. Do **not** redirect `game.php` across hosts — session cookies are host-scoped.

```
    # Example: HTTP → HTTPS (server-level; adjust for your TLS termination)
    # if ($scheme = http) { return 301 https://$host$request_uri; }

    # Example: lobby alias hosts → moonsiege.com (exclude game/admin/cron)
    # if ($host ~* ^(moon\.hive\.pizza|www\.moonsiege\.com)$) {
    #   set $skip_host_redirect 0;
    #   if ($request_uri ~* ^/(game|admin|cronjob)\.php) { set $skip_host_redirect 1; }
    #   if ($skip_host_redirect = 0) { return 301 https://moonsiege.com$request_uri; }
    # }

    location = /robots.txt {
    allow all;
    }

    location /cache/ {
    deny all;
    }

    location /includes/ {
    deny all;
    }

    location /includes/backups/ {
    deny all;
    }

    location /styles/templates/ {
    deny all;
    }

    location /tests/ {
    deny all;
    }

    location /language/ {
    deny all;
    }

    location /install/ {
    deny all;
    }

    location /.git/ {
    deny all;
    }

    location ~ /external/ {
    deny all;
    }
```