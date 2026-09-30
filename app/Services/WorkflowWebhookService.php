<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorkflowWebhookService
{
    /**
     * Get the configured webhook URL.
     */
    public function getWebhookUrl(): ?string
    {
        $url = config('services.workflow.webhook_url') ?: env('WORKFLOW_WEBHOOK_URL');

        if (empty($url) && class_exists(Setting::class)) {
            $url = Setting::get('workflow_webhook_url');
        }

        return !empty($url) ? trim((string)$url) : null;
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

        return null;
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
     * Send a webhook for a given deal.
     */
    public function send(Deal $deal, string $event = 'job.created'): array
    {
        $url = $this->getWebhookUrl();

        if (empty($url)) {
            Log::channel('single')->debug("Workflow webhook skipped for Deal #{$deal->id}: WORKFLOW_WEBHOOK_URL is not configured.");
            return [
                'success' => false,
                'message' => 'WORKFLOW_WEBHOOK_URL is not configured.',
                'status' => null,
                'payload' => null,
            ];
        }

        $payload = $this->buildPayload($deal);
        return $this->sendPayload($url, $payload, $event);
    }

    /**
     * Send a test webhook ping.
     */
    public function sendTest(?string $customUrl = null): array
    {
        $url = $customUrl ?: $this->getWebhookUrl();

        if (empty($url)) {
            return [
                'success' => false,
                'message' => 'No webhook URL configured. Please set WORKFLOW_WEBHOOK_URL in .env or settings.',
                'status' => null,
                'payload' => null,
            ];
        }

        $year = date('Y');
        $payload = [
            'job_number' => "LOOPS/{$year}/TEST",
            'brand_name' => 'Loops Sample Brand',
        ];

        return $this->sendPayload($url, $payload, 'job.test');
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
