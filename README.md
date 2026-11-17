# TMail Dockerized (TMail 8.0.4 + Groot Theme)

Dockerized setup for **TMail - Multi-Domain Temporary Email System** (version 8.0.4) with pre-configured PHP 8.3-FPM, Nginx, MariaDB, Cron scheduler, and bundled themes (Default, Mantis, Nebula, and **Groot**).

---

## 🚀 Features

- **PHP 8.3 FPM & Nginx**: Optimized Alpine-based single container managed by Supervisord.
- **Built-in SMTP Receiver (Port 25)**: Dedicated Python async SMTP service (`aiosmtpd`) listening on port 25, automatically receiving incoming emails and pushing them directly into TMail database via Delivery API.
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
├── docker-compose.yml           # App + MariaDB + Port 25 SMTP Receiver Compose
├── .env.example                 # Environment variables template
├── export-mails.sh              # Script xuất toàn bộ lịch sử email nhận
├── scripts/
│   └── export_mails.py          # Bộ xử lý xuất mail (JSON, CSV Excel, HTML, ZIP)
├── exports/                     # Thư mục lưu trữ kết quả xuất email
├── docker/
│   ├── nginx/                   # Nginx configuration (gzip, client_max_body_size, fastcgi)
│   ├── php/                     # PHP ini & php-fpm pool config
│   ├── supervisor/              # Supervisord configuration
│   ├── cron/                    # Crontab configuration
│   ├── smtp/                    # Custom Python async SMTP receiver daemon (Port 25)
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
- `ADMIN_EMAIL`: Admin email address (e.g. `anhptt@softlab.vn`)
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

## 📤 Xuất Lịch Sử Email Nhận (Email History Export)

Hệ thống tích hợp sẵn script [export-mails.sh](./export-mails.sh) giúp trích xuất toàn bộ lịch sử email nhận được từ database ra nhiều định dạng tiện ích: **CSV (Excel UTF-8 BOM)**, **JSON**, **HTML Archive (Web tra cứu offline)**, và **ZIP**.

### 1. Xuất mặc định toàn bộ (All formats + ZIP)

```bash
./export-mails.sh
```

Kết quả sẽ được tự động tạo và lưu trữ tại thư mục:
`exports/export_YYYYMMDD_HHMMSS/`

Bao gồm:
- **`emails.csv`**: File bảng tính định dạng **UTF-8 with BOM**, mở trực tiếp bằng Microsoft Excel hoặc Google Sheets mà **không bị lỗi font** tiếng Việt, tiếng Nhật, tiếng Trung hay biểu tượng emoji.
- **`emails.json`**: Cấu trúc JSON đầy đủ 100% dữ liệu (ID, ngày nhận, người gửi, người nhận, tiêu đề, mã HTML body, attachments).
- **`html/index.html`**: **Giao diện Web tra cứu Offline** tích hợp thanh tìm kiếm nhanh; nhấp vào từng thư để mở xem nguyên bản giao diện email.
- **`summary.txt`**: Báo cáo tóm tắt: tổng số email, top 10 người nhận, top 10 người gửi.
- **`emails_*.zip`**: Tệp nén trọn gói tự động đóng gói để dễ dàng tải về máy hoặc backup.

### 2. Xuất theo định dạng cụ thể

```bash
# Chỉ xuất file CSV (để mở bằng Excel / Google Sheets)
./export-mails.sh -f csv

# Chỉ xuất file JSON (để backup dữ liệu hoặc lập trình xử lý tiếp)
./export-mails.sh -f json

# Chỉ xuất bộ HTML tra cứu offline
./export-mails.sh -f html
```

### 3. Bộ lọc nâng cao (Filters)

```bash
# Lọc theo hòm thư nhận cụ thể (To)
./export-mails.sh --to "user@imail.edu.vn"

# Lọc theo địa chỉ hoặc tên miền người gửi (From)
./export-mails.sh --from "tiktok.com"

# Lọc email nhận trong N ngày gần nhất (ví dụ 7 ngày)
./export-mails.sh --days 7

# Lọc theo khoảng thời gian cụ thể (Since / Until)
./export-mails.sh --since "2026-11-01" --until "2026-11-17 23:59:59"

# Tìm kiếm từ khóa bất kỳ trong tiêu đề, người gửi, người nhận hoặc nội dung
./export-mails.sh --search "mã xác nhận"

# Giới hạn số lượng email cần xuất (ví dụ 100 email mới nhất)
./export-mails.sh --limit 100

# Tùy chỉnh thư mục lưu kết quả
./export-mails.sh -o /root/backup_mails

# Nén file zip cho định dạng lẻ
./export-mails.sh -f csv --zip
```

Xem danh sách đầy đủ các tùy chọn:
```bash
./export-mails.sh --help
```

---

## 🏢 Đơn Vị Duy Trì & Vận Hành

**CÔNG TY TNHH GIẢI PHÁP SỐ SOFTLAB**
- **Mã số thuế (MST):** `3604049476`
- **Địa chỉ:** 59 Đặng Đại Độ, Phường Trấn Biên, Thành phố Đồng Nai
- **Hotline:** 0845656869 - 0888866548
- **Email:** contact@softlab.vn
- **Website:** [softlab.vn](https://softlab.vn)
- **GitHub:** [https://github.com/SoftLabVN/tmail-dockerized](https://github.com/SoftLabVN/tmail-dockerized)

### ⚠️ Tuyên Bố Từ Chối Trách Nhiệm (Disclaimer)
Hệ thống **IMAIL (imail.edu.vn)** là dịch vụ thư điện tử tạm thời miễn phí phục vụ mục đích học tập, nghiên cứu và phòng chống thư rác hợp pháp.
- **Nghiêm cấm tuyệt đối** mọi hành vi lợi dụng hòm thư để lừa đảo, phát tán mã độc, spam, tấn công an ninh mạng hoặc vi phạm pháp luật nước CHXHCN Việt Nam.
- Đơn vị vận hành hoàn toàn không kiểm duyệt trước nội dung và miễn trừ toàn bộ trách nhiệm dân sự/hình sự đối với các hành vi vi phạm của người dùng. Người dùng phải tự chịu 100% trách nhiệm trước pháp luật.
- Đơn vị vận hành cam kết phối hợp và cung cấp đầy đủ nhật ký kỹ thuật cho các Cơ quan An ninh mạng, Cơ quan Công an có thẩm quyền khi có yêu cầu bằng văn bản.
- Xem toàn văn chính sách: [https://imail.edu.vn/dieu-khoan-va-tu-choi-trach-nhiem](https://imail.edu.vn/dieu-khoan-va-tu-choi-trach-nhiem).

---

## 📜 License

Packaged, maintained and operated by [SoftLabVN](https://github.com/SoftLabVN).
