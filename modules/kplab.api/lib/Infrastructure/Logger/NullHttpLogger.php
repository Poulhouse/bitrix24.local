<?php
namespace KPLab\API\V2\Infrastructure\Logger;
class NullHttpLogger extends DbHttpLogger
{
    public function __construct(bool $dev = false)
    {
        parent::__construct($dev);
    }
    public function log(
        array   $objectData,
        string  $methodName,
        string  $url,
        string  $controllerName,
        string  $httpMethod,
        int     $status,
        array   $response,      // ['response' => string, ...]
        array   $timeData,
        ?string $requestBody,
        array   $requestHeaders,
        bool    $outRequest,
        ?int    $taskId,
        int     $requestTypeId
    ): void {/* ничего */}
}