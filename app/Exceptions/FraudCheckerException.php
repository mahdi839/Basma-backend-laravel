<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FraudCheckerException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->getMessage(),
        ], $this->httpStatus);
    }
}
