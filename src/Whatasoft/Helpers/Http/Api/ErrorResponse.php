<?php

namespace Whatasoft\Helpers\Http\Api;

class ErrorResponse
{
  public function __construct(string $message = '', array $data = [])
  {
    parent::__construct(false, $message, $data)
  }
}