<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ReportTask;
use App\Models\ReportTaskMessage;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
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

        return response()->json([
            'data' => $reportTaskMessage
        ], 201);
    }

    /**
     * Display the specified resource.
     */
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
