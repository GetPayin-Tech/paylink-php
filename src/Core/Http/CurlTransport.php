<?php

declare(strict_types=1);

namespace GetPayin\Paylink\Core\Http;

use GetPayin\Paylink\Core\Exceptions\PaylinkConnectionException;

/**
 * Default {@see Transport} built on ext-curl. Applies the per-attempt timeout in
 * milliseconds and captures response headers into a lower-cased map. Any cURL
 * error (including a timeout) surfaces as a {@see PaylinkConnectionException}.
 */
final class CurlTransport implements Transport
{
    /**
     * @param  array<string, string>  $headers
     *
     * @throws PaylinkConnectionException On any cURL error, including a timeout.
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutMs): Response
    {
        if ($method === '' || $url === '') {
            throw new PaylinkConnectionException('Cannot send a request without an HTTP method and URL.');
        }

        $handle = curl_init();

        if ($handle === false) {
            throw new PaylinkConnectionException("Request to {$url} failed: could not initialize cURL.");
        }

        $responseHeaders = [];
        $headerCallback = static function ($_curl, string $line) use (&$responseHeaders): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return strlen($line);
        };

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_HEADERFUNCTION => $headerCallback,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $rawBody = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $errorMessage = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($errorNumber !== 0 || $rawBody === false) {
            $isTimeout = $errorNumber === CURLE_OPERATION_TIMEOUTED;
            $reason = $isTimeout ? "timed out after {$timeoutMs}ms" : "failed: {$errorMessage}";

            throw new PaylinkConnectionException("Request to {$url} {$reason}.");
        }

        return new Response($status, (string) $rawBody, $responseHeaders);
    }
}
