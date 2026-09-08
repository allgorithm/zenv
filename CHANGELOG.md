# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Default Nginx host port changed from `8080` to `80` for direct `http://localhost` access.
- Added Vite HMR Docker configuration (`host: '0.0.0.0'`, `hmr.host: 'localhost'`) to `vite.config.js`.

## [1.0.0] - 2026-09-05

### Added

#### Container Architecture

- **PHP 8.4-FPM Application Container** (`zenv-app`)
  with Composer, Node.js 22 LTS, NPM, and PHP CodeSniffer pre-installed.
- **Nginx Reverse Proxy** (`zenv-web`) with optimized `fastcgi_pass`
  configuration and Laravel-friendly `try_files` routing.
- **Redis** (`zenv-redis`) for cache, sessions, and queue driver.
- **Mailpit** (`zenv-mailpit`) as local SMTP sandbox with web UI (port 8025).
- **MinIO** (`zenv-minio`) as local S3-compatible object storage with
  web console (port 9001).
- **Queue Worker** (`zenv-queue`) running `artisan queue:work` with
  `--tries=3 --timeout=90` and auto-restart via `unless-stopped`.
- **Dedicated Docker network** (`zenv-network`, bridge driver) for
  inter-container communication.
- **Persistent volumes** for Redis data and MinIO storage.

#### PHP Extensions

- `pdo_mysql`, `mbstring`, `zip`, `exif`, `pcntl`, `bcmath`, `gd`
  (FreeType + JPEG), `intl`, `opcache`, `redis` (PECL).

#### UID/GID Resolution

- Dynamic user creation in Dockerfile matching host `UID`/`GID` to
  eliminate file permission conflicts across macOS, Linux, and WSL2.
- Automatic `USER_UID` and `USER_GID` export in CLI script.

#### Host Database Persistence (Zero Data Loss)

- Database connectivity via `host.docker.internal:host-gateway` to the
  developer's local MariaDB/MySQL instance.
- Container destruction never causes data loss — the database lives
  on the host system.

#### `./zenv` CLI

- `up` / `down` / `stop` / `restart` / `ps` / `logs` — container lifecycle.
- `artisan` — execute `php artisan` inside the app container.
- `composer` — execute Composer inside the app container.
- `npm` / `npx` — execute Node.js tooling inside the app container.
- `php` — execute arbitrary PHP commands.
- `pest` / `test` — run Pest PHP test suite.
- `mariadb` / `mysql` — open interactive MariaDB console on host.
- `shell` — open Bash session in the app container.
- `init` — initialize a fresh Laravel project.
- `help` — display usage information.
- Automatic Docker Compose version detection (`docker compose` vs.
  `docker-compose`).

#### Configuration

- `.env.example` with pre-configured `host.docker.internal` database host,
  Redis cache/queue/session drivers, Mailpit SMTP, and MinIO S3 credentials.
- `docker/nginx.conf` with security headers (`X-Frame-Options`,
  `X-Content-Type-Options`), hidden dotfile protection, and
  `fastcgi_hide_header X-Powered-By`.

#### Documentation

- `README.md` with Mermaid architecture diagram, service port matrix,
  3-step quickstart guide, CLI cheatsheet, and database persistence
  documentation.

### Security

- `X-Frame-Options: SAMEORIGIN` and `X-Content-Type-Options: nosniff`
  headers set by default in Nginx.
- Dotfile access (`/.`) denied globally except `.well-known`.
- `X-Powered-By` header stripped from PHP responses.
- Non-root container user for PHP-FPM process execution.

[Unreleased]: https://github.com/allgorithm/zenv/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/allgorithm/zenv/releases/tag/v1.0.0
