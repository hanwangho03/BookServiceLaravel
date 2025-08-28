<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AppointmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    protected $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    /**
     * API để tạo một lịch hẹn mới.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // 1. Validate request test test 2
        $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'start_time' => 'required|date_format:Y-m-d H:i:s',
        ]);
        
        try {
            // 2. Lấy thông tin user hiện tại
            $customer = Auth::user();
            if (!$customer) {
                return response()->json(['message' => 'Người dùng chưa được xác thực.'], 401);
            }
            
            // 3. Gọi service để tạo lịch hẹn
            $appointment = $this->appointmentService->createAppointment(
                $customer,
                $request->service_id,
                $request->start_time
            );

            // 4. Trả về response thành công
            return response()->json([
                'message' => 'Đặt lịch thành công!',
                'appointment' => $appointment->load(['customer', 'service', 'technician']) // Load các mối quan hệ
            ], 201);

        } catch (\Exception $e) {
            Log::error("Booking failed: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * API để lấy danh sách các services
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServices()
    {
        $services = $this->appointmentService->getAllServices();
        return response()->json($services);
    }
}
