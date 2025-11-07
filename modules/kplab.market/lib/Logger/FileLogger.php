<?php
namespace KPLab\Market\Logger;

use Bitrix\Main\Application;

class FileLogger implements LoggerInterface
{
    private string $basePath;

    public function __construct(string $basePath = '/upload/kplab_market/logs/')
    {
        $this->basePath = Application::getDocumentRoot() . $basePath;
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $domain = $context['domain'] ?? 'unknown';
        $member = $context['member_id'] ?? 'nomember';
        $path = $this->basePath . date('Y-m-d') . '/' . $domain . '_' . $member . '/';

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $logEntry = [
            'timestamp' => date('c'),
            'level'     => $level,
            'message' => $message,
            'context' => $context,
            'request_id' => $context['request_id'] ?? uniqid(),
        ];

        file_put_contents($path . time() . '_' . $level . '.json', json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
    }
}