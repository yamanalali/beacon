<?php

namespace App\Http\Middleware;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Middleware\ThrottleRequests;

class ArabicThrottleRequests extends ThrottleRequests
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  callable|null  $responseCallback
     */
    protected function buildException($request, $key, $maxAttempts, $responseCallback = null)
    {
        if (is_callable($responseCallback)) {
            return parent::buildException($request, $key, $maxAttempts, $responseCallback);
        }

        $retryAfter = $this->getTimeUntilNextRetry($key);

        $headers = $this->getHeaders(
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );

        return new ThrottleRequestsException(
            'تم تجاوز عدد محاولات الدخول. حاول مرة أخرى بعد قليل.',
            null,
            $headers
        );
    }
}
