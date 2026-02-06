# BugRadar Deployment Guide

## Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL 8.0 or higher
- Node.js 18+ and npm (for frontend assets)
- Redis (optional, for queue management)
- Web server (Apache/Nginx)

---

## Local Development Setup

### 1. Clone Repository
```bash
git clone https://github.com/yourusername/bugradar.git
cd bugradar
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### 3. Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure Database
Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bugradar
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create database:
```bash
mysql -u root -p
CREATE DATABASE bugradar;
exit;
```

### 5. Run Migrations
```bash
php artisan migrate
```

### 6. Configure OAuth Providers

#### Google OAuth
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable Google+ API
4. Create OAuth 2.0 credentials
5. Add authorized redirect URI: `http://localhost:8006/api/auth/google/callback`
6. Update `.env`:
```env
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8006/api/auth/google/callback
```

#### GitHub OAuth (Authentication)
1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Create new OAuth App
3. Set Authorization callback URL: `http://localhost:8006/api/auth/github/callback`
4. Update `.env`:
```env
GITHUB_CLIENT_ID=your_client_id
GITHUB_CLIENT_SECRET=your_client_secret
GITHUB_REDIRECT_URI=http://localhost:8006/api/auth/github/callback
```

#### GitHub OAuth (Integration)
Create a separate OAuth app for integration:
```env
GITHUB_INTEGRATION_CLIENT_ID=your_integration_client_id
GITHUB_INTEGRATION_CLIENT_SECRET=your_integration_client_secret
GITHUB_INTEGRATION_REDIRECT_URI=http://localhost:8006/api/integrations/github/callback
```

