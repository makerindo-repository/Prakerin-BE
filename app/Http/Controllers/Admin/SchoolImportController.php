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
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

class SchoolImportController extends Controller
{
    /**
     * POST /api/v1/admin/schools/import
     *
     * Excel final:
     * No | Nama | Email | Password | Kode LLDikti | Provinsi | Alamat | Foto
     *
     * Foto bukan nama file. Foto adalah gambar yang benar-benar
     * tertanam sebagai Drawing pada worksheet.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:1048576', // Allow up to 1GB
            'type' => 'nullable|in:university,school',
        ]);

        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $type = $request->input('type', 'university');

        try {
            $spreadsheet = IOFactory::load(
                $request->file('file')->getRealPath()
            );
        } catch (\Throwable $e) {
            Log::error('[SchoolImport] Excel load failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal membaca Excel.',
                'debug' => $e->getMessage(),
            ], 422);
        }

        $sheet = $spreadsheet->getSheetByName('IMPORT_WEB')
            ?? $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return response()->json([
                'message' => 'Excel kosong atau hanya memiliki header.',
            ], 422);
        }

        $headers = array_shift($rows);
        $columns = $this->mapColumns($headers);

        if (!isset($columns['name'])) {
            return response()->json([
                'message' => 'Kolom "Nama" tidak ditemukan.',
                'headers_terbaca' => $headers,
            ], 422);
        }

        /*
         * ULTIMATE TIMEOUT & CORS FIX:
         * We wrap the heavy processing inside app()->terminating().
         * This allows Laravel to return the JSON response to the browser immediately (including CORS headers!),
         * and then PHP will silently execute the heavy loop in the background.
         */
        app()->terminating(function () use ($sheet, $rows, $columns, $type) {
            try {
                $drawingsByRow = $this->extractDrawingsByRow($sheet);
            } catch (\Throwable $e) {
                $drawingsByRow = [];
                Log::error('[SchoolImport Background] Failed to extract drawings', ['error' => $e->getMessage()]);
            }

        $created = 0;
        $updated = 0;
        $photosSaved = 0;
        $defaultPhotos = 0;
        $skipped = 0;
        $failed = 0;

        $failedDetails = [];

        $processedEmails = [];
        $processedNames = [];

        // OPTIMIZATION: Fetch all existing users and schools at once to prevent N+1 Queries
        $allEmails = [];
        $allNames = [];
        foreach ($rows as $index => $row) {
            $name = $this->value($row, $columns['name'] ?? null);
            if ($name !== '') {
                $allNames[] = $name;
                $email = mb_strtolower($this->value($row, $columns['email'] ?? null));
                if ($email !== '') {
                    $allEmails[] = $email;
                }
            }
        }

        $existingUsersByEmail = User::whereIn(DB::raw('LOWER(email)'), array_unique($allEmails))
            ->get()
            ->keyBy(function($u) { return strtolower($u->email); });

        $existingSchoolsByName = School::whereIn(DB::raw('LOWER(name)'), array_unique($allNames))
            ->with('user')
            ->get()
            ->keyBy(function($s) { return strtolower($s->name); });

        foreach ($rows as $index => $row) {
            /*
             * Karena header dihapus, data pertama memiliki index 2
             * pada array hasil toArray(). Tetap gunakan:
             *
             * $excelRow = $index
             *
             * karena PhpSpreadsheet array masih mempertahankan key
             * baris Excel.
             */
            $excelRow = (int) $index;

            $name = $this->value(
                $row,
                $columns['name'] ?? null
            );

            if ($name === '') {
                $skipped++;
                continue;
            }

            $email = mb_strtolower(
                $this->value(
                    $row,
                    $columns['email'] ?? null
                )
            );

            $password = $this->value(
                $row,
                $columns['password'] ?? null
            );

            $lldikti = $this->value(
                $row,
                $columns['lldikti'] ?? null
            );

            $provinsi = $this->value(
                $row,
                $columns['provinsi'] ?? null
            );

            $address = $this->value(
                $row,
                $columns['address'] ?? null
            );

            $normalizedName = $this->normalize($name);

            /*
             * Jangan proses baris duplicate dua kali.
             */
            if (isset($processedNames[$normalizedName])) {
                $skipped++;
                continue;
            }

            $processedNames[$normalizedName] = true;

            if ($email !== '') {
                if (isset($processedEmails[$email])) {
                    $skipped++;
                    continue;
                }

                $processedEmails[$email] = true;
            }

            try {
                $result = DB::transaction(function () use (
                    $name,
                    $email,
                    $password,
                    $lldikti,
                    $provinsi,
                    $address,
                    $type,
                    $drawingsByRow,
                    $excelRow
                ) {
                    /*
                     * =====================================================
                     * USER
                     * =====================================================
                     */
                    $user = null;

                    if ($email !== '') {
                        $user = $existingUsersByEmail[$email] ?? null;
                    }

                    /*
                     * Jika email tidak menemukan user,
                     * cari school berdasarkan nama.
                     */
                    $school = $existingSchoolsByName[strtolower($name)] ?? null;

                    if (
                        !$user &&
                        $school &&
                        $school->user
                    ) {
                        $user = $school->user;
                    }

                    $isNewUser = !$user;
                    $isNewSchool = !$school;

                    if (!$user) {
                        $slug = Str::slug($name);

                        if ($slug === '') {
                            $slug = 'kampus-' . Str::lower(Str::random(8));
                        }

                        $username = $this->uniqueUsername($slug);

                        $finalEmail = $email !== ''
                            ? $email
                            : $this->uniqueEmail($slug);

                        $finalPassword = $password !== ''
                            ? $password
                            : 'Kampus' . random_int(100000, 999999);

                        $user = new User();
                        $user->username = $username;
                        $user->email = $finalEmail;

                        /*
                         * Struktur legacy aplikasi.
                         */
                        $user->role = 'school';

                        /*
                         * User model repository kamu memakai cast
                         * password => hashed, jadi jangan Hash::make()
                         * dua kali.
                         */
                        $user->password = $finalPassword;
                        $user->save();
                    } else {
                        /*
                         * USER LAMA:
                         * password TIDAK DIUBAH.
                         */
                        if (empty($user->username)) {
                            $user->username = $this->uniqueUsername(
                                Str::slug($name),
                                $user->id
                            );
                        }

                        /*
                         * Jangan mengganti email user lama.
                         */
                        $user->role = 'school';
                        $user->save();
                    }

                    /*
                     * =====================================================
                     * FOTO DARI EXCEL
                     * =====================================================
                     */
                    $photo = null;

                    if (isset($drawingsByRow[$excelRow])) {
                        $photo = $drawingsByRow[$excelRow];
                    }

                    if ($photo) {
                        $photoName = $this->storePhoto(
                            $photo['contents'],
                            $photo['extension'],
                            $name
                        );

                        /*
                         * Karena Excel adalah sumber data foto,
                         * foto dari Excel boleh memperbarui foto user.
                         */
                        $user->photo_profile = $photoName;
                        $user->save();

                        $photoSaved = true;
                    } else {
                        /*
                         * Fallback.
                         *
                         * Pastikan default-school.png sudah ada di:
                         * storage/app/public/photo-profile/default-school.png
                         */
                        if (
                            empty($user->photo_profile) ||
                            !Storage::disk('public')->exists(
                                'photo-profile/' . $user->photo_profile
                            )
                        ) {
                            $user->photo_profile = $this->ensureDefaultPhoto();
                            $user->save();
                        }

                        $photoSaved = false;
                    }

                    /*
                     * =====================================================
                     * SCHOOL
                     * =====================================================
                     */
                    if (!$school) {
                        $school = new School();
                    }

                    $school->user_id = $user->id;
                    $school->name = $name;
                    $school->type = $type;

                    /*
                     * Gunakan alamat Excel kalau tersedia.
                     * Kalau kosong, buat fallback dari wilayah/provinsi.
                     */
                    if ($address !== '') {
                        $school->address = $address;
                    } elseif (empty($school->address)) {
                        $school->address = $this->buildAddress(
                            $lldikti,
                            $provinsi
                        );
                    }

                    /*
                     * PENTING:
                     * Kode LLDikti BUKAN NPSN.
                     * Jangan masukkan Wilayah 4/3/7/etc. ke npsn.
                     */
                    $school->save();

                    /*
                     * Sinkronisasi role sesuai helper User yang sudah
                     * ada di repository.
                     */
                    $user->syncSpatieRole(
                        $type === 'university'
                            ? 'university'
                            : 'school'
                    );

                    return [
                        'new_user' => $isNewUser,
                        'new_school' => $isNewSchool,
                        'photo_saved' => $photoSaved,
                    ];
                });

                if (
                    $result['new_user'] ||
                    $result['new_school']
                ) {
                    $created++;
                } else {
                    $updated++;
                }

                if ($result['photo_saved']) {
                    $photosSaved++;
                } else {
                    $defaultPhotos++;
                }
            } catch (\Throwable $e) {
                $failed++;

                if (count($failedDetails) < 100) {
                    $failedDetails[] = [
                        'row' => $excelRow,
                        'name' => $name,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ];
                }

                Log::error('[SchoolImport] Row failed', [
                    'row' => $excelRow,
                    'name' => $name,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        } // End of foreach
        }); // End of app()->terminating()

        // Return immediately to the browser with CORS headers intact
        return response()->json([
            'message' => 'Import sedang diproses di background! Silakan tutup popup ini dan refresh halaman dalam beberapa menit untuk melihat hasilnya.',
            'summary' => [
                'total_rows' => count($rows),
                'created' => 'Berjalan di Background',
                'skipped_existing' => 0,
                'skipped_not_verified' => 0,
                'skipped_invalid' => 0,
                'failed' => 0,
            ],
            'failed_details' => [],
        ]);
    }

    /**
     * Extract all embedded drawings and associate them with Excel row.
     *
     * PhpSpreadsheet officially exposes getDrawingCollection() for reading
     * images from a worksheet. MemoryDrawing is rendered from its image
     * resource; normal Drawing objects can be read from their path.
     */
    private function extractDrawingsByRow($sheet): array
    {
        $result = [];

        foreach ($sheet->getDrawingCollection() as $drawing) {
            $coordinate = $drawing->getCoordinates();

            if (!preg_match('/^([A-Z]+)(\d+)$/i', $coordinate, $m)) {
                continue;
            }

            $row = (int) $m[2];

            try {
                $imageContents = null;
                $extension = 'png';

                if ($drawing instanceof MemoryDrawing) {
                    $resource = $drawing->getImageResource();
                    $renderingFunction = $drawing->getRenderingFunction();

                    if (
                        is_resource($resource) &&
                        is_callable($renderingFunction)
                    ) {
                        ob_start();
                        call_user_func(
                            $renderingFunction,
                            $resource
                        );
                        $imageContents = ob_get_clean();
                    }

                    switch ($drawing->getMimeType()) {
                        case MemoryDrawing::MIMETYPE_JPEG:
                            $extension = 'jpg';
                            break;

                        case MemoryDrawing::MIMETYPE_GIF:
                            $extension = 'gif';
                            break;

                        case MemoryDrawing::MIMETYPE_PNG:
                        default:
                            $extension = 'png';
                            break;
                    }
                } else {
                    $path = $drawing->getPath();

                    if (!$path) {
                        continue;
                    }

                    /*
                     * Untuk XLSX embedded image, PhpSpreadsheet menyediakan
                     * path yang dapat dibaca sebagai binary.
                     */
                    if ($drawing->getIsURL()) {
                        $imageContents = @file_get_contents($path);
                    } else {
                        $imageContents = @file_get_contents($path);
                    }

                    $extension = strtolower(
                        $drawing->getExtension() ?: 'png'
                    );

                    if ($extension === 'jpeg') {
                        $extension = 'jpg';
                    }
                }

                if (
                    !is_string($imageContents) ||
                    $imageContents === ''
                ) {
                    continue;
                }

                /*
                 * Validasi bahwa binary memang gambar.
                 */
                $imageInfo = @getimagesizefromstring(
                    $imageContents
                );

                if ($imageInfo === false) {
                    continue;
                }

                $mime = $imageInfo['mime'] ?? '';

                $mimeToExtension = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];

                if (isset($mimeToExtension[$mime])) {
                    $extension = $mimeToExtension[$mime];
                }

                /*
                 * Jika ada lebih dari satu drawing di baris yang sama,
                 * ambil yang pertama.
                 */
                if (!isset($result[$row])) {
                    $result[$row] = [
                        'contents' => $imageContents,
                        'extension' => $extension,
                        'coordinate' => $coordinate,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[SchoolImport] Cannot extract drawing', [
                    'coordinate' => $coordinate,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Simpan foto ke public disk.
     */
    private function storePhoto(
        string $contents,
        string $extension,
        string $schoolName
    ): string {
        $extension = strtolower($extension);

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        if (!in_array(
            $extension,
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            true
        )) {
            $extension = 'png';
        }

        $base = Str::slug($schoolName);

        if ($base === '') {
            $base = 'kampus';
        }

        /*
         * Hash membuat nama file stabil untuk foto yang sama,
         * sekaligus mencegah benturan nama.
         */
        $hash = substr(
            hash('sha256', $contents),
            0,
            12
        );

        $filename =
            $base .
            '-' .
            $hash .
            '.' .
            $extension;

        Storage::disk('public')->put(
            'photo-profile/' . $filename,
            $contents
        );

        return $filename;
    }

    /**
     * Pastikan fallback tersedia.
     */
    private function ensureDefaultPhoto(): string
    {
        $filename = 'default-school.png';

        $path = 'photo-profile/' . $filename;

        if (
            !Storage::disk('public')->exists($path)
        ) {
            /*
             * Jangan membuat binary gambar palsu.
             * Sediakan file default-school.png di:
             *
             * storage/app/public/photo-profile/default-school.png
             */
            throw new \RuntimeException(
                'File default-school.png belum ada di ' .
                'storage/app/public/photo-profile/'
            );
        }

        return $filename;
    }

    /**
     * Map header Excel.
     */
    private function mapColumns(array $headers): array
    {
        $aliases = [
            'name' => [
                'nama',
                'nama perguruan tinggi',
                'nama sekolah',
                'nama institusi',
            ],
            'email' => [
                'email',
                'e-mail',
            ],
            'password' => [
                'password',
                'pass',
                'kata sandi',
            ],
            'lldikti' => [
                'kode lldikti',
                'lldikti',
                'wilayah',
                'wilayah lldikti',
            ],
            'provinsi' => [
                'provinsi',
                'provinsi cakupan wilayah',
                'provinsi (cakupan wilayah)',
            ],
            'address' => [
                'alamat',
                'alamat diperbaiki',
                'alamat_diperbaiki',
                'address',
            ],
        ];

        $map = [];

        foreach ($headers as $column => $header) {
            $normalizedHeader = $this->normalizeHeader($header);

            foreach ($aliases as $key => $names) {
                foreach ($names as $name) {
                    if (
                        $normalizedHeader ===
                        $this->normalizeHeader($name)
                    ) {
                        $map[$key] = $column;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    private function normalizeHeader($value): string
    {
        $value = (string) $value;
        $value = str_replace(
            "\xC2\xA0",
            ' ',
            $value
        );

        $value = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $value
        );

        $value = mb_strtolower(
            trim($value)
        );

        $value = str_replace(
            ['_', '-'],
            ' ',
            $value
        );

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        );

        return trim($value);
    }

    private function value(
        array $row,
        ?string $column
    ): string {
        if ($column === null) {
            return '';
        }

        return trim(
            (string) (
                $row[$column] ?? ''
            )
        );
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(
            trim($value)
        );

        return preg_replace(
            '/\s+/u',
            ' ',
            $value
        );
    }

    private function uniqueUsername(
        string $base,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($base);

        if ($base === '') {
            $base = 'kampus';
        }

        $base = Str::limit(
            $base,
            35,
            ''
        );

        $candidate = $base;
        $number = 1;

        while (true) {
            $query = User::where(
                'username',
                $candidate
            );

            if ($ignoreId !== null) {
                $query->where(
                    'id',
                    '!=',
                    $ignoreId
                );
            }

            if (!$query->exists()) {
                return $candidate;
            }

            $number++;

            $candidate =
                Str::limit(
                    $base,
                    30,
                    ''
                ) .
                '-' .
                $number;
        }
    }

    private function uniqueEmail(
        string $base
    ): string {
        $base = Str::slug($base);

        if ($base === '') {
            $base = 'kampus';
        }

        $candidate =
            $base .
            '@kampus.prakerin.id';

        $number = 1;

        while (
            User::where(
                'email',
                $candidate
            )->exists()
        ) {
            $number++;

            $candidate =
                $base .
                $number .
                '@kampus.prakerin.id';
        }

        return $candidate;
    }

    private function buildAddress(
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

        return $parts
            ? implode(' — ', $parts)
            : 'Alamat belum tersedia';
    }
}
