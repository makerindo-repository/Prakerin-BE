<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci endpoint di level API supaya cuma siswa/mahasiswa dengan status
 * Premium yang bisa akses (mis. AI Analytics, AI CV Generator, AI Report).
 *
 * Ini pelengkap dari <LockedFeature> di frontend — LockedFeature cuma
 * nyembunyiin UI-nya, tapi endpoint API-nya sendiri tetap bisa dipanggil
 * langsung (lewat Postman/curl/dsb) kalau tidak digate di sini juga.
 *
 * Alias: 'premium' (didaftarkan di bootstrap/app.php)
 * Pasang SETELAH middleware 'ability:...' di route, supaya $request->user()
 * sudah pasti ada (route sudah lolos auth:sanctum duluan).
 */
class EnsurePremiumSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // super_admin is always allowed
        if ($user?->role === 'super_admin') {
            return $next($request);
        }

        $student = $user?->student;
        if ($student) {
            if ($student->status_subscription !== 'premium') {
                return response()->json([
                    'errors' => 'Fitur ini khusus untuk pengguna Premium. Silakan upgrade akun kamu terlebih dahulu.',
                    'code'   => 'PREMIUM_REQUIRED',
                ], 403);
            }
            return $next($request);
        }

        $company = $user?->company;
        if ($company) {
            $status = $company->status_subscription ?? 'free';
            if ($status !== 'premium') {
                return response()->json([
                    'errors' => 'Fitur ini khusus untuk akun Perusahaan Premium. Silakan upgrade paket langganan industri Anda terlebih dahulu.',
                    'code'   => 'PREMIUM_REQUIRED',
                ], 403);
            }
            return $next($request);
        }

        return $next($request);
    }
}