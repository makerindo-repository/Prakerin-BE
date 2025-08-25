<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // ambil task random dulu
        $task = Task::inRandomOrder()->first();

        // tentukan apakah report ini untuk company atau student
        $forCompany = $this->faker->boolean; // 50:50

        $companyId = null;
        $studentId = null;

        if ($task) {
            if ($forCompany) {
                // ambil company lewat chain relasi task -> internship -> internship_application -> job_opening -> company
                $companyId = optional($task->internship?->internshipApplication?->jobOpening?->company)->id;
            } else {
                // ambil student lewat chain task -> internship -> curriculum_vitae -> student
                $studentId = optional($task->internship?->curriculumVitae?->student)->id;
            }
        }

        return [
            'task_id' => $task?->id ?? Task::factory(),
            'company_id' => $companyId,
            'student_id' => $studentId,
            'report' => $this->faker->paragraph(),
        ];
    }
}
