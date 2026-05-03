# Triển khai Homecam (Laravel + WebRTC polling)

Hướng dẫn deploy ứng dụng Laravel có signaling WebRTC qua HTTP polling và cache file. **Không cần Node.js, npm, WebSocket hay queue worker.**

## 1. Chuẩn bị Ubuntu 22.04

Cập nhật gói và cài PHP 8.2, extension cần cho Laravel, Composer và Nginx:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx software-properties-common unzip curl
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mbstring php8.2-xml php8.2-curl \
  php8.2-zip php8.2-bcmath php8.2-intl php8.2-readline
```

Cài Composer (bản cài đặt toàn cục):

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

## 2. Đặt mã nguồn và quyền thư mục

```bash
sudo mkdir -p /var/www/homecam
sudo chown -R $USER:www-data /var/www/homecam
```

Sao chép dự án vào `/var/www/homecam`, sau đó:

```bash
cd /var/www/homecam
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Trong file `.env`:

- `APP_URL=https://your-domain.example` (đúng domain thật, HTTPS)
- `CACHE_STORE=file` (Laravel 11: store mặc định dùng file cho signaling; tránh `database` vì yêu cầu không dùng DB)
- Cấu hình `APP_DEBUG=false` trên production

Tạo thư mục cache có quyền ghi:

```bash
sudo chown -R www-data:www-data /var/www/homecam/storage /var/www/homecam/bootstrap/cache
sudo chmod -R ug+rwx /var/www/homecam/storage /var/www/homecam/bootstrap/cache
```

## 3. Nginx + PHP-FPM

Ví dụ site `/etc/nginx/sites-available/homecam`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.example;
    root /var/www/homecam/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Kích hoạt site và kiểm tra cấu hình:

```bash
sudo ln -s /etc/nginx/sites-available/homecam /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl enable --now php8.2-fpm
```

## 4. SSL với Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.example
```

Certbot sẽ chỉnh Nginx để redirect HTTP → HTTPS. Đảm bảo `APP_URL` trong `.env` dùng `https://`.

## 5. Tối ưu cấu hình Laravel (production)

```bash
cd /var/www/homecam
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Sau khi đổi `.env` hoặc routes, chạy lại các lệnh cache tương ứng (`config:clear` / `route:clear` rồi cache lại nếu cần).

## 6. Ghi chú vận hành

- Signaling dùng **file cache** (`storage/framework/cache/data`). Một máy chủ đơn là đủ; nếu scale nhiều máy cần backend cache tập trung (Redis, v.v.) — ngoài phạm vi thiết kế hiện tại.
- WebRTC cần **HTTPS** (hoặc localhost) để `getUserMedia` hoạt động trên trình duyệt.
- Không cần cài đặt hay build **Node.js / npm** cho dự án này.

## 7. Kiểm tra nhanh

- Mở `https://your-domain.example/camera` trên thiết bị có camera.
- Mở `https://your-domain.example/viewer` trên thiết bị khác (hoặc quét QR trên trang camera).
- Xác nhận video hiển thị sau vài giây (tùy mạng và NAT).
