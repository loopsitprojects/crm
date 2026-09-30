<?php

namespace App\Console\Commands;

use App\Models\Deal;
use App\Services\WorkflowWebhookService;
use Illuminate\Console\Command;

class SyncAllWorkflowJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:sync-all-jobs
                            {--url= : Custom webhook URL to send to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all actual existing jobs and brands from the CRM to the workflow system webhook';

    /**
     * Execute the console command.
     */
    public function handle(WorkflowWebhookService $webhookService): int
    {
        $customUrl = $this->option('url');
        $activeUrl = $customUrl ?: $webhookService->getWebhookUrl();

        $this->info("=== Workflow Webhook: Bulk Sync All Jobs ===");
        $this->line("Target URL: " . ($activeUrl ?: '<NOT CONFIGURED>'));

        if (empty($activeUrl)) {
            $this->error("No webhook URL configured! Please set WORKFLOW_WEBHOOK_URL in .env or pass --url=http://...");
            return Command::FAILURE;
        }

        $deals = Deal::whereNotNull('job_number')
            ->where('job_number', '!=', '')
            ->with(['estimates', 'customer'])
            ->orderBy('id', 'asc')
            ->get();

        $total = $deals->count();

        if ($total === 0) {
            $this->warn("No deals with job numbers found in the database.");
            return Command::SUCCESS;
        }

        $this->info("Found {$total} actual jobs in the server database. Starting sync to {$activeUrl}...");

        $bar = $this->output->createProgressBar($total);
        $successCount = 0;
        $failedCount = 0;

        foreach ($deals as $deal) {
            $result = $webhookService->send($deal, 'job.created');
            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Bulk sync complete: {$successCount} succeeded, {$failedCount} failed.");

        return $failedCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
