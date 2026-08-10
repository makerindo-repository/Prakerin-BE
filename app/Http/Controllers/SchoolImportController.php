<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SchoolImportController extends Controller
{
    /**
     * Alias nama header yang dikenali per kolom tujuan. Pencarian
     * case-insensitive dan trim spasi.
     */
    private const COLUMN_ALIASES = [
        'name' => ['nama perguruan tinggi', 'nama sekolah', 'nama'],
        'email' => ['email'],
        'password' => ['password'],
        'address' => ['alamat_diperbaiki', 'alamat diperbaiki', 'alamat'],
        // Dipakai HANYA sebagai fallback kalau kolom address di atas kosong/gak ada.
        'wilayah' => ['kode lldikti', 'wilayah'],
        'provinsi' => ['provinsi (cakupan wilayah)', 'provinsi'],
        // WAJIB — baris yang statusnya bukan persis "Kode LLDikti resmi" di-skip,
        // tidak ikut diimpor sama sekali (lihat filter di bawah).
        'sumber' => ['sumber data provinsi', 'status', 'sumber'],
    ];

    // POST /api/v1/admin/schools/import
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480', // maks 20MB
            'type' => 'nullable|in:university,school',
        ]);

        $type = $request->input('type', 'university');

        // File besar (ribuan baris) — jangan sampai kepotong timeout PHP
        // default di server.
        set_time_limit(0);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal membaca file Excel. Pastikan formatnya .xlsx/.xls dan tidak corrupt.',
                'debug'   => $e->getMessage(),
            ], 422);
        }

        $rows = $sheet->toArray(null, true, true, true); // keyed by kolom huruf (A, B, C, ...)
        if (count($rows) < 2) {
            return response()->json(['message' => 'File kosong atau cuma ada header, tidak ada data untuk diimpor.'], 422);
        }

        $headerRow = array_shift($rows); // baris pertama = header
        $columnMap = $this->mapColumns($headerRow);

        if (!isset($columnMap['name'])) {
            return response()->json([
                'message' => 'Kolom nama (mis. "Nama Perguruan Tinggi" / "Nama Sekolah") tidak ditemukan di file. Cek nama header kolom pertama.',
            ], 422);
        }

        if (!isset($columnMap['sumber'])) {
            return response()->json([
                'message' => 'Kolom status sumber data (mis. "Sumber Data Provinsi") tidak ditemukan di file. Kolom ini WAJIB ada — cuma baris berstatus "Kode LLDikti resmi" yang boleh diimpor.',
            ], 422);
        }

        $created = 0;
        $skippedExisting = 0;
        $skippedInvalid = 0;
        $skippedNotVerified = 0;
        $failed = 0;
        $failedDetails = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row[$columnMap['name']] ?? ''));
            if ($name === '') {
                $skippedInvalid++;
                continue;
            }

            // FILTER WAJIB: cuma baris dengan status persis "Kode LLDikti
            // resmi" yang boleh diimpor jadi data master — baris
            // "Perkiraan dari nama kampus" atau "Tidak diketahui" DILEWATI,
            // supaya data master cuma isi institusi yang sudah terverifikasi
            // official, bukan hasil tebakan.
            $sumber = mb_strtolower(trim((string) ($row[$columnMap['sumber']] ?? '')));
            if ($sumber !== 'kode lldikti resmi') {
                $skippedNotVerified++;
                continue;
            }

            $email = isset($columnMap['email']) ? trim((string) ($row[$columnMap['email']] ?? '')) : '';
            $password = isset($columnMap['password']) ? trim((string) ($row[$columnMap['password']] ?? '')) : '';

            // Idempotent: skip kalau nama sekolah ATAU email ini sudah ada
            // di database (baik dari import sebelumnya, maupun akun asli
            // yang sudah daftar sendiri) — jangan sampai dobel.
            $alreadyExists = School::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()
                || ($email !== '' && User::where('email', $email)->exists());

            if ($alreadyExists) {
                $skippedExisting++;
                continue;
            }

            try {
                DB::transaction(function () use ($row, $columnMap, $name, $email, $password, $type) {
                    $slug = Str::slug($name);
                    $slug = $slug !== '' ? $slug : ('akun-' . Str::random(8));
                    $slug = Str::limit($slug, 40, '');

                    $finalEmail = $email !== ''
                        ? $email
                        : $this->uniqueValue(
                            $slug,
                            fn ($candidateEmail) => User::where('email', $candidateEmail)->exists(),
                            fn ($c) => "{$c}@kampus.prakerin.id",
                        );

                    $username = $this->uniqueValue($slug, fn ($c) => User::where('username', $c)->exists());

                    // Kalau kolom Password kosong di file, tetap generate
                    // password sederhana (bukan random panjang yang susah
                    // dibaca) sebagai fallback, supaya akunnya tetap valid.
                    $finalPassword = $password !== '' ? $password : ('Kampus' . random_int(100000, 999999));

                    $user = new User();
                    $user->username = $username;
                    $user->email = $finalEmail;
                    $user->role = 'school';
                    $user->password = $finalPassword; // otomatis di-hash lewat cast 'hashed' di model User
                    $user->save();

                    $address = $this->resolveAddress($row, $columnMap);

                    $school = new School();
                    $school->name = $name;
                    $school->address = $address;
                    $school->user_id = $user->id;
                    $school->type = $type;
                    $school->is_verified = false; // placeholder, belum diklaim institusi aslinya
                    // Simpan Kode LLDikti (mis. "Wilayah 4") sebagai jejak audit —
                    // inilah bukti kenapa baris ini lolos filter "resmi" saat
                    // diimpor. Bukan NPSN asli (itu istilah utk sekolah/SMK),
                    // tapi kolom ini yang paling dekat kegunaannya untuk
                    // universitas.
                    $school->npsn = isset($columnMap['wilayah'])
                        ? trim((string) ($row[$columnMap['wilayah']] ?? '')) ?: null
                        : null;
                    $school->save();

                    $user->syncSpatieRole($type === 'university' ? 'university' : 'school');
                });

                $created++;
            } catch (\Throwable $e) {
                $failed++;
                if (count($failedDetails) < 50) {
                    $failedDetails[] = ['name' => $name, 'error' => $e->getMessage()];
                }
                Log::error("[SchoolImportController] Gagal impor \"{$name}\": " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => "Import selesai. Dibuat: {$created}, dilewati (sudah ada): {$skippedExisting}, dilewati (bukan Kode LLDikti resmi): {$skippedNotVerified}, dilewati (data tidak valid): {$skippedInvalid}, gagal: {$failed}.",
            'summary' => [
                'total_rows'          => count($rows),
                'created'             => $created,
                'skipped_existing'    => $skippedExisting,
                'skipped_not_verified'=> $skippedNotVerified,
                'skipped_invalid'     => $skippedInvalid,
                'failed'              => $failed,
            ],
            'failed_details' => $failedDetails,
        ]);
    }

    /**
     * Cocokkan header kolom di file (bebas urutan, case-insensitive) ke
     * key kolom tujuan lewat COLUMN_ALIASES. Balikin array
     * ['name' => 'B', 'email' => 'F', ...] (huruf kolom Excel-nya).
     */
    private function mapColumns(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $col => $label) {
            $normalized = mb_strtolower(trim((string) $label));
            if ($normalized === '') {
                continue;
            }
            foreach (self::COLUMN_ALIASES as $target => $aliases) {
                if (isset($map[$target])) {
                    continue; // sudah ketemu duluan di kolom lain
                }
                if (in_array($normalized, $aliases, true)) {
                    $map[$target] = $col;
                }
            }
        }
        return $map;
    }

    /**
     * Alamat diambil dari kolom address (mis. "Alamat_Diperbaiki") kalau
     * ada. Kalau gak ada/kosong, coba susun dari kolom wilayah+provinsi
     * mentah sebagai fallback. Kalau itu juga gak ada, kasih placeholder
     * yang jelas nandain "data belum lengkap" (bukan dikosongkan, karena
     * kolom address di database NOT NULL).
     */
    private function resolveAddress(array $row, array $columnMap): string
    {
        if (isset($columnMap['address'])) {
            $address = trim((string) ($row[$columnMap['address']] ?? ''));
            if ($address !== '') {
                return $address;
            }
        }

        $parts = [];
        if (isset($columnMap['wilayah'])) {
            $w = trim((string) ($row[$columnMap['wilayah']] ?? ''));
            if ($w !== '') $parts[] = "LLDikti {$w}";
        }
        if (isset($columnMap['provinsi'])) {
            $p = trim((string) ($row[$columnMap['provinsi']] ?? ''));
            if ($p !== '') $parts[] = $p;
        }

        return $parts ? implode(' — ', $parts) : 'Alamat belum tersedia (data impor)';
    }

    /**
     * Cari nilai unik dengan nambahin angka urut di belakang kalau
     * base-nya sudah kepakai.
     */
    private function uniqueValue(string $base, callable $exists, ?callable $formatter = null): string
    {
        $formatter ??= fn ($v) => $v;
        $candidate = $base;
        $suffix = 1;

        while ($exists($formatter($candidate))) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $formatter($candidate);
    }
}