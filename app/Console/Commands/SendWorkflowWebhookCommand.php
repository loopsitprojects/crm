<?php

namespace App\Console\Commands;

use App\Models\Deal;
use App\Services\WorkflowWebhookService;
use Illuminate\Console\Command;

class SendWorkflowWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:workflow-test 
                            {--deal= : Deal ID to send}
                            {--job= : Job Number to send (e.g. LOOPS/2026/0001)}
                            {--url= : Custom webhook URL to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test or trigger the workflow system webhook with job number and brand name';

    /**
     * Execute the console command.
     */
    public function handle(WorkflowWebhookService $webhookService): int
    {
        $customUrl = $this->option('url');
        $dealId = $this->option('deal');
        $jobNumber = $this->option('job');

        $activeUrl = $customUrl ?: $webhookService->getWebhookUrl();
        $this->info("=== Workflow Webhook Dispatcher ===");
        $this->line("Target URL: " . ($activeUrl ?: '<NOT CONFIGURED>'));
        $this->line("Secret:     " . ($webhookService->getWebhookSecret() ? '<CONFIGURED (HMAC-SHA256)>' : '<NONE>'));

        if (empty($activeUrl)) {
            $this->error("No webhook URL configured! Please set WORKFLOW_WEBHOOK_URL in your .env file or pass --url=http://...");
            return Command::FAILURE;
        }

        if ($dealId || $jobNumber) {
            $query = Deal::query();
            if ($dealId) {
                $query->where('id', $dealId);
            }
            if ($jobNumber) {
                $query->where('job_number', $jobNumber);
            }

            $deal = $query->first();

            if (!$deal) {
                $this->error("Deal not found for given criteria (Deal ID: {$dealId}, Job: {$jobNumber})");
                return Command::FAILURE;
            }

            $this->info("Dispatching webhook for Deal #{$deal->id} (Job: {$deal->job_number})...");
            $result = $webhookService->send($deal, 'job.updated');
        } else {
            $this->info("Dispatching test webhook payload...");
            $result = $webhookService->sendTest($customUrl);
        }

        $this->newLine();
        $this->line("Status:     " . ($result['status'] ?? 'N/A'));
        $this->line("Result:     " . ($result['success'] ? '<fg=green>SUCCESS</>' : '<fg=red>FAILED</>'));
        $this->line("Message:    " . $result['message']);

        if (!empty($result['payload'])) {
            $this->newLine();
            $this->line("<fg=yellow>Payload Dispatched:</>");
            $this->line(json_encode($result['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (!empty($result['response'])) {
            $this->newLine();
            $this->line("<fg=cyan>Server Response:</>");
            $this->line($result['response']);
        }

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }
}
