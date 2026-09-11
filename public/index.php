<?php
declare(strict_types=1);

// Helper function to check socket availability
function checkSocket(string $host, int $port, float $timeout = 0.5): array {
    $start = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $latency = round((microtime(true) - $start) * 1000, 1);
    if ($fp) {
        fclose($fp);
        return ['status' => true, 'latency' => $latency, 'message' => "Erreichbar ({$latency} ms)"];
    }
    return ['status' => false, 'latency' => null, 'message' => 'Nicht erreichbar'];
}

// Service Checks
$services = [];

// 1. PHP-FPM
$services['php'] = [
    'name' => 'PHP 8.4-FPM',
    'status' => true,
    'tag' => 'v' . PHP_VERSION,
    'detail' => 'SAPI: ' . php_sapi_name() . ' | Memory: ' . ini_get('memory_limit'),
    'icon' => 'php',
    'badge' => 'Aktiv',
    'type' => 'runtime'
];

// 2. Nginx
$services['nginx'] = [
    'name' => 'Nginx Webserver',
    'status' => true,
    'tag' => 'Port 80',
    'detail' => $_SERVER['SERVER_SOFTWARE'] ?? 'Nginx Alpine',
    'icon' => 'server',
    'badge' => 'Online',
    'type' => 'web'
];

// 3. Redis
$redisStatus = ['status' => false, 'message' => 'Extension fehlt'];
if (extension_loaded('redis')) {
    try {
        $redis = new Redis();
        $connected = @$redis->connect('redis', 6379, 0.5);
        if ($connected && @$redis->ping()) {
            $redisStatus = ['status' => true, 'message' => 'Verbunden (PONG) :6379'];
        } else {
            $redisStatus = ['status' => false, 'message' => 'Keine Verbindung zu redis:6379'];
        }
    } catch (\Throwable $e) {
        $redisStatus = ['status' => false, 'message' => $e->getMessage()];
    }
}
$services['redis'] = [
    'name' => 'Redis Cache / Queue',
    'status' => $redisStatus['status'],
    'tag' => 'Port 6379',
    'detail' => $redisStatus['message'],
    'icon' => 'database',
    'badge' => $redisStatus['status'] ? 'Verbunden' : 'Fehler',
    'type' => 'cache'
];

// 4. Mailpit
$mailpitHttp = checkSocket('mailpit', 8025, 0.5);
$services['mailpit'] = [
    'name' => 'Mailpit Sandbox',
    'status' => $mailpitHttp['status'],
    'tag' => 'Port 1025 / 8025',
    'detail' => $mailpitHttp['status'] ? 'Webmail UI bereit' : 'Nicht erreichbar',
    'icon' => 'mail',
    'badge' => $mailpitHttp['status'] ? 'Bereit' : 'Offline',
    'url' => 'http://localhost:8025',
    'url_label' => 'Webmail UI öffnen',
    'type' => 'tool'
];

// 5. MinIO S3
$minioHttp = checkSocket('minio', 9000, 0.5);
$services['minio'] = [
    'name' => 'MinIO S3 Storage',
    'status' => $minioHttp['status'],
    'tag' => 'Port 9000 / 9001',
    'detail' => 'User: minioadmin | Pass: minioadmin',
    'icon' => 'cloud',
    'badge' => $minioHttp['status'] ? 'Bereit' : 'Offline',
    'url' => 'http://localhost:9001',
    'url_label' => 'S3 Console öffnen',
    'type' => 'storage'
];

// 6. Queue Worker
$hasArtisan = file_exists(__DIR__ . '/../artisan');
$services['queue'] = [
    'name' => 'Queue Worker (zenv-queue)',
    'status' => true,
    'tag' => $hasArtisan ? 'App-Modus' : 'Package-Modus',
    'detail' => $hasArtisan ? 'Artisan Queue Worker aktiv' : 'Ruhezustand (keine artisan-Datei)',
    'icon' => 'cpu',
    'badge' => $hasArtisan ? 'Worker aktiv' : 'Pausiert (Idle)',
    'type' => 'worker'
];

