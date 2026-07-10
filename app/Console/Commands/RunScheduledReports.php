<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ScheduledReport;
use App\Models\Report;
use App\Http\Controllers\ReportController;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RunScheduledReports extends Command
{
    protected $signature = 'app:run-scheduled-reports';
    protected $description = 'Run scheduled reports based on active frequency (daily, weekly, monthly)';

    public function handle()
    {
        $this->info("Checking scheduled reports...");
        $activeReports = ScheduledReport::where('is_active', true)->get();

        $today = Carbon::today();
        $controller = new ReportController();

        foreach ($activeReports as $scheduled) {
            $shouldRun = false;

            if (!$scheduled->last_sent_at) {
                $shouldRun = true;
            } else {
                $lastSent = Carbon::parse($scheduled->last_sent_at);
                if ($scheduled->frequency === 'daily' && $lastSent->diffInDays($today) >= 1) {
                    $shouldRun = true;
                } elseif ($scheduled->frequency === 'weekly' && $lastSent->diffInWeeks($today) >= 1) {
                    $shouldRun = true;
                } elseif ($scheduled->frequency === 'monthly' && $lastSent->diffInMonths($today) >= 1) {
                    $shouldRun = true;
                }
            }

            if ($shouldRun) {
                $this->info("Running report ID: {$scheduled->id} ({$scheduled->type})");

                $subRequest = new Request();
                if ($scheduled->type === 'internship_stats') {
                    $data = $controller->internshipStats($subRequest)->getData(true);
                } elseif ($scheduled->type === 'student_progress') {
                    $data = $controller->studentProgress($subRequest)->getData(true);
                } else {
                    $data = $controller->companyPerformance($subRequest)->getData(true);
                }

                // Save report log
                Report::create([
                    'type' => $scheduled->type,
                    'data' => $data,
                    'generated_at' => Carbon::now(),
                    'generated_by_id' => $scheduled->created_by_id
                ]);

                $scheduled->update(['last_sent_at' => Carbon::now()]);
                
                $this->info("Report generated and saved successfully. Mock emailed to " . implode(', ', $scheduled->email_recipients));
            }
        }

        $this->info("Scheduled reports check complete.");
    }
}
