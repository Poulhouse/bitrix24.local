<?php
namespace KPLab\API\V2\Infrastructure\Logger;

use KPLab\API\V2\LogsAction;

class DbHttpLogger  // имя любое
{
    public function __construct(private bool $dev = false) {}

    /**
     * Обертка над старым LogsAction::Request
     */
    public function log(
        array   $objectData,
        string  $methodName,
        string  $url,
        string  $controllerName,
        string  $httpMethod,
        string     $status,
        array   $response,      // ['response' => string, ...]
        array   $timeData,
        ?string $requestBody,
        string  $requestHeaders,
        ?int    $taskId,
        int     $requestTypeId,
        bool    $outRequest,
        string  $partnerName
    ): void
    {
        LogsAction::Request(
            $objectData,
            $methodName,
            $url,
            $controllerName,
            $httpMethod,
            $status,
            $response,
            $timeData,
            $requestBody,
            $requestHeaders,
            $taskId,
            $requestTypeId,
            $outRequest,
            $partnerName,
            $this->dev
        );
    }
}
