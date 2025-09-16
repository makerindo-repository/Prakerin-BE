<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
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

        return response()->json([
            'data' => $feedback
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
