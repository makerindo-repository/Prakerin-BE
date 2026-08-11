<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SchoolImportController extends Controller
{
    /**
     * Header Excel yang dikenali.
     *
     * Excel terbaru:
     *
     * No
     * Nama
     * Email
     * Password
     * Kode LLDikti
     * Provinsi
     * Alamat
     * Foto
     * Foto_Search_URL
     */
    private const COLUMN_ALIASES = [
        'name' => [
            'nama perguruan tinggi',
            'nama sekolah',
            'nama',
        ],

        'email' => [
            'email',
        ],

        'password' => [
            'password',
        ],

        'address' => [
            'alamat_diperbaiki',
            'alamat diperbaiki',
            'alamat',
        ],

        'wilayah' => [
            'kode lldikti',
            'wilayah',
        ],

        'provinsi' => [
            'provinsi (cakupan wilayah)',
            'provinsi',
        ],

        'sumber' => [
            'sumber data provinsi',
            'status',
            'sumber',
        ],

        'photo' => [
            'foto',
            'photo',
            'foto profil',
            'photo profile',
            'logo',
        ],

        'photo_search_url' => [
            'foto_search_url',
            'foto search url',
            'photo search url',
        ],
    ];

    /**
     * POST /api/v1/admin/schools/import
     *
     * Import sekolah/perguruan tinggi dari Excel.
     *
     * Perilaku:
     *
     * - Data baru       -> CREATE
     * - Email sama      -> UPDATE
     * - Nama sama       -> UPDATE
     * - Password lama   -> TIDAK ditimpa
     * - Password baru   -> ambil dari Excel
     * - Foto lama ada   -> pertahankan
     * - Foto belum ada  -> gunakan foto dari Excel/default
     * - Hanya "Kode LLDikti resmi" yang diimport
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
            'type' => 'nullable|in:university,school',
        ]);

        $type = $request->input('type', 'university');

        set_time_limit(0);

        try {
            $spreadsheet = IOFactory::load(
                $request->file('file')->getRealPath()
            );

            $sheet = $spreadsheet->getActiveSheet();
        } catch (\Throwable $e) {
            Log::error(
                '[SchoolImportController] Gagal membaca Excel: '
                . $e->getMessage()
            );

            return response()->json([
                'message' => 'Gagal membaca file Excel. Pastikan file .xlsx/.xls valid dan tidak corrupt.',
                'debug' => $e->getMessage(),
            ], 422);
        }

        $rows = $sheet->toArray(
            null,
            true,
            true,
            true
        );

        if (count($rows) < 2) {
            return response()->json([
                'message' => 'File Excel kosong atau hanya berisi header.',
            ], 422);
        }

        $headerRow = array_shift($rows);

        $columnMap = $this->mapColumns($headerRow);

        /*
         * Validasi kolom wajib.
         */
        if (!isset($columnMap['name'])) {
            return response()->json([
                'message' => 'Kolom "Nama" tidak ditemukan di Excel.',
            ], 422);
        }

        if (!isset($columnMap['sumber'])) {
            return response()->json([
                'message' => 'Kolom "Sumber Data Provinsi" tidak ditemukan. Kolom ini wajib ada.',
            ], 422);
        }

        /*
         * Statistik.
         */
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        $skippedNotVerified = 0;
        $skippedInvalid = 0;
        $failed = 0;

        $failedDetails = [];

        /*
         * Untuk mencegah dua baris Excel yang sama-sama
         * menunjuk ke record yang sama dalam satu import.
         */
        $processedEmails = [];
        $processedNames = [];

        foreach ($rows as $excelRowNumber => $row) {

            /*
             * Ambil nama.
             */
            $name = trim(
                (string) (
                    $row[$columnMap['name']] ?? ''
                )
            );

            if ($name === '') {
                $skippedInvalid++;
                continue;
            }

            /*
             * HANYA DATA RESMI.
             *
             * Excel kita menggunakan:
             *
             * Kode LLDikti resmi
             */
            $sumber = mb_strtolower(
                trim(
                    (string) (
                        $row[$columnMap['sumber']] ?? ''
                    )
                )
            );

            if ($sumber !== 'kode lldikti resmi') {
                $skippedNotVerified++;
                continue;
            }

            /*
             * Email.
             */
            $email = '';

            if (isset($columnMap['email'])) {
                $email = trim(
                    (string) (
                        $row[$columnMap['email']] ?? ''
                    )
                );
            }

            /*
             * Normalisasi email.
             */
            if ($email !== '') {
                $email = mb_strtolower($email);
            }

            /*
             * Password dari Excel.
             */
            $password = '';

            if (isset($columnMap['password'])) {
                $password = trim(
                    (string) (
                        $row[$columnMap['password']] ?? ''
                    )
                );
            }

            /*
             * LLDikti.
             */
            $lldikti = '';

            if (isset($columnMap['wilayah'])) {
                $lldikti = trim(
                    (string) (
                        $row[$columnMap['wilayah']] ?? ''
                    )
                );
            }

            /*
             * Provinsi.
             */
            $provinsi = '';

            if (isset($columnMap['provinsi'])) {
                $provinsi = trim(
                    (string) (
                        $row[$columnMap['provinsi']] ?? ''
                    )
                );
            }

            /*
             * Foto.
             */
            $photo = '';

            if (isset($columnMap['photo'])) {
                $photo = trim(
                    (string) (
                        $row[$columnMap['photo']] ?? ''
                    )
                );
            }

            /*
             * URL pencarian foto.
             *
             * Belum digunakan sebagai photo_profile karena
             * URL Google Search bukan URL file gambar.
             */
            $photoSearchUrl = '';

            if (isset($columnMap['photo_search_url'])) {
                $photoSearchUrl = trim(
                    (string) (
                        $row[$columnMap['photo_search_url']] ?? ''
                    )
                );
            }

            /*
             * Cegah duplicate di dalam file Excel.
             */
            $normalizedName = mb_strtolower(
                preg_replace('/\s+/', ' ', $name)
            );

            if (
                isset($processedNames[$normalizedName]) &&
                $processedNames[$normalizedName] === true
            ) {
                $skippedInvalid++;
                continue;
            }

            if (
                $email !== '' &&
                isset($processedEmails[$email]) &&
                $processedEmails[$email] === true
            ) {
                $skippedInvalid++;
                continue;
            }

            $processedNames[$normalizedName] = true;

            if ($email !== '') {
                $processedEmails[$email] = true;
            }

            try {

                $result = DB::transaction(function () use (
                    $name,
                    $email,
                    $password,
                    $lldikti,
                    $provinsi,
                    $photo,
                    $photoSearchUrl,
                    $type
                ) {

                    /*
                     * =====================================================
                     * 1. CARI USER LAMA BERDASARKAN EMAIL
                     * =====================================================
                     */
                    $user = null;

                    if ($email !== '') {
                        $user = User::whereRaw(
                            'LOWER(email) = ?',
                            [$email]
                        )->first();
                    }

                    /*
                     * =====================================================
                     * 2. CARI SCHOOL LAMA BERDASARKAN NAMA
                     * =====================================================
                     */
                    $school = School::whereRaw(
                        'LOWER(name) = ?',
                        [$name]
                    )->first();

                    /*
                     * Kalau school sudah ada tetapi email Excel
                     * berbeda, gunakan user milik school tersebut.
                     */
                    if (!$user && $school) {
                        $user = $school->user;
                    }

                    $isNewUser = !$user;
                    $isNewSchool = !$school;

                    /*
                     * =====================================================
                     * 3. BUAT USER BARU JIKA BELUM ADA
                     * =====================================================
                     */
                    if (!$user) {

                        $slug = Str::slug($name);

                        if ($slug === '') {
                            $slug = 'kampus-' . Str::random(8);
                        }

                        /*
                         * Maks username mengikuti struktur
                         * yang sudah ada.
                         */
                        $username = $this->uniqueValue(
                            Str::limit($slug, 40, ''),
                            fn ($candidate) =>
                                User::where('username', $candidate)->exists()
                        );

                        /*
                         * Kalau email Excel kosong,
                         * generate email internal.
                         */
                        $finalEmail = $email !== ''
                            ? $email
                            : $this->uniqueValue(
                                Str::limit($slug, 40, ''),
                                fn ($candidate) =>
                                    User::where('email', $candidate)->exists(),
                                fn ($candidate) =>
                                    "{$candidate}@kampus.prakerin.id"
                            );

                        /*
                         * Password Excel.
                         *
                         * Kalau kosong:
                         * Kampus + 6 angka.
                         */
                        $finalPassword = $password !== ''
                            ? $password
                            : 'Kampus' . random_int(100000, 999999);

                        $user = new User();

                        $user->username = $username;
                        $user->email = $finalEmail;

                        /*
                         * Role legacy.
                         */
                        $user->role = 'school';

                        /*
                         * User model memakai cast hashed.
                         * Jadi tidak perlu Hash::make().
                         */
                        $user->password = $finalPassword;

                        /*
                         * User baru belum diverifikasi.
                         */
                        if (
                            $user->is_verified === null
                        ) {
                            $user->is_verified = false;
                        }

                        $user->save();

                    } else {

                        /*
                         * =================================================
                         * 4. USER LAMA
                         * =================================================
                         *
                         * PENTING:
                         *
                         * PASSWORD TIDAK DIGANTI.
                         *
                         * Jadi user lama masih bisa login
                         * menggunakan password sebelumnya.
                         */

                        /*
                         * Jika username kosong, buat username.
                         */
                        if (
                            empty($user->username)
                        ) {

                            $slug = Str::slug($name);

                            if ($slug === '') {
                                $slug = 'kampus-' . Str::random(8);
                            }

                            $user->username = $this->uniqueValue(
                                Str::limit($slug, 40, ''),
                                fn ($candidate) =>
                                    User::where('username', $candidate)
                                        ->where('id', '!=', $user->id)
                                        ->exists()
                            );
                        }

                        /*
                         * Jika email lama kosong dan Excel punya email,
                         * baru kita update email.
                         *
                         * Kalau email sudah berbeda, kita tidak
                         * memaksa mengganti agar akun lama aman.
                         */
                        if (
                            empty($user->email) &&
                            $email !== ''
                        ) {

                            $emailExists = User::whereRaw(
                                'LOWER(email) = ?',
                                [$email]
                            )
                            ->where('id', '!=', $user->id)
                            ->exists();

                            if (!$emailExists) {
                                $user->email = $email;
                            }
                        }

                        $user->role = 'school';

                        $user->save();
                    }

                    /*
                     * =====================================================
                     * 5. FOTO
                     * =====================================================
                     *
                     * Excel sekarang berisi default-school.png.
                     *
                     * Kalau user lama sudah punya foto,
                     * jangan ditimpa.
                     */
                    $newPhoto = $this->resolvePhoto(
                        $photo,
                        $photoSearchUrl
                    );

                    if (
                        empty($user->photo_profile) &&
                        $newPhoto !== null
                    ) {
                        $user->photo_profile = $newPhoto;
                        $user->save();
                    }

                    /*
                     * =====================================================
                     * 6. BUAT / UPDATE SCHOOL
                     * =====================================================
                     */
                    if (!$school) {
                        $school = new School();
                    }

                    $school->user_id = $user->id;
                    $school->name = $name;

                    /*
                     * Address.
                     */
                    $school->address = $this->buildAddress(
                        $name,
                        $lldikti,
                        $provinsi
                    );

                    $school->type = $type;

                    /*
                     * Data berasal dari daftar resmi,
                     * tetapi belum tentu sudah melakukan klaim akun.
                     */
                    if ($school->is_verified === null) {
                        $school->is_verified = false;
                    }

                    /*
                     * JANGAN masukkan LLDikti ke npsn.
                     *
                     * Kita hanya mengisinya jika memang
                     * sudah ada NPSN sebelumnya.
                     */
                    /*
                     * $school->npsn = ...
                     *
                     * sengaja tidak dilakukan.
                     */

                    /*
                     * Website belum tersedia di Excel.
                     * Jangan menghapus website lama.
                     */

                    $school->save();

                    /*
                     * =====================================================
                     * 7. ROLE SPATIE
                     * =====================================================
                     */
                    $user->syncSpatieRole(
                        $type === 'university'
                            ? 'university'
                            : 'school'
                    );

                    return [
                        'new_user' => $isNewUser,
                        'new_school' => $isNewSchool,
                    ];
                });

                if ($result['new_user'] || $result['new_school']) {
                    $created++;
                } else {
                    $updated++;
                }

            } catch (\Throwable $e) {

                $failed++;

                if (count($failedDetails) < 50) {
                    $failedDetails[] = [
                        'row' => $excelRowNumber + 1,
                        'name' => $name,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ];
                }

                Log::error(
                    "[SchoolImportController] Gagal impor \"{$name}\": "
                    . $e->getMessage(),
                    [
                        'email' => $email,
                        'row' => $excelRowNumber + 1,
                    ]
                );
            }
        }

        /*
         * =========================================================
         * RESPONSE
         * =========================================================
         */
        return response()->json([
            'message' =>
                "Import selesai. " .
                "Dibuat: {$created}, " .
                "diperbarui: {$updated}, " .
                "dilewati bukan resmi: {$skippedNotVerified}, " .
                "dilewati tidak valid: {$skippedInvalid}, " .
                "gagal: {$failed}.",

            'summary' => [
                'total_rows' => count($rows),
                'created' => $created,
                'updated' => $updated,
                'skipped_not_verified' => $skippedNotVerified,
                'skipped_invalid' => $skippedInvalid,
                'failed' => $failed,
            ],

            'failed_details' => $failedDetails,
        ]);
    }

    /**
     * Mapping header Excel.
     */
    private function mapColumns(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $column => $label) {

            $normalized = mb_strtolower(
                trim((string) $label)
            );

            if ($normalized === '') {
                continue;
            }

            foreach (self::COLUMN_ALIASES as $target => $aliases) {

                if (isset($map[$target])) {
                    continue;
                }

                if (
                    in_array(
                        $normalized,
                        $aliases,
                        true
                    )
                ) {
                    $map[$target] = $column;
                }
            }
        }

        return $map;
    }

    /**
     * Membuat alamat berdasarkan Excel.
     *
     * Karena Excel belum mempunyai alamat jalan lengkap,
     * kita tidak membuat alamat palsu.
     */
    private function buildAddress(
        string $name,
        string $lldikti,
        string $provinsi
    ): string {

        $parts = [];

        if ($provinsi !== '') {
            $parts[] = $provinsi;
        }

        if ($lldikti !== '') {
            $parts[] = $lldikti;
        }

        if (empty($parts)) {
            return 'Alamat belum tersedia';
        }

        return implode(' — ', $parts);
    }

    /**
     * Tentukan foto yang digunakan.
     *
     * Excel:
     *
     * Foto = default-school.png
     *
     * Foto_Search_URL = Google Image Search.
     *
     * Google Search TIDAK disimpan sebagai photo_profile.
     */
    private function resolvePhoto(
        string $photo,
        string $photoSearchUrl = ''
    ): ?string {

        /*
         * Jika kosong.
         */
        if ($photo === '') {
            return 'default-school.png';
        }

        /*
         * Kalau Excel berisi URL pencarian Google,
         * jangan digunakan sebagai foto.
         */
        if (
            str_contains(
                mb_strtolower($photo),
                'google.com/search'
            )
        ) {
            return 'default-school.png';
        }

        /*
         * Kalau URL langsung gambar.
         *
         * Untuk sekarang kita simpan URL tersebut.
         */
        if (
            filter_var(
                $photo,
                FILTER_VALIDATE_URL
            )
        ) {

            return $photo;
        }

        /*
         * Kalau hanya nama file:
         *
         * default-school.png
         *
         * kita simpan nama filenya.
         */
        return $photo;
    }

    /**
     * Cari nilai unik.
     */
    private function uniqueValue(
        string $base,
        callable $exists,
        ?callable $formatter = null
    ): string {

        $formatter ??= fn ($value) => $value;

        $candidate = $base;
        $suffix = 1;

        while (
            $exists(
                $formatter($candidate)
            )
        ) {

            $suffix++;

            $candidate =
                "{$base}-{$suffix}";
        }

        return $formatter($candidate);
    }
}