<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionAdminController extends Controller
{
    // ── GET /api/v1/admin/subscriptions/list ──────────────────────────────

    /**
     * Paginated list of all students with their subscription status.
     */
    public function list(Request $request): JsonResponse
    {
        $limit  = $request->integer('limit', 15);
        $search = $request->input('search', '');
        $tier   = $request->input('tier'); // 'free' | 'premium' | null (all)

        $students = Student::with(['school', 'subscription'])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($tier, fn ($q) => $q->where('status_subscription', $tier))
            ->paginate($limit);

        $data = $students->map(function (Student $s) {
            $sub = $s->subscription;
            return [
                'id'                    => $s->id,
                'name'                  => $s->name,
                'user_type'             => $s->subscription_user_type,
                'school'                => optional($s->school)->name,
                'status_subscription'   => $s->status_subscription ?? 'free',
                'status_magang'         => $s->status_magang ?? 'not_started',
                'subscription_end_date' => $sub?->subscription_end_date,
                'renewal_date'          => $sub?->renewal_date,
                'subscription_status'   => $sub?->status,
            ];
        });

        return response()->json([
            'data'  => $data,
            'meta'  => [
                'total'        => $students->total(),
                'per_page'     => $students->perPage(),
                'current_page' => $students->currentPage(),
                'last_page'    => $students->lastPage(),
            ],
        ]);
    }

    // ── POST /api/v1/admin/subscriptions/toggle ───────────────────────────

    /**
     * Manually toggle a student's subscription tier (admin override).
     */
    public function toggleTier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'          => 'required|uuid|exists:students,id',
            'status_subscription' => 'required|in:free,premium',
        ]);

        $student = Student::findOrFail($validated['student_id']);

        if ($validated['status_subscription'] === 'premium') {
            $now = now();
            $endDate = $now->copy()->addYear();

            Subscription::updateOrCreate(
                ['user_id' => $student->id],
                [
                    'user_type'               => $student->subscription_user_type,
                    'amount'                  => 0,
                    'currency'                => 'IDR',
                    'status'                  => 'active',
                    'subscription_start_date' => $now,
                    'subscription_end_date'   => $endDate,
                    'renewal_date'            => $endDate,
                    'payment_method'          => 'MANUAL_ADMIN',
                ]
            );

            $student->update([
                'status_subscription'     => 'premium',
                'subscription_renewed_at' => $now,
            ]);
        } else {
            $student->update(['status_subscription' => 'free']);

            Subscription::where('user_id', $student->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);
        }

        return response()->json([
            'message'             => 'Tier berhasil diperbarui.',
            'student_id'          => $student->id,
            'name'                => $student->name,
            'status_subscription' => $student->status_subscription,
        ]);
    }
}