// 7. MariaDB Host Gateway
$dbCheck = checkSocket('host.docker.internal', 3306, 0.5);
$services['mariadb'] = [
    'name' => 'MariaDB (Host-Gateway)',
    'status' => $dbCheck['status'],
    'tag' => 'host.docker.internal:3306',
    'detail' => $dbCheck['status'] ? 'Host MariaDB antwortet (' . $dbCheck['latency'] . ' ms)' : 'Host-Port 3306 nicht erreichbar',
    'icon' => 'hard-drive',
    'badge' => $dbCheck['status'] ? 'Verbunden' : 'Host Offline',
    'type' => 'db'
];
?>
<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>zenv — Zero-Config Docker Environment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #090d16;
            --bg-card: rgba(17, 24, 39, 0.7);
            --bg-card-hover: rgba(26, 36, 56, 0.85);
            --border: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(99, 102, 241, 0.4);
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.25);
            --emerald: #10b981;
            --emerald-glow: rgba(16, 185, 129, 0.2);
            --amber: #f59e0b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            background-image: 
                radial-gradient(at 15% 15%, rgba(99, 102, 241, 0.12) 0px, transparent 50%),
                radial-gradient(at 85% 85%, rgba(16, 185, 129, 0.08) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(15, 23, 42, 0.5) 0px, transparent 100%);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            padding: 2.5rem 1.5rem 4rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 1140px;
        }

        /* Header */
        header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 3rem;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(99, 102, 241, 0.12);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 9999px;
            padding: 0.35rem 1rem;
            font-size: 0.825rem;
            font-weight: 600;
            color: #818cf8;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1.25rem;
            box-shadow: 0 0 20px var(--primary-glow);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--emerald);
            box-shadow: 0 0 10px var(--emerald);
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.2); }
        }

        h1 {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 50%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.75rem;
        }

        h1 span {
            background: linear-gradient(135deg, #818cf8 0%, #c084fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p.lead {
            font-size: 1.15rem;
            color: var(--text-muted);
            max-width: 680px;
            line-height: 1.6;
        }

        /* Mode Banner */
        .info-banner {
            background: linear-gradient(90deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 4px solid var(--primary);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            backdrop-filter: blur(12px);
        }

        .info-content h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-content p {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .tag-pill {
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
        }

        /* Section Title */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .section-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        /* Cards Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.25rem;
            margin-bottom: 3rem;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.35rem;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            backdrop-filter: blur(16px);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-hover);
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .card-title-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .icon-box {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #818cf8;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #ffffff;
        }

        .card-tag {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-top: 2px;
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-badge.online {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-badge.offline {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-badge.idle {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .card-detail {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1.25rem;
            line-height: 1.5;
            font-family: 'JetBrains Mono', monospace;
        }

        .card-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            font-size: 0.825rem;
            font-weight: 600;
            text-decoration: none;
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
            transition: all 0.2s ease;
        }

        .card-action:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 15px var(--primary-glow);
        }

        /* CLI Cheatsheet */
        .cheatsheet {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.75rem;
            backdrop-filter: blur(16px);
        }

        .cmd-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .cmd-item {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.825rem;
            color: #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .cmd-item:hover {
            border-color: rgba(99, 102, 241, 0.4);
            background: rgba(99, 102, 241, 0.08);
        }

        .cmd-item .copy-hint {
            font-size: 0.7rem;
            color: var(--text-dim);
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* Footer */
        footer {
            margin-top: 3.5rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-dim);
        }

        footer a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        footer a:hover {
            color: #ffffff;
        }

        /* Toast */
        #toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #10b981;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
            z-index: 100;
        }

        #toast.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header>
            <div class="brand-badge">
                <span class="pulse-dot"></span>
                <span>zenv Docker Environment</span>
            </div>
            <h1>Zero-Config <span>Hub</span></h1>
            <p class="lead">Hochperformantes, isoliertes Docker-Entwicklungsfundament für Laravel 13 & moderne PHP 8.4+ Enterprise-Projekte.</p>
        </header>

        <!-- Information / Guidance Banner -->
        <div class="info-banner">
            <div class="info-content">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    zenv Standalone Dashboard aktiv
                </h3>
                <p>Du siehst diese Übersicht, weil <code>zenv</code> aktuell im eigenständigen Basis-Modus läuft. Sobald du eine Laravel-Applikation einhängst, wird dieser Bildschirm nahtlos durch deine App abgelöst.</p>
            </div>
            <span class="tag-pill">Document Root: /var/www/html/public</span>
        </div>

        <!-- Service Status Section -->
        <div class="section-header">
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                Integrierte Services & Status
            </h2>
            <span class="tag-pill">Docker Bridge Network</span>
        </div>

        <div class="services-grid">
            <!-- Nginx -->
            <div class="card">
                <div>
                    <div class="card-top">
                        <div class="card-title-group">
                            <div class="icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                            </div>
                            <div>
                                <div class="card-title"><?= htmlspecialchars($services['nginx']['name']) ?></div>
                                <div class="card-tag"><?= htmlspecialchars($services['nginx']['tag']) ?></div>
                            </div>
                        </div>
                        <span class="status-badge online"><span class="pulse-dot"></span><?= $services['nginx']['badge'] ?></span>
                    </div>
                    <div class="card-detail"><?= htmlspecialchars($services['nginx']['detail']) ?></div>
                </div>
                <a href="http://localhost" class="card-action">
                    http://localhost
                </a>
            </div>

            <!-- PHP 8.4-FPM -->
            <div class="card">
                <div>
                    <div class="card-top">
                        <div class="card-title-group">
                            <div class="icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 16 4-4-4-4"></path><path d="m6 8-4 4 4 4"></path><path d="m14.5 4-5 16"></path></svg>
                            </div>
                            <div>
                                <div class="card-title"><?= htmlspecialchars($services['php']['name']) ?></div>
                                <div class="card-tag"><?= htmlspecialchars($services['php']['tag']) ?></div>
                            </div>
                        </div>
                        <span class="status-badge online"><span class="pulse-dot"></span><?= $services['php']['badge'] ?></span>
                    </div>
                    <div class="card-detail"><?= htmlspecialchars($services['php']['detail']) ?></div>
                </div>
                <span class="tag-pill" style="align-self: flex-start;">OPcache & PECL Redis aktiv</span>
            </div>

            <!-- Redis -->
            <div class="card">
                <div>
                    <div class="card-top">
                        <div class="card-title-group">
                            <div class="icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                            </div>
                            <div>
                                <div class="card-title"><?= htmlspecialchars($services['redis']['name']) ?></div>
                                <div class="card-tag"><?= htmlspecialchars($services['redis']['tag']) ?></div>
                            </div>
                        </div>
                        <span class="status-badge <?= $services['redis']['status'] ? 'online' : 'offline' ?>">
                            <?php if ($services['redis']['status']): ?><span class="pulse-dot"></span><?php endif; ?>
                            <?= $services['redis']['badge'] ?>
                        </span>
                    </div>
                    <div class="card-detail"><?= htmlspecialchars($services['redis']['detail']) ?></div>
                </div>
                <span class="tag-pill" style="align-self: flex-start;">Cache & Session Store</span>
            </div>

            <!-- Mailpit -->
            <div class="card">
                <div>
                    <div class="card-top">
                        <div class="card-title-group">
                            <div class="icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg>
                            </div>
                            <div>
                                <div class="card-title"><?= htmlspecialchars($services['mailpit']['name']) ?></div>
                                <div class="card-tag"><?= htmlspecialchars($services['mailpit']['tag']) ?></div>
                            </div>
                        </div>
                        <span class="status-badge <?= $services['mailpit']['status'] ? 'online' : 'offline' ?>">
                            <?php if ($services['mailpit']['status']): ?><span class="pulse-dot"></span><?php endif; ?>
                            <?= $services['mailpit']['badge'] ?>
                        </span>
                    </div>
                    <div class="card-detail"><?= htmlspecialchars($services['mailpit']['detail']) ?></div>
                </div>
                <a href="<?= $services['mailpit']['url'] ?>" target="_blank" rel="noopener noreferrer" class="card-action">
                    <?= $services['mailpit']['url_label'] ?> &rarr;
                </a>
            </div>

            <!-- MinIO -->
            <div class="card">
                <div>
                    <div class="card-top">
                        <div class="card-title-group">
                            <div class="icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"></path></svg>
                            </div>
                            <div>
                                <div class="card-title"><?= htmlspecialchars($services['minio']['name']) ?></div>
                                <div class="card-tag"><?= htmlspecialchars($services['minio']['tag']) ?></div>
                            </div>
                        </div>
                        <span class="status-badge <?= $services['minio']['status'] ? 'online' : 'offline' ?>">
                            <?php if ($services['minio']['status']): ?><span class="pulse-dot"></span><?php endif; ?>
                            <?= $services['minio']['badge'] ?>
                        </span>
                    </div>
                    <div class="card-detail"><?= htmlspecialchars($services['minio']['detail']) ?></div>
                </div>
                <a href="<?= $services['minio']['url'] ?>" target="_blank" rel="noopener noreferrer" class="card-action">
                    <?= $services['minio']['url_label'] ?> &rarr;
                </a>
            </div>

            <!-- MariaDB Host -->
            <div class="card">
                <div>
                    <div class="card-top">
                        <div class="card-title-group">
                            <div class="icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2"></rect><rect width="20" height="8" x="2" y="14" rx="2"></rect><line x1="6" x2="6.01" y1="6" y2="6"></line><line x1="6" x2="6.01" y1="18" y2="18"></line></svg>
                            </div>
                            <div>
                                <div class="card-title"><?= htmlspecialchars($services['mariadb']['name']) ?></div>
                                <div class="card-tag"><?= htmlspecialchars($services['mariadb']['tag']) ?></div>
                            </div>
                        </div>
                        <span class="status-badge <?= $services['mariadb']['status'] ? 'online' : 'offline' ?>">
                            <?php if ($services['mariadb']['status']): ?><span class="pulse-dot"></span><?php endif; ?>
                            <?= $services['mariadb']['badge'] ?>
                        </span>
                    </div>
                    <div class="card-detail"><?= htmlspecialchars($services['mariadb']['detail']) ?></div>
                </div>
                <span class="tag-pill" style="align-self: flex-start;">Zero-Data-Loss Host DB</span>
            </div>

            <!-- Queue Worker -->
            <div class="card">
                <div>
                    <div class="card-top">
                        <div class="card-title-group">
                            <div class="icon-box">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"></path><path d="m4.93 4.93 2.83 2.83"></path><path d="M2 12h4"></path><path d="m4.93 19.07 2.83-2.83"></path><path d="M12 22v-4"></path><path d="m19.07 19.07-2.83-2.83"></path><path d="M22 12h-4"></path><path d="m19.07 4.93-2.83 2.83"></path></svg>
                            </div>
                            <div>
                                <div class="card-title"><?= htmlspecialchars($services['queue']['name']) ?></div>
                                <div class="card-tag"><?= htmlspecialchars($services['queue']['tag']) ?></div>
                            </div>
                        </div>
                        <span class="status-badge <?= $hasArtisan ? 'online' : 'idle' ?>">
                            <?= $services['queue']['badge'] ?>
                        </span>
                    </div>
                    <div class="card-detail"><?= htmlspecialchars($services['queue']['detail']) ?></div>
                </div>
                <span class="tag-pill" style="align-self: flex-start;">Auto-Detect Mode</span>
            </div>
        </div>

        <!-- CLI Cheatsheet -->
        <div class="cheatsheet">
            <div class="section-header" style="margin-bottom: 0.5rem;">
                <h2>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                    zenv CLI Quick Reference
                </h2>
                <span class="tag-pill">Klicken zum Kopieren</span>
            </div>
            <div class="cmd-grid">
                <div class="cmd-item" onclick="copyCmd('./zenv artisan migrate')">
                    <span>./zenv artisan migrate</span>
                    <span class="copy-hint">Kopieren</span>
                </div>
                <div class="cmd-item" onclick="copyCmd('./zenv composer install')">
                    <span>./zenv composer install</span>
                    <span class="copy-hint">Kopieren</span>
                </div>
                <div class="cmd-item" onclick="copyCmd('./zenv npm run dev')">
                    <span>./zenv npm run dev</span>
                    <span class="copy-hint">Kopieren</span>
                </div>
                <div class="cmd-item" onclick="copyCmd('./zenv pest')">
                    <span>./zenv pest</span>
                    <span class="copy-hint">Kopieren</span>
                </div>
                <div class="cmd-item" onclick="copyCmd('./zenv shell')">
                    <span>./zenv shell</span>
                    <span class="copy-hint">Kopieren</span>
                </div>
                <div class="cmd-item" onclick="copyCmd('./zenv logs -f')">
                    <span>./zenv logs -f</span>
                    <span class="copy-hint">Kopieren</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer>
            <p>zenv &bull; Enterprise Zero-Config Docker Environment &bull; MIT License</p>
        </footer>
    </div>

    <div id="toast">Befehl in die Zwischenablage kopiert!</div>

    <script>
        function copyCmd(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.getElementById('toast');
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 2000);
            });
        }
    </script>
</body>
</html>
