<?php
namespace KPLab\API\V2;

use KPLab\API\V2\Infrastructure\Http\BitrixHttpClientAdapter;
use KPLab\API\V2\Infrastructure\Http\LoggingHttpClientDecorator;
use KPLab\API\V2\Infrastructure\Http\HttpClientDecorator;
use KPLab\API\V2\Infrastructure\Logger\DbHttpLogger;
use KPLab\API\V2\Infrastructure\Logger\NullHttpLogger;
use KPLab\API\V2\Interfaces\Http\HttpClientInterface;
use KPLab\API\V2\Infrastructure\Http\Auth\AuthHttpClientDecorator;

final class HttpClientFactory
{
    public static function build(
        HttpClientInterface $baseClient,
        bool  $logging  = true,
        bool  $emulate  = false,
        bool  $devMode  = false,
        array $options  = []
    ): HttpClientInterface {
        if($logging) {
            return new LoggingHttpClientDecorator(
                $baseClient,
                new DbHttpLogger($devMode),
                $emulate
            );
        }
        else {
            return new HttpClientDecorator(
                $baseClient,
                $emulate
            );
        }
    }
}
