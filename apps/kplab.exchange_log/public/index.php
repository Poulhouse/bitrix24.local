<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/ChangeTracker.php';

use Kplab\ExchangeLog\ChangeTracker;

// Инициализируем объект для отслеживания изменений
$tracker = new ChangeTracker();

// Здесь можно загрузить данные для отображения (например, последние записи изменений)
// В данном примере мы будем читать последние 5 записей из файла логов (для демонстрации)
$logFile = __DIR__ . '/../logs/changes.log';
$recentLogs = [];

if (file_exists($logFile)) {
    $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $recentLogs = array_slice(array_reverse($logs), 0, 5);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель управления отслеживанием изменений</title>
    <link rel="stylesheet" href="/local/apps/kplab.exchange_log/assets/css/style.css">
    <style>
        /* Пример небольших стилей для панельного интерфейса */
        header, nav, main, footer {
            margin: 0 auto;
            max-width: 800px;
            padding: 10px;
        }
        nav ul {
            list-style-type: none;
            padding: 0;
            display: flex;
            gap: 15px;
        }
        nav li {
            display: inline;
        }
        nav a {
            text-decoration: none;
            color: #0077cc;
        }
        nav a:hover {
            text-decoration: underline;
        }
        h1 {
            text-align: center;
        }
        .log-list {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
        }
    </style>
</head>
<body>
<header>
    <h1>Панель управления отслеживанием изменений</h1>
    <nav>
        <ul>
            <li><a href="index.php">Главная</a></li>
            <li><a href="settings.php">Настройки</a></li>
            <!-- Здесь можно добавить дополнительные ссылки, например, "Отчеты", "Журнал", "Помощь" и т.д. -->
        </ul>
    </nav>
</header>
<main>
    <section>
        <h2>Обзор состояния</h2>
        <p>В этом разделе можно вывести общую статистику по изменяемым объектам или другую полезную информацию.</p>
    </section>
    <section>
        <h2>Последние изменения</h2>
        <div class="log-list">
            <?php if (!empty($recentLogs)): ?>
                <ul>
                    <?php foreach ($recentLogs as $logEntry): ?>
                        <li><?= htmlspecialchars($logEntry) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Записей изменений пока нет.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<footer>
    <p>&copy; <?= date("Y") ?> Kplab Exchange Log</p>
</footer>
</body>
</html>
