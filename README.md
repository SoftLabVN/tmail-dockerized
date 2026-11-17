# TMail Dockerized (TMail 8.0.4 + Groot Theme)

Dockerized setup for **TMail - Multi-Domain Temporary Email System** (version 8.0.4) with pre-configured PHP 8.3-FPM, Nginx, MariaDB, Cron scheduler, and bundled themes (Default, Mantis, Nebula, and **Groot**).

---

## 🚀 Features

- **PHP 8.3 FPM & Nginx**: Optimized Alpine-based single container managed by Supervisord.
- **Full PHP Extensions Included**: `pdo_mysql`, `mysqli`, `imap`, `bcmath`, `mbstring`, `zip`, `xml`, `fileinfo`, `ctype`, `json`, `tokenizer`, `openssl`, `iconv`, `gd`, `opcache`.
- **Database**: MariaDB 10.11 LTS with automatic healthcheck and volume persistence.
- **Themes Included**:
  - Default
  - Mantis
  - Nebula
  - **Groot** (pre-installed in `resources/views/frontend/themes/groot`)
- **Automated Setup / Zero-touch Deployment**:
  - Automatically runs migrations, seeds, sets initial domain, admin account, and license bypass via `AUTO_INSTALL=true`.
  - Alternatively, supports standard web installer at `/installer` if `AUTO_INSTALL=false`.
- **Background Cron**: Integrated crond running Laravel scheduler and TMail cleanup tasks.

---

## 📁 Project Structure

```text
├── Dockerfile                   # PHP 8.3 + Nginx + Supervisor + Crond
├── docker-compose.yml           # App + MariaDB Compose configuration
├── .env.example                 # Environment variables template
├── docker/
│   ├── nginx/                   # Nginx configuration (gzip, client_max_body_size, fastcgi)
│   ├── php/                     # PHP ini & php-fpm pool config
│   ├── supervisor/              # Supervisord configuration
│   ├── cron/                    # Crontab configuration
│   ├── docker-entrypoint.sh     # Container initialization & permissions
│   └── auto-setup.php           # Post-install auto-configuration script
├── resources/views/frontend/
│   └── themes/                  # Themes: default, groot, mantis, nebula
└── app/, config/, database/, ... # TMail 8.0.4 application source
```

---

## ⚡ Quick Start

### 1. Clone & Configure

```bash
git clone https://github.com/SoftLabVN/tmail-dockerized.git
cd tmail-dockerized
cp .env.example .env
```

Review and adjust `.env` parameters if needed:
- `APP_URL`: e.g. `https://imail.edu.vn`
- `APP_PORT`: Host port (default: `8090`)
- `DEFAULT_DOMAIN`: Primary email domain (e.g. `imail.edu.vn`)
- `ADMIN_EMAIL`: Admin email address (e.g. `petertuananh@hotmail.com`)
- `ADMIN_PASSWORD`: Admin password
- `AUTO_INSTALL`: Set to `true` for instant setup or `false` for manual web installer at `/installer`.

### 2. Start Containers

```bash
docker compose up -d --build
```

View container logs:
```bash
docker compose logs -f app
```

---

## 🌐 Host Nginx Reverse Proxy Setup (for imail.edu.vn)

Create `/etc/nginx/sites-available/imail.edu.vn`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name imail.edu.vn www.imail.edu.vn;

    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:8090;
        proxy_http_version 1.1;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        proxy_connect_timeout 60s;
        proxy_send_timeout 300s;
        proxy_read_timeout 300s;
    }
}
```

Enable site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/imail.edu.vn /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Obtain free SSL certificate via Certbot:
```bash
sudo certbot --nginx -d imail.edu.vn -d www.imail.edu.vn
```

---

## ⚙️ Administration & Useful Commands

- **Access Admin Panel**: `https://imail.edu.vn/admin`
- **Clear Caches**:
  ```bash
  docker compose exec app php artisan optimize:clear
  ```
- **Run Artisan Commands**:
  ```bash
  docker compose exec app php artisan <command>
  ```
- **View Container Status**:
  ```bash
  docker compose ps
  ```

---

## 📧 Mail Engine Configuration

In the Admin Panel (`/admin/settings`):
- **IMAP Catch-All**: Configure host, port (993), SSL/TLS, and credentials to fetch emails from a catch-all mailbox.
- **Delivery Engine**: Configure webhook/API incoming emails via `/api/delivery/...`.

---

## 📜 License

Packaged and dockerized by [SoftLabVN](https://github.com/SoftLabVN).
