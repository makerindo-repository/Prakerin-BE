<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $search = $request->query('search', '');


        $feedback = Feedback::with('fromUser')
            ->where('to_user_id', auth()->id())
            ->where('text', 'like', "%{$search}%")
            ->whereHas(
                'fromUser',
                fn($query) =>
                $query->where('username', 'like', "%{$search}%")
            )
            ->paginate($limit);

        return response()->json([
            $feedback
        ]);
    }

    public function rate(Request $request)
    {
        // debug cepat: lihat isi pivot untuk user yang login
        Log::info('===> FUNCTION RATE DIPANGGIL <===');
        // debug: lihat query yang akan dieksekusi

        $limit = $request->query('limit', 10);
        $search = $request->query('search', '');

        $query = $request->user()->with(['toRate', 'feedbacksGiven']);
        Log::info('toRate sql', [$query->toSql(), $query->getBindings()]);

        // Pastikan kita Eager Load semua relasi yang mungkin dibutuhkan
        // untuk menghindari N+1 query problem saat transformasi.
        $query->toRate->with([
            'company.cityRegency.province',
            // 'school.someRelation'  // Ganti dengan relasi yang relevan untuk school
        ]);

        $query->feedbacksGiven([
            'formUser.student.major'
        ]);

        if ($search !== '') {
            $query->where('username', 'like', "%{$search}%");
        }

        // 1. Eksekusi query dan dapatkan hasil paginasi
        $paginatedUsers = $query->paginate($limit);

        // 2. Transformasi data menggunakan `through()`
        // Ini akan mengubah setiap item user di dalam paginator
        $transformedData = $paginatedUsers->through(function ($user) {
            // Gunakan switch untuk menentukan struktur berdasarkan role
            switch ($user->role) {
                case 'company':
                    // Pastikan relasi company tidak null untuk menghindari error
                    if ($user->company) {
                        return [
                            'is_done' => $user->pivot->is_done,
                            'id'       => $user->id,
                            'name'     => $user->company->name,
                            'kota'     => $user->company->cityRegency->name ?? 'Data tidak tersedia',
                            'provinsi' => $user->company->cityRegency->province->name ?? 'Data tidak tersedia',
                            'user'     => [
                                'photo_profile' => $user->photo_profile,
                            ],
                            // Anda bisa tambahkan data lain jika diperlukan
                            // 'id' => $user->id,
                        ];
                    }
                    break;

                case 'student':
                    // Anda bisa definisikan struktur untuk student di sini
                    return [
                        'nama_lengkap' => $user->student->full_name ?? $user->username,
                        'jurusan'      => $user->student->major ?? 'Belum ada jurusan',
                        'user'         => [
                            'photo_profile' => $user->photo_profile,
                        ]
                    ];
                    break;

                    // Tambahkan case lain jika ada role lain
            }

            // Jika role tidak cocok atau data relasi tidak ada, kembalikan null atau data default
            return null;
        });

        // 3. Filter hasil yang null (jika ada) dan kembalikan response
        // Catatan: Jika Anda memfilter, jumlah total item mungkin tidak sesuai lagi.
        // Opsi lain adalah tidak mem-filter dan biarkan frontend yang menangani item null.
        // Untuk saat ini kita biarkan apa adanya.

        return response()->json($transformedData, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_user_id' => 'required|exists:users,id',
            'to_type' => 'required|in:student,company,school,super_admin',
            'rating' => 'required|integer|min:1|max:5',
            'text' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $data = $validator->validated();

        if ($data['to_user_id'] === $request->user()->id) {
            throw new HttpResponseException(response()->json([
                'errors' => 'You cannot give feedback to yourself.'
            ], 400));
        }

        // Cek dulu apakah sudah kasih feedback
        $exists = Feedback::where('from_user_id', auth()->id())
            ->where('to_user_id', $data['to_user_id'])
            ->exists();

        if ($exists) {
            throw new HttpResponseException(response()->json([
                'errors' => 'You have already given feedback to this user.'
            ], 400));
        }

        $data['from_user_id'] = auth()->id();


        $feedback = Feedback::create($data);

        auth()->user()->toRate()->updateExistingPivot($data['to_user_id'], ['is_done' => true]);

        return response()->json([
            'data' => $feedback
        ]);
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // $user = auth()->user();
        // // $company = $user->toRate()->where('related_user_id', $id)->first();
        $data = Feedback::where('to_user_id', $id)->first();
        Log::info(response()->json($data, 200));
        // return response()->json([
        //     'rating' => $data->rating,
        //     'text' => $data->text
        // ], 200);
        return response()->json($data,200);
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
    public function destroy(Request $request, string $id)
    {
        $feedback = Feedback::where('id', $id)
            ->where('to_user_id', $request->user()->id)
            ->first();

        if (!$feedback) {
            throw new HttpResponseException(response()->json([
                'errors' => 'Feedback not found.'
            ], 404));
        }

        $feedback->delete();

        return response()->json([
            'message' => 'Feedback deleted successfully.'
        ]);
    }

    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'errors' => $validator->errors()
            ], 400));
        }

        $feedback = Feedback::where('from_user_id', auth()->id())
            ->where('to_user_id', $request->to_user_id)
            ->first();

        if (!$feedback) {
            throw new HttpResponseException(response()->json([
                'errors' => 'You have not given feedback.'
            ], 400));
        }

        return response()->json([
            'data' => [
                'already_rated' => (bool) $feedback,
                'feedback' => $feedback
            ],
        ]);
    }

    public function rating(Request $request)
    {
        $ratings = Feedback::where('to_user_id', $request->user()->id)->select('rating')->get();

        $ratingCount = $ratings->count();
        $averageRating = $ratings->avg('rating');
        $rating1 = $ratings->where('rating', 1)->count();
        $rating2 = $ratings->where('rating', 2)->count();
        $rating3 = $ratings->where('rating', 3)->count();
        $rating4 = $ratings->where('rating', 4)->count();
        $rating5 = $ratings->where('rating', 5)->count();

        return response()->json([
            'data' => [
                'rating_count' => $ratingCount,
                'average_rating' => $averageRating === null ? 0 : $averageRating,
                'rating_1' => $rating1,
                'rating_2' => $rating2,
                'rating_3' => $rating3,
                'rating_4' => $rating4,
                'rating_5' => $rating5,
            ],
        ]);
    }
}
