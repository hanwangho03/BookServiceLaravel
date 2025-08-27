<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RatingController;



// Các route không cần xác thực
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Các route cần xác thực
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Các route thử nghiệm cho từng vai trò
    Route::get('/admin-dashboard', function () {
        return response()->json(['message' => 'Chào mừng đến với trang Admin!']);
    })->middleware('role:admin');

    Route::get('/technician-dashboard', function () {
        return response()->json(['message' => 'Chào mừng đến với trang Kỹ thuật viên!']);
    })->middleware('role:technician');

    Route::get('/customer-dashboard', function () {
        return response()->json(['message' => 'Chào mừng đến với trang Khách hàng!']);
    })->middleware('role:customer');

    Route::middleware('auth:sanctum')->get('/user/bookings', [AppointmentController::class, 'getBookings']);
     // Route để tạo lịch hẹn
    Route::middleware('auth:sanctum')->post('/bookings', [BookingController::class, 'store']);
    // Route để lấy danh sách services
    Route::get('/services', [BookingController::class, 'getServices']);
    Route::get('/appointments', [AppointmentController::class, 'getAllAppointments']);
    Route::post('/appointments/{id}/update-status', [AppointmentController::class, 'updateStatus']);
    Route::post('/appointments/{id}/change-technician', [AppointmentController::class, 'changeTechnician']);
    Route::get('/technicians', [AppointmentController::class, 'getTechnicians']);
    
    Route::get('/technicians/{id}/appointments', [AppointmentController::class, 'getTechnicianAppointments']);
    
    Route::get('/ratings', [RatingController::class, 'getAllRatings']);
    Route::post('/ratings', [RatingController::class, 'store']);
});
