# 🚀 Deploying NAAP Routing to Coolify

This project is fully dockerized and configured for high-performance production deployment on **[Coolify](https://coolify.io)**.

---

## 📋 Prerequisites
- A working Coolify instance.
- This repository pushed to your Git provider (GitHub, GitLab, or Git self-hosted).

---

## 🛠️ Method 1: Deploy with Docker Compose (Recommended - All-in-One)

This method deploys both the **Laravel App** and a dedicated **MySQL 8.0** database container together.

### Step 1: Create Application in Coolify
1. In Coolify dashboard, navigate to your **Project** > **Environment**.
2. Click **+ New Resource** > **Docker Compose**.
3. Choose your Git repository and branch (or select **Docker Compose Raw** and paste `docker-compose.yml`).

### Step 2: Configure Environment Variables
In the **Environment Variables** tab in Coolify, copy and paste the values from [.env.example](file:///.env.example):
- `APP_KEY`: *(Generate one with `php artisan key:generate --show` or use the one generated in `.env`)*
- `APP_URL`: Set to your production domain (e.g. `https://routing.yourdomain.com`).
- `APP_ENV`: `production`
- `APP_DEBUG`: `false`
- `DB_CONNECTION`: `mysql`
- `DB_HOST`: `mysql`
- `DB_DATABASE`: `naap_routing`
- `DB_USERNAME`: `naap_user`
- `DB_PASSWORD`: *(Set a strong secret password)*
- `DB_ROOT_PASSWORD`: *(Set a strong root password)*
- `RUN_MIGRATIONS`: `true` *(Automatically runs `php artisan migrate --force` on startup)*
- `RUN_SEEDER`: `true` *(Set to `true` on the very first run to seed default offices and admin user, then change back to `false`)*

### Step 3: Domain & Ports
- Under **Domains**, set your FQDN: e.g. `https://routing.yourdomain.com`.
- Route traffic to port `80` (Coolify Traefik handles SSL automatically).

### Step 4: Deploy
Click **Deploy**! Coolify will build the image, start MySQL, wait for healthcheck, run migrations, and serve the application.

---

## 🛠️ Method 2: Deploy with Dockerfile + Coolify Managed Database

If you prefer using Coolify's built-in managed MySQL database resource:

### Step 1: Create Database in Coolify
1. Click **+ New Resource** > **Databases** > **MySQL**.
2. Note the internal hostname, database name, username, and password.

### Step 2: Create Application
1. Click **+ New Resource** > **Public / Private Repository**.
2. Select your repository.
3. Choose **Build Pack**: **Dockerfile**.

### Step 3: Environment Variables
Set:
- `APP_KEY`: Your generated base64 key
- `APP_URL`: `https://routing.yourdomain.com`
- `APP_ENV`: `production`
- `APP_DEBUG`: `false`
- `DB_CONNECTION`: `mysql`
- `DB_HOST`: *(Coolify internal database host, e.g. `<uuid>:3306` or container name)*
- `DB_PORT`: `3306`
- `DB_DATABASE`: Your database name
- `DB_USERNAME`: Your database user
- `DB_PASSWORD`: Your database password
- `RUN_MIGRATIONS`: `true`
- `RUN_SEEDER`: `true` *(on first deploy)*

### Step 4: Persistent Storage in Coolify
In the **Storages** tab of your application in Coolify, add a persistent volume:
- **Volume Name**: `storage_data`
- **Destination Path**: `/var/www/html/storage`

This ensures uploaded document signatures, QR codes, and files survive container restarts and deployments.

---

## 🔑 Default Seeded Admin Credentials

When `RUN_SEEDER=true` runs:
- **Email**: `admin@naap.org`
- **Username**: `admin`
- **Password**: `password`

*(Please change this password immediately in Settings after first login!)*

---

## 🔍 Handy Artisan Commands inside Container

To run artisan commands directly in Coolify:
Open the container's **Terminal** in Coolify UI:
```bash
# Run migrations manually
php artisan migrate --force

# Seed database
php artisan db:seed --force

# Clear cache if updating config
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
