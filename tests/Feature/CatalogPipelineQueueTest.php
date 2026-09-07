<?php

namespace Tests\Feature;

use App\Console\Commands\CatalogSystemCheck;
use Tests\TestCase;

class CatalogPipelineQueueTest extends TestCase
{
    /**
     * A worker started without an explicit --queue list only drains the default queue,
     * which no pipeline job uses. If a job routes itself to a queue nobody supervises,
     * imports queue up forever and every diagnostic still reports OK.
     */
    public function test_every_job_dispatches_to_a_supervised_queue(): void
    {
        $unsupervised = [];

        foreach (glob(app_path('Jobs/*.php')) ?: [] as $file) {
            $contents = (string) file_get_contents($file);

            if (! preg_match("/onQueue\('([^']+)'\)/", $contents, $matches)) {
                $unsupervised[basename($file)] = 'default (no onQueue call)';

                continue;
            }

            if (! in_array($matches[1], CatalogSystemCheck::PIPELINE_QUEUES, true)) {
                $unsupervised[basename($file)] = $matches[1];
            }
        }

        $this->assertSame([], $unsupervised, sprintf(
            "These jobs use queues missing from CatalogSystemCheck::PIPELINE_QUEUES:\n%s\nAdd them there, to docker-compose.yml and to the runbook, or the jobs will never run.",
            json_encode($unsupervised, JSON_PRETTY_PRINT)
        ));
    }

    /**
     * retry_after is configured against MAX_JOB_TIMEOUT_SECONDS. A job that declares a
     * longer timeout would be released back to the queue while still running and imported
     * twice in parallel, so the constant has to keep covering every job.
     */
    public function test_no_job_outlives_the_documented_maximum_timeout(): void
    {
        $tooLong = [];

        foreach (glob(app_path('Jobs/*.php')) ?: [] as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match('/public int \$timeout = (\d+);/', $contents, $matches)
                && (int) $matches[1] > CatalogSystemCheck::MAX_JOB_TIMEOUT_SECONDS) {
                $tooLong[basename($file)] = (int) $matches[1];
            }
        }

        $this->assertSame([], $tooLong, sprintf(
            "These jobs declare a timeout above CatalogSystemCheck::MAX_JOB_TIMEOUT_SECONDS (%ds):\n%s\nRaise the constant and the deployed queue retry_after together, or lower the job timeout.",
            CatalogSystemCheck::MAX_JOB_TIMEOUT_SECONDS,
            json_encode($tooLong, JSON_PRETTY_PRINT)
        ));
    }

    public function test_the_supervised_queue_list_has_no_duplicates(): void
    {
        $queues = CatalogSystemCheck::PIPELINE_QUEUES;

        $this->assertSame(array_values(array_unique($queues)), $queues);
    }
}
