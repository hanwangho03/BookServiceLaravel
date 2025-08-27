<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Services\EmailNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    private $appointmentService;
    private $emailService;

    public function __construct(AppointmentService $appointmentService, EmailNotificationService $emailService)
    {
        $this->appointmentService = $appointmentService;
        $this->emailService = $emailService;
    }

    /**
     * Lấy danh sách booking của người dùng đã đăng nhập.
     */
     public function getBookings(Request $request)
    {
        $user = $request->user();

        $appointmentsAsCustomer = $user->appointmentsAsCustomer()->with([
            'customer:id,name,email,phone_number', 
            'technician:id,name', 
            'service:id,name',
            'rating'
        ])->get();
        
        $appointmentsAsTechnician = $user->appointmentsAsTechnician()->with([
            'customer:id,name,email,phone_number', 
            'technician:id,name', 
            'service:id,name',
            'rating'
        ])->get();

        $allAppointments = $appointmentsAsCustomer->concat($appointmentsAsTechnician)->unique('id')->sortBy('start_time');

        return response()->json([
            'message' => 'Lấy danh sách cuộc hẹn thành công!',
            'bookings' => $allAppointments->values()->all(),
        ]);
    }
    
    /**
     * Lấy tất cả các lịch hẹn (dành cho admin).
     * Yêu cầu quyền admin để truy cập.
     */
    public function getAllAppointments(Request $request)
    {
        $appointments = Appointment::with([
            'customer:id,name,email,phone_number', 
            'technician:id,name', 
            'service:id,name'
        ])->orderBy('start_time', 'desc')->get();

        return response()->json([
            'message' => 'Lấy tất cả cuộc hẹn thành công!',
            'appointments' => $appointments,
        ]);
    }

    /**
     * Cập nhật trạng thái của một lịch hẹn (dành cho admin).
     */
    public function updateStatus(Request $request, $id)
    {
        // Tải các mối quan hệ cần thiết để gửi email
        $appointment = Appointment::with(['customer', 'service', 'technician'])->find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Không tìm thấy lịch hẹn.'], 404);
        }

        $request->validate([
            'status' => ['required', 'string', Rule::in(['da_xac_nhan', 'chua_xac_nhan', 'da_huy', 'da_hoan_thanh'])],
        ]);
        
        $newStatus = $request->status;
        $appointmentStartTime = Carbon::parse($appointment->start_time);
        $now = Carbon::now();

        switch ($newStatus) {
            case 'da_xac_nhan':
                // Không phê duyệt được lịch hẹn khi quá 8 tiếng so với thời điểm hẹn
                if ($now->greaterThan($appointmentStartTime->addHours(8))) {
                    return response()->json(['message' => 'Không thể phê duyệt lịch hẹn này vì thời điểm hẹn đã quá 8 tiếng.'], 400);
                }
                break;

            case 'da_hoan_thanh':
                // Không hoàn thành được khi chưa đến thời điểm hẹn quá 4 tiếng
                if ($now->lessThan($appointmentStartTime->subHours(4))) {
                    return response()->json(['message' => 'Không thể hoàn thành lịch hẹn này khi còn quá 4 tiếng nữa mới đến thời điểm hẹn.'], 400);
                }
                break;
            
            case 'da_huy':
                // Không hủy khi đã hoàn thành
                if ($appointment->status === 'da_hoan_thanh') {
                    return response()->json(['message' => 'Không thể hủy lịch hẹn đã hoàn thành.'], 400);
                }
                break;
        }

        $appointment->status = $newStatus;
        $appointment->save();

        // Sử dụng service mới để gửi email
        if ($newStatus === 'da_xac_nhan' || $newStatus === 'da_huy') {
            $this->emailService->sendAppointmentStatusNotification($appointment, $newStatus);
        }

        return response()->json(['message' => 'Cập nhật trạng thái lịch hẹn thành công.']);
    }

    /**
     * Endpoint API để thay đổi kỹ thuật viên cho một lịch hẹn.
     *
     * @param Request $request
     * @param int $id ID của lịch hẹn
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeTechnician(Request $request, int $id)
    {
        // Validate request
        $request->validate([
            'new_technician_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $updatedAppointment = $this->appointmentService->changeTechnicianForAppointment(
                $id, 
                $request->input('new_technician_id')
            );

            return response()->json([
                'message' => 'Thay đổi kỹ thuật viên thành công.',
                'appointment' => $updatedAppointment->load(['customer', 'technician', 'service']),
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
    public function getTechnicians(): JsonResponse
    {
        try {
            $technicians = $this->appointmentService->getAllTechnicians();
            return response()->json([
                'message' => 'Lấy danh sách kỹ thuật viên thành công.',
                'technicians' => $technicians,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    public function getTechnicianAppointments(Request $request, int $technicianId): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ]);
        
        try {
            $appointments = $this->appointmentService->getTechnicianAppointments(
                $technicianId,
                $request->query('date')
            );

            return response()->json([
                'message' => 'Lấy danh sách lịch hẹn của kỹ thuật viên thành công!',
                'appointments' => $appointments,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}