# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Enterprise GitHub Actions CI Pipeline**: Comprehensive CI/CD workflow (`.github/workflows/ci.yml`) featuring static analysis (ShellCheck, Bash syntax, Hadolint, Docker Compose validation, PHP syntax), security audit (TruffleHog secret scanning, file permissions), Docker layer caching (`type=gha`), and full-stack end-to-end integration smoke testing (HTTP 200 checks, Redis ping, PHP 8.4 runtime extension verification, and graceful teardown).
- **Standalone Environment Hub & Status Dashboard**: Added `public/index.php` to provide immediate feedback on `http://localhost/` when running `zenv` in standalone or package mode, featuring live healthchecks (PHP-FPM, Nginx, Redis, Mailpit, MinIO, MariaDB host gateway, and Queue status) and a 1-click CLI reference.
- **Enterprise `.gitignore` Configuration**: Comprehensive 8-section rule set covering strict zero-leak secret protection (`.env*`, private keys, certificates), granular Laravel 13 storage preservation, frontend/Vite artifacts, testing & profiling caches (PHPUnit, Pest, PHPStan, Infection), local database dumps/SQLite, and IDE/OS cleanup.
- **Package-Mode Support for Queue Worker**: The `zenv-queue` container dynamically detects whether an `artisan` CLI exists at the project root. When running in Composer package or plugin development contexts, the container logs an informative idle notice and sleeps quietly instead of crashing.

### Changed

- Enhanced `./zenv` CLI wrapper with dynamic TTY detection (`-t` only if interactive terminal is allocated) for smooth execution in non-interactive CI/CD environments and shell pipelines.
- Updated framework compatibility and alignment to **Laravel 13** & PHP 8.4+.
- Default Nginx host port changed from `8080` to `80` for direct `http://localhost` access.
- Added Vite HMR Docker configuration (`host: '0.0.0.0'`, `hmr.host: 'localhost'`) to `vite.config.js`.
- Configured Nginx FastCGI buffer sizes (`fastcgi_buffer_size 128k; fastcgi_buffers 4 256k; fastcgi_busy_buffers_size 256k;`) to prevent HTTP 502 "upstream sent too big header" errors with heavy Filament / Livewire payloads.

### Fixed

- Resolved `zenv-queue` container crash and restart loop (`Restarting (1)`) caused by `Could not open input file: artisan` in repositories without a full Laravel skeleton.

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
