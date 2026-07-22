<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Events\MessageSent;
use App\Models\ReportTask;
use App\Models\ReportTaskMessage;
use App\Services\NotificationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class ReportTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
    path: '/report-task/{id}',
    summary: 'Send report task message',
    tags: ['Report Task'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Report Task ID',
            schema: new OA\Schema(type: 'string')
        )
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['message'],
            properties: [
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Laporan tugas telah selesai dikerjakan.'
                )
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Message sent successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden'
        ),
        new OA\Response(
            response: 404,
            description: 'Report task not found'
        ),
        new OA\Response(
            response: 422,
            description: 'Validation Error'
        )
    ]
)]
    public function store(string $id, Request $request)
    {
        $reportTask = ReportTask::find($id);
        if (!$reportTask) {
            throw new HttpResponseException(response()->json(
                [
                    'errors' => 'Report task not found.'
                ],
                404
            ));
        }

        $studentId = $reportTask
            ->task
            ->internship
            ->internshipApplications
            ->curriculumVitae
            ->student_id;
        $companyId = $reportTask
            ->task
            ->internship
            ->internshipApplications
            ->jobOpening
            ->company_id;

        if ($studentId !== auth()->user()?->student->id) {
            if ($companyId !== auth()->user()?->company->id) {
                throw new HttpResponseException(response()->json(
                    [
                        'errors' => 'Forbidden'
                    ],
                    403
                ));
            }
        }

        $reportTask->makeHidden(['task']);

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json(
                [
                    'errors' => $validator->errors()
                ],
                422
            ));
        }

        $data = $validator->validated();

        $reportTaskMessage = new ReportTaskMessage();
        $reportTaskMessage->report_task_id = $reportTask->id;
        if (auth()->user()?->student) {
            $reportTaskMessage->student_id = auth()->user()->student->id;
        }
        if (auth()->user()?->company) {
            $reportTaskMessage->company_id = auth()->user()->company->id;
        }
        $reportTaskMessage->message = $data['message'];
        $reportTaskMessage->save();

        Broadcast(new MessageSent('aa', 'aa'));

        // Notify the student when company sends a message (feedback)
        // and notify the company when a student sends a message (update)
        try {
            $senderUser    = auth()->user();
            $frontendUrl   = config('app.frontend_url', 'http://localhost:3000');
            $taskTitle     = $reportTask->task?->title ?? 'Tugas';

            if ($senderUser?->company) {
                // Company sent feedback → notify student
                $studentUser = $reportTask->task?->internship?->student?->user;
                if ($studentUser) {
                    app(NotificationService::class)->notify(
                        userId      : $studentUser->id,
                        title       : "Feedback Laporan: {$taskTitle}",
                        content     : "Perusahaan mengirimkan feedback pada laporan tugas '{$taskTitle}': " . \Str::limit($data['message'], 120),
                        type        : 'report_feedback',
                        actionUrl   : "{$frontendUrl}/dashboard/tasklist/{$reportTask->task_id}",
                        relatedType : 'ReportTask',
                        relatedId   : $reportTask->id,
                        senderId    : $senderUser->id
                    );
                }
            } elseif ($senderUser?->student) {
                // Student sent update → notify company supervisor
                $companyUser = $reportTask->task?->internship?->company?->user;
                if ($companyUser) {
                    app(NotificationService::class)->notify(
                        userId      : $companyUser->id,
                        title       : "Laporan Diperbarui: {$taskTitle}",
                        content     : "Siswa/Mahasiswa memperbarui laporan tugas '{$taskTitle}': " . \Str::limit($data['message'], 120),
                        type        : 'report_feedback',
                        actionUrl   : "{$frontendUrl}/dashboard/tasklist/{$reportTask->task_id}",
                        relatedType : 'ReportTask',
                        relatedId   : $reportTask->id,
                        senderId    : $senderUser->id
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ReportTaskController] Notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'data' => $reportTaskMessage
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
    path: '/report-task/{id}',
    summary: 'Get report task detail',
    tags: ['Report Task'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Report Task ID',
            schema: new OA\Schema(type: 'string')
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Report task retrieved successfully'
        ),
        new OA\Response(
            response: 403,
            description: 'Forbidden'
        ),
        new OA\Response(
            response: 404,
            description: 'Report task not found'
        )
    ]
)]
    public function show(string $id)
    {
        $reportTask = ReportTask::find($id);
        if (!$reportTask) {
            throw new HttpResponseException(response()->json(
                [
                    'errors' => 'Report task not found.'
                ],
                404
            ));
        }

        $studentId = $reportTask
            ->task
            ->internship
            ->internshipApplications
            ->curriculumVitae
            ->student_id;
        $companyId = $reportTask
            ->task
            ->internship
            ->internshipApplications
            ->jobOpening
            ->company_id;

        if ($studentId !== auth()->user()?->student->id) {
            if ($companyId !== auth()->user()->company->id) {
                throw new HttpResponseException(response()->json(
                    [
                        'errors' => 'Forbidden'
                    ],
                    403
                ));
            }
        }

        $reportTask->makeHidden(['task']);

        return response()->json([
            'data' => $reportTask->load([
                'reportTaskMessage' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ])
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


}
