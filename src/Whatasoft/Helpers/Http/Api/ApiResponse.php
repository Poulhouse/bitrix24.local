<?php

namespace Whatasoft\Helpers\Http\Api;

class ApiResponse
{
  private $success;
  private $message;
  private $data;
  
  public function __construct(bool $success = true, string $message = '', array $data = [])
  {
    $this->success = $success;
    $this->message = $message;
    $this->data = $data;
  }
  
  public function asArray()
  {
    return array_merge([
      'success' => $this->success,
      'message' => $this->message,
    ], $this->data);
  }
}