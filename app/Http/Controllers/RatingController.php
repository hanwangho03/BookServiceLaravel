<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rating;
use Illuminate\Validation\ValidationException;
class RatingController extends Controller
{
    public function getAllRatings()
    {
        $rating = Rating::with(['user','appointment'])->get();
        return response()->json([
            'message'=> "Lấy danh sách đánh giá thành công.",
            'rating' => $rating
        ]);
    }

    public function store (Request $request)
    {
        try {
            // Lấy user_id của người dùng đang đăng nhập thông qua token
            $userId = $request->user()->id;

            $validatedData = $request->validate([
                'comment' => 'nullable|string',
                'rating' => 'required|integer|min:1|max:5',
                'appointment_id' => 'required|exists:appointments,id',
            ]);

            $validatedData['user_id'] = $userId;

            $rating = Rating::create($validatedData);
            $rating->load(['user','appointment']);
            return response()->json([
                'message' => 'Thêm đánh giá thành công.',
                'rating' => $rating
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Lỗi xác thực dữ liệu.',
                'errors' => $e->errors()
            ], 422);
        }
    }
}
