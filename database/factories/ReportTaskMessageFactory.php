<?php

namespace Database\Factories;

use App\Models\ReportTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportTaskMessage>
 */
class ReportTaskMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // ambil task random dulu
        $reportTask = ReportTask::inRandomOrder()->first();

        // tentukan apakah report ini untuk company atau student
        $forCompany = $this->faker->boolean; // 50:50

        $companyId = null;
        $studentId = null;

        if ($reportTask) {
            if ($forCompany) {
                // ambil company lewat chain relasi reportTask -> internship -> internship_application -> job_opening -> company
                $companyId = optional($reportTask->task->internship?->internshipApplication?->jobOpening?->company)->id;
            } else {
                // ambil student lewat chain reportTask -> internship -> curriculum_vitae -> student
                $studentId = optional($reportTask->task->internship?->internshipApplication->curriculumVitae?->student)->id;
            }
        }

        return [
            'report_task_id' => $reportTask?->id ?? ReportTask::factory(),
            'company_id' => $companyId,
            'student_id' => $studentId,
            'message' => $this->faker->paragraph(),
        ];
    }
}
