Nginx does not support .htaccess files, which means that by using nginx you are exposed to data leakage from your server instance.

Insert the content below into your nginx configuration file.

If you also front the site with a reverse proxy that blocks `*.txt`, add an exception so `/robots.txt` remains publicly crawlable (see `caddy.md`).

```
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