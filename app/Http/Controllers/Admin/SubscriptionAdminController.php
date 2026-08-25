<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Student;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionAdminController extends Controller
{
    // ── GET /api/v1/admin/subscriptions/list ──────────────────────────────

    /**
     * Paginated list of all students or companies with their subscription status.
     */
    public function list(Request $request): JsonResponse
    {
        $limit       = $request->integer('limit', 15);
        $search      = $request->input('search', '');
        $tier        = $request->input('tier'); // 'free' | 'premium' | null (all)
        $accountType = $request->input('account_type', 'student'); // 'student' | 'company'

        if ($accountType === 'company') {
            $companies = Company::with(['user', 'sector'])
                ->when($search, function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$search}%"));
                })
                ->when($tier, fn ($q) => $q->where('status_subscription', $tier))
                ->paginate($limit);

            $data = $companies->map(function (Company $c) {
                return [
                    'id'                      => $c->id,
                    'name'                    => $c->name,
                    'email'                   => $c->email ?? $c->user?->email,
                    'user_type'               => 'company',
                    'school'                  => $c->sector?->name ?? 'Perusahaan / Industri',
                    'status_subscription'     => $c->status_subscription ?? 'free',
                    'status_magang'           => 'active',
                    'subscription_end_date'   => $c->subscription_renewed_at ? \Carbon\Carbon::parse($c->subscription_renewed_at)->addYear()->toIso8601String() : null,
                    'renewal_date'            => $c->subscription_renewed_at,
                    'subscription_status'     => $c->status_subscription === 'premium' ? 'active' : 'inactive',
                ];
            });

            return response()->json([
                'data'  => $data,
                'meta'  => [
                    'total'        => $companies->total(),
                    'per_page'     => $companies->perPage(),
                    'current_page' => $companies->currentPage(),
                    'last_page'    => $companies->lastPage(),
                ],
            ]);
        }

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
     * Manually toggle a student or company's subscription tier (admin override).
     */
    public function toggleTier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'          => 'nullable|uuid',
            'company_id'          => 'nullable|uuid',
            'account_id'          => 'nullable|uuid',
            'account_type'        => 'nullable|in:student,company',
            'status_subscription' => 'required|in:free,premium',
        ]);

        $companyId = $validated['company_id'] ?? ($validated['account_type'] === 'company' ? $validated['account_id'] : null);
        $studentId = $validated['student_id'] ?? ($validated['account_type'] === 'student' ? $validated['account_id'] : null);

        if ($companyId) {
            $company = Company::findOrFail($companyId);
            $newStatus = $validated['status_subscription'];
            $now = now();

            $company->update([
                'status_subscription'     => $newStatus,
                'subscription_renewed_at' => $newStatus === 'premium' ? $now : null,
            ]);

            if ($company->user_id) {
                \App\Models\User::where('id', $company->user_id)->update([
                    'is_pro' => $newStatus === 'premium',
                ]);
            }

            if ($newStatus === 'premium') {
                Subscription::updateOrCreate(
                    ['user_id' => $company->id],
                    [
                        'user_type'               => 'company',
                        'amount'                  => 0,
                        'currency'                => 'IDR',
                        'status'                  => 'active',
                        'subscription_start_date' => $now,
                        'subscription_end_date'   => $now->copy()->addYear(),
                        'renewal_date'            => $now->copy()->addYear(),
                        'payment_method'          => 'MANUAL_ADMIN',
                    ]
                );
            } else {
                Subscription::where('user_id', $company->id)
                    ->where('status', 'active')
                    ->update(['status' => 'expired']);
            }

            return response()->json([
                'message'             => 'Tier perusahaan berhasil diperbarui.',
                'company_id'          => $company->id,
                'name'                => $company->name,
                'status_subscription' => $company->status_subscription,
            ]);
        }

        $student = Student::findOrFail($studentId ?? $validated['account_id']);

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

            if ($student->user_id) {
                \App\Models\User::where('id', $student->user_id)->update(['is_pro' => true]);
            }
        } else {
            $student->update(['status_subscription' => 'free']);

            if ($student->user_id) {
                \App\Models\User::where('id', $student->user_id)->update(['is_pro' => false]);
            }

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
