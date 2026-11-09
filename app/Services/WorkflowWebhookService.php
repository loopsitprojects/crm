<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorkflowWebhookService
{
    /**
     * Get all configured webhook URLs as an array.
     * Supports multiple comma, semicolon, or newline-separated URLs.
     *
     * @return array<string>
     */
    public function getWebhookUrls(): array
    {
        $raw = config('services.workflow.webhook_url') ?: env('WORKFLOW_WEBHOOK_URL');

        if (empty($raw) && class_exists(Setting::class)) {
            $raw = Setting::get('workflow_webhook_url');
        }

        if (empty($raw)) {
            return [];
        }

        if (is_array($raw)) {
            $candidates = $raw;
        } else {
            $candidates = preg_split('/[\s,;]+/', trim((string)$raw), -1, PREG_SPLIT_NO_EMPTY);
        }

        $urls = [];
        foreach ($candidates as $item) {
            $item = trim((string)$item);
            if (!empty($item)) {
                $urls[] = $item;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Get the configured webhook URL (primary URL for backwards compatibility).
     */
    public function getWebhookUrl(): ?string
    {
        $urls = $this->getWebhookUrls();
        return $urls[0] ?? null;
    }

    /**
     * Get the configured webhook secret for HMAC signing.
     */
    public function getWebhookSecret(): ?string
    {
        $secret = config('services.workflow.webhook_secret') ?: env('WORKFLOW_WEBHOOK_SECRET');

        if (empty($secret) && class_exists(Setting::class)) {
            $secret = Setting::get('workflow_webhook_secret');
        }

        return !empty($secret) ? trim((string)$secret) : null;
    }

    /**
     * Resolve the brand name associated with a deal.
     * Looks first at the deal's estimates, then falls back to the customer's brand.
     */
    public function getBrandName(Deal $deal): ?string
    {
        // 1. Check associated estimates for a non-empty brand_name
        if ($deal->relationLoaded('estimates')) {
            $estimate = $deal->estimates
                ->filter(fn($e) => !empty(trim((string)$e->brand_name)))
                ->sortByDesc('id')
                ->first();
        } else {
            $estimate = $deal->estimates()
                ->whereNotNull('brand_name')
                ->where('brand_name', '!=', '')
                ->latest('id')
                ->first();
        }

        if ($estimate && !empty(trim((string)$estimate->brand_name))) {
            return trim((string)$estimate->brand_name);
        }

        // 2. Fallback to Customer brand
        if ($deal->relationLoaded('customer')) {
            $customerBrand = $deal->customer?->brand;
        } else {
            $customerBrand = $deal->customer()->value('brand');
        }

        if (!empty(trim((string)$customerBrand))) {
            return trim((string)$customerBrand);
        }

        // 3. Fallback to customer name or deal title
        $fallback = $deal->customer_name ?? ($deal->relationLoaded('customer') ? $deal->customer?->name : $deal->customer()->value('name')) ?? $deal->title ?? 'N/A';
        return trim((string)$fallback);
    }

    /**
     * Build the structured JSON payload for a deal.
     * Strictly contains only the job number and brand name as requested.
     */
    public function buildPayload(Deal $deal): array
    {
        return [
            'job_number' => $deal->job_number,
            'brand_name' => $this->getBrandName($deal),
        ];
    }

    /**
     * Send a webhook for a given deal to all configured webhook endpoints.
     */
    public function send(Deal $deal, string $event = 'job.created', $customUrls = null): array
    {
        if (!empty($customUrls)) {
            $urls = is_array($customUrls)
                ? array_values(array_unique($customUrls))
                : array_values(array_unique(preg_split('/[\s,;]+/', trim((string)$customUrls), -1, PREG_SPLIT_NO_EMPTY)));
        } else {
            $urls = $this->getWebhookUrls();
        }

        if (empty($urls)) {
            Log::channel('single')->debug("Workflow webhook skipped for Deal #{$deal->id}: WORKFLOW_WEBHOOK_URL is not configured.");
            return [
                'success' => false,
                'message' => 'WORKFLOW_WEBHOOK_URL is not configured.',
                'status' => null,
                'payload' => null,
                'results' => [],
            ];
        }

        $payload = $this->buildPayload($deal);
        $results = [];
        $overallSuccess = true;

        foreach ($urls as $url) {
            $res = $this->sendPayload($url, $payload, $event);
            $results[$url] = $res;
            if (!$res['success']) {
                $overallSuccess = false;
            }
        }

        $firstResult = reset($results);

        if (count($results) === 1) {
            return [
                'success' => $firstResult['success'],
                'status' => $firstResult['status'],
                'message' => $firstResult['message'],
                'response' => $firstResult['response'],
                'payload' => $payload,
                'results' => $results,
            ];
        }

        $successCount = count(array_filter($results, fn($r) => !empty($r['success'])));
        $totalCount = count($results);

        return [
            'success' => $overallSuccess,
            'status' => $overallSuccess ? 200 : ($firstResult['status'] ?? 500),
            'message' => $overallSuccess
                ? "Delivered successfully to all {$totalCount} webhook endpoints."
                : "Delivered to {$successCount}/{$totalCount} webhook endpoints.",
            'response' => json_encode(array_map(fn($r) => ['status' => $r['status'], 'message' => $r['message']], $results)),
            'payload' => $payload,
            'results' => $results,
        ];
    }

    /**
     * Send a real webhook ping using the latest actual deal in the database.
     */
    public function sendTest(?string $customUrl = null): array
    {
        $urls = !empty($customUrl)
            ? array_values(array_unique(preg_split('/[\s,;]+/', trim($customUrl), -1, PREG_SPLIT_NO_EMPTY)))
            : $this->getWebhookUrls();

        if (empty($urls)) {
            return [
                'success' => false,
                'message' => 'No webhook URL configured. Please set WORKFLOW_WEBHOOK_URL in .env or settings.',
                'status' => null,
                'payload' => null,
                'results' => [],
            ];
        }

        // Pick the latest actual job from the database (no fake test data)
        $deal = Deal::whereNotNull('job_number')
            ->where('job_number', '!=', '')
            ->with(['estimates', 'customer'])
            ->latest('id')
            ->first();

        if (!$deal) {
            return [
                'success' => false,
                'message' => 'No actual jobs found in the database to send.',
                'status' => null,
                'payload' => null,
                'results' => [],
            ];
        }

        $payload = $this->buildPayload($deal);
        $results = [];
        $overallSuccess = true;

        foreach ($urls as $url) {
            $res = $this->sendPayload($url, $payload, 'job.updated');
            $results[$url] = $res;
            if (!$res['success']) {
                $overallSuccess = false;
            }
        }

        $firstResult = reset($results);

        if (count($results) === 1) {
            return [
                'success' => $firstResult['success'],
                'status' => $firstResult['status'],
                'message' => $firstResult['message'],
                'response' => $firstResult['response'],
                'payload' => $payload,
                'results' => $results,
            ];
        }

        $successCount = count(array_filter($results, fn($r) => !empty($r['success'])));
        $totalCount = count($results);

        return [
            'success' => $overallSuccess,
            'status' => $overallSuccess ? 200 : ($firstResult['status'] ?? 500),
            'message' => $overallSuccess
                ? "Delivered test webhook successfully to all {$totalCount} endpoints."
                : "Test delivered to {$successCount}/{$totalCount} endpoints.",
            'response' => json_encode(array_map(fn($r) => ['status' => $r['status'], 'message' => $r['message']], $results)),
            'payload' => $payload,
            'results' => $results,
        ];
    }

    /**
     * Dispatch HTTP POST request with headers, timeout, and signature.
     */
    protected function sendPayload(string $url, array $payload, string $event): array
    {
        $secret = $this->getWebhookSecret();
        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'LoopsCRM-WorkflowWebhook/1.0',
            'X-Webhook-Event' => $event,
        ];

        if (!empty($secret)) {
            $headers['X-Webhook-Signature'] = hash_hmac('sha256', $jsonBody, $secret);
            $headers['X-Webhook-Token'] = $secret;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->post($url, $payload);

            $success = $response->successful();
            $status = $response->status();
            $body = substr($response->body(), 0, 500);

            if ($success) {
                Log::channel('single')->info("Workflow webhook delivered successfully to {$url} [Status: {$status}]", [
                    'event' => $event,
                    'job_number' => $payload['job_number'] ?? null,
                    'brand_name' => $payload['brand_name'] ?? null,
                ]);
            } else {
                Log::channel('single')->warning("Workflow webhook failed to {$url} [Status: {$status}]", [
                    'event' => $event,
                    'job_number' => $payload['job_number'] ?? null,
                    'brand_name' => $payload['brand_name'] ?? null,
                    'response' => $body,
                ]);
            }

            return [
                'success' => $success,
                'status' => $status,
                'message' => $success ? "Delivered successfully (HTTP {$status})." : "Webhook server returned HTTP {$status}.",
                'response' => $body,
                'payload' => $payload,
            ];
        } catch (\Throwable $e) {
            Log::channel('single')->error("Workflow webhook exception to {$url}: " . $e->getMessage(), [
                'event' => $event,
                'job_number' => $payload['job_number'] ?? null,
                'brand_name' => $payload['brand_name'] ?? null,
            ]);

            return [
                'success' => false,
                'status' => null,
                'message' => "Connection error: " . $e->getMessage(),
                'response' => null,
                'payload' => $payload,
            ];
        }
    }
}