#### GitLab OAuth
1. Go to [GitLab Applications](https://gitlab.com/-/profile/applications)
2. Create new application
3. Set Redirect URI: `http://localhost:8006/api/integrations/gitlab/callback`
4. Select scopes: `read_user`, `read_api`, `read_repository`
5. Update `.env`:
```env
GITLAB_CLIENT_ID=your_client_id
GITLAB_CLIENT_SECRET=your_client_secret
GITLAB_REDIRECT_URI=http://localhost:8006/api/integrations/gitlab/callback
```

#### Bitbucket OAuth
1. Go to [Bitbucket OAuth](https://bitbucket.org/account/settings/app-passwords/)
2. Create OAuth consumer
3. Set Callback URL: `http://localhost:8006/api/integrations/bitbucket/callback`
4. Select permissions: Repository (Read), Pull requests (Read), Issues (Read)
5. Update `.env`:
```env
BITBUCKET_CLIENT_ID=your_client_id
BITBUCKET_CLIENT_SECRET=your_client_secret
BITBUCKET_REDIRECT_URI=http://localhost:8006/api/integrations/bitbucket/callback
```

### 7. Start Development Server
```bash
# Start Laravel server
php artisan serve

# In another terminal, start queue worker
php artisan queue:work

# In another terminal, compile assets
npm run dev
```

Access the application at `http://localhost:8006`

---

## Production Deployment

### Option 1: Traditional Server (VPS)

#### 1. Server Requirements
- Ubuntu 22.04 LTS (recommended)
- PHP 8.2 with extensions: mbstring, xml, bcmath, pdo_mysql, curl, gd
- MySQL 8.0
- Nginx
- Redis
- Supervisor (for queue workers)
- SSL certificate (Let's Encrypt)

#### 2. Install Dependencies
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP and extensions
sudo apt install php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-bcmath php8.2-curl php8.2-gd php8.2-redis -y

# Install MySQL
sudo apt install mysql-server -y

# Install Redis
sudo apt install redis-server -y

# Install Nginx
sudo apt install nginx -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install nodejs -y
```

#### 3. Setup Application
```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/yourusername/bugradar.git
cd bugradar

# Set permissions
sudo chown -R www-data:www-data /var/www/bugradar
sudo chmod -R 755 /var/www/bugradar/storage
sudo chmod -R 755 /var/www/bugradar/bootstrap/cache

# Install dependencies
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

#### 4. Configure Environment
```bash
# Copy and edit environment file
cp .env.example .env
nano .env
```

Update production values:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bugradar
DB_USERNAME=bugradar_user
DB_PASSWORD=secure_password

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Update OAuth redirect URIs to production domain
GOOGLE_REDIRECT_URI=https://yourdomain.com/api/auth/google/callback
GITHUB_REDIRECT_URI=https://yourdomain.com/api/auth/github/callback
# ... etc
```

```bash
# Generate key and run migrations
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### 5. Configure Nginx
```bash
sudo nano /etc/nginx/sites-available/bugradar
```

Add configuration:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/bugradar/public;

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
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/bugradar /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

#### 6. Setup SSL with Let's Encrypt
```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d yourdomain.com
```

#### 7. Configure Queue Worker with Supervisor
```bash
sudo nano /etc/supervisor/conf.d/bugradar-worker.conf
```

Add configuration:
```ini
[program:bugradar-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bugradar/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/bugradar/storage/logs/worker.log
stopwaitsecs=3600
```

Start supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bugradar-worker:*
```

#### 8. Setup Cron for Scheduled Tasks
```bash
sudo crontab -e -u www-data
```

Add:
```
* * * * * cd /var/www/bugradar && php artisan schedule:run >> /dev/null 2>&1
```

---

### Option 2: Docker Deployment

#### 1. Create Dockerfile
```dockerfile
FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . /var/www

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www

EXPOSE 9000
CMD ["php-fpm"]
```

#### 2. Create docker-compose.yml
```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: bugradar-app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./:/var/www
    networks:
      - bugradar

  nginx:
    image: nginx:alpine
    container_name: bugradar-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www
      - ./docker/nginx:/etc/nginx/conf.d
    networks:
      - bugradar

  mysql:
    image: mysql:8.0
    container_name: bugradar-mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: bugradar
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_USER: bugradar_user
      MYSQL_PASSWORD: user_password
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - bugradar

  redis:
    image: redis:alpine
    container_name: bugradar-redis
    restart: unless-stopped
    networks:
      - bugradar

networks:
  bugradar:
    driver: bridge

volumes:
  mysql_data:
```

#### 3. Deploy with Docker
```bash
docker-compose up -d
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan config:cache
```

---

### Option 3: Cloud Platforms

#### Laravel Forge
1. Connect your server to Forge
2. Create new site
3. Deploy repository
4. Configure environment variables
5. Enable queue worker
6. Setup SSL certificate

#### Laravel Vapor (Serverless)
1. Install Vapor CLI: `composer require laravel/vapor-cli`
2. Configure `vapor.yml`
3. Deploy: `vapor deploy production`

#### DigitalOcean App Platform
1. Connect GitHub repository
2. Configure build settings
3. Add environment variables
4. Deploy

---

## Post-Deployment Checklist

- [ ] Update OAuth redirect URIs in provider settings
- [ ] Configure CORS settings if needed
- [ ] Setup monitoring (e.g., Laravel Telescope, Sentry)
- [ ] Configure backups for database
- [ ] Setup log rotation
- [ ] Test all OAuth flows
- [ ] Test sync jobs
- [ ] Configure firewall rules
- [ ] Setup CDN for assets (optional)
- [ ] Configure email service (if needed)

---

## Maintenance

### Update Application
```bash
cd /var/www/bugradar
git pull origin main
composer install --optimize-autoloader --no-dev
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart bugradar-worker:*
```

### Monitor Queue
```bash
php artisan queue:monitor
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### View Logs
```bash
tail -f storage/logs/laravel.log
```

---

## Troubleshooting

### Queue not processing
```bash
sudo supervisorctl status
sudo supervisorctl restart bugradar-worker:*
```

### Permission issues
```bash
sudo chown -R www-data:www-data /var/www/bugradar
sudo chmod -R 755 /var/www/bugradar/storage
```

### OAuth not working
- Check redirect URIs match exactly
- Verify credentials in `.env`
- Check SSL certificate is valid
- Review application logs

---

## Security Best Practices

1. Keep PHP and dependencies updated
2. Use strong database passwords
3. Enable firewall (UFW)
4. Disable directory listing
5. Use HTTPS only
6. Implement rate limiting
7. Regular security audits
8. Monitor logs for suspicious activity
9. Keep OAuth credentials secure
10. Regular backups
