<?php

namespace App\Services;

use App\Mail\AppointmentStatusMail;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    /**
     * Gửi email thông báo về trạng thái của lịch hẹn cho khách hàng.
     *
     * @param Appointment $appointment
     * @param string $newStatus
     * @return void
     */
    public function sendAppointmentStatusNotification(Appointment $appointment, string $newStatus)
    {
        Log::info("Attempting to send email for appointment ID: {$appointment->id} with new status: {$newStatus}");

        try {
            // Kiểm tra nếu có thông tin khách hàng và email
            if ($appointment->customer && $appointment->customer->email) {
                // Tạo Mailable instance và truyền dữ liệu cần thiết
                Mail::to($appointment->customer->email)->send(new AppointmentStatusMail($appointment, $newStatus));
                
                Log::info("Email notification for appointment ID: {$appointment->id} successfully sent to: {$appointment->customer->email}");
            } else {
                Log::warning("Could not send email for appointment ID: {$appointment->id}. Customer or email address not found.");
            }
        } catch (\Exception $e) {
            // Ghi log lỗi nếu việc gửi email thất bại
            Log::error("Failed to send email for appointment ID: {$appointment->id}. Error: " . $e->getMessage());
        }
    }
}
