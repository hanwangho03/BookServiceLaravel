<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class AppointmentService
{
    private const MAX_APPOINTMENTS_PER_DAY = 3;
    private const APPOINTMENT_DURATION_MINUTES = 20;
    private const MAX_CONCURRENT_APPOINTMENTS = 2;

    /**
     * Tạo một lịch hẹn mới.
     *
     * @param User $customer Đối tượng người dùng khách hàng
     * @param int $serviceId ID của dịch vụ
     * @param string $startTime Chuỗi thời gian bắt đầu (ISO 8601 string)
     * @return Appointment
     * @throws \Exception
     */
    public function createAppointment(User $customer, int $serviceId, string $startTime): Appointment
    {
        Log::info("Creating appointment for customer ID: {$customer->id}, serviceId: {$serviceId}, startTime: {$startTime}");

        $startTime = Carbon::parse($startTime);
        
        // 1. Kiểm tra tính hợp lệ của thời gian đặt lịch
        if (!$this->isValidAppointmentTime($startTime)) {
            Log::error("Invalid appointment time: {$startTime}");
            throw new \Exception("Thời gian đặt lịch không hợp lệ.");
        }

        // 2. Tìm kiếm dịch vụ
        $service = Service::find($serviceId);
        if (!$service) {
            throw new \Exception("Dịch vụ không tồn tại.");
        }
        
        Log::info("Customer: {$customer->username}, Service: {$service->name}");

        // 3. Kiểm tra giới hạn đặt lịch trong ngày của khách hàng
        $userAppointmentsToday = Appointment::where('customer_user_id', $customer->id)
                                         ->whereDate('start_time', $startTime->toDateString())
                                         ->count();

        if ($userAppointmentsToday >= self::MAX_APPOINTMENTS_PER_DAY) {
            Log::warning("Customer {$customer->username} has reached the daily appointment limit.");
            throw new \Exception("Bạn chỉ được đặt tối đa " . self::MAX_APPOINTMENTS_PER_DAY . " lịch hẹn trong một ngày.");
        }

        // 4. Kiểm tra xem mốc thời gian đã đầy chưa (max 2 appointment)
        $appointmentCount = Appointment::where('start_time', $startTime)
                                       ->whereIn('status', ['cho_xac_nhan', 'da_xac_nhan'])
                                       ->count();
        if ($appointmentCount >= self::MAX_CONCURRENT_APPOINTMENTS) {
            Log::error("Time slot is full at: {$startTime}");
            throw new \Exception("Mốc thời gian đã đầy. Vui lòng chọn thời gian khác.");
        }
        
        // 5. Giao kỹ thuật viên rảnh rỗi nhất
        $endTime = $startTime->copy()->addMinutes(self::APPOINTMENT_DURATION_MINUTES);
        $technician = $this->assignTechnician($startTime, $endTime);
        if (!$technician) {
            Log::error("No available technician for time slot: {$startTime}");
            throw new \Exception("Không có kỹ thuật viên rảnh trong mốc thời gian này.");
        }
        Log::info("Assigned technician: {$technician->username}");

        // 6. Lưu lịch hẹn trong một transaction
        return DB::transaction(function () use ($customer, $service, $technician, $startTime, $endTime) {
            $appointment = new Appointment();
            $appointment->customer_user_id = $customer->id;
            $appointment->service_id = $service->id;
            $appointment->technician_user_id = $technician->id;
            $appointment->start_time = $startTime;
            $appointment->end_time = $endTime;
            $appointment->status = 'cho_xac_nhan';
            $appointment->save();

            return $appointment;
        });
    }

    /**
     * Thay đổi kỹ thuật viên cho một lịch hẹn.
     *
     * @param int $appointmentId ID của lịch hẹn cần thay đổi
     * @param int $newTechnicianId ID của kỹ thuật viên mới
     * @return Appointment
     * @throws \Exception
     */
    public function changeTechnicianForAppointment(int $appointmentId, int $newTechnicianId): Appointment
    {
        $appointment = Appointment::find($appointmentId);
        if (!$appointment) {
            throw new \Exception("Không tìm thấy lịch hẹn.");
        }

        $newTechnician = User::find($newTechnicianId);
        if (!$newTechnician || !$newTechnician->role('technician')) {
            throw new \Exception("Kỹ thuật viên mới không tồn tại hoặc không hợp lệ.");
        }

        // Kiểm tra xem kỹ thuật viên mới có rảnh trong mốc thời gian của lịch hẹn không
        $isAvailable = $this->isTechnicianAvailable(
            $newTechnicianId, 
            Carbon::parse($appointment->start_time), 
            Carbon::parse($appointment->end_time)
        );

        if (!$isAvailable) {
            throw new \Exception("Kỹ thuật viên mới không rảnh trong mốc thời gian này.");
        }

        // Cập nhật kỹ thuật viên và lưu
        $appointment->technician_user_id = $newTechnicianId;
        $appointment->save();

        Log::info("Technician for appointment ID: {$appointmentId} changed to new technician ID: {$newTechnicianId}");

        return $appointment;
    }

    /**
     * Kiểm tra tính hợp lệ của thời gian đặt lịch.
     *
     * @param Carbon $startTime
     * @return bool
     */
    private function isValidAppointmentTime(Carbon $startTime): bool
    {
        $now = Carbon::now();

        // Thời gian không được ở quá khứ
        if ($startTime->isBefore($now->subMinutes(1))) {
            Log::warning("Invalid appointment time (in the past): {$startTime}");
            return false;
        }

        // Chủ nhật nghỉ
        if ($startTime->dayOfWeek === Carbon::SUNDAY) {
            return false;
        }
        
        // Phải là mốc 20 phút
        if ($startTime->minute % 20 !== 0) {
            return false;
        }

        // Thứ 2 đến Thứ 6: 08:00 - 20:00
        if ($startTime->dayOfWeek >= Carbon::MONDAY && $startTime->dayOfWeek <= Carbon::FRIDAY) {
            return $startTime->between(
                $startTime->copy()->setTime(8, 0, 0),
                $startTime->copy()->setTime(20, 0, 0)
            );
        }

        // Thứ 7: 08:00 - 12:00
        if ($startTime->dayOfWeek === Carbon::SATURDAY) {
            return $startTime->between(
                $startTime->copy()->setTime(8, 0, 0),
                $startTime->copy()->setTime(12, 0, 0)
            );
        }

        return false;
    }
    
    /**
     * Kiểm tra xem một kỹ thuật viên cụ thể có rảnh trong một khoảng thời gian hay không.
     *
     * @param int $technicianId ID của kỹ thuật viên
     * @param Carbon $startTime Thời gian bắt đầu của lịch hẹn
     * @param Carbon $endTime Thời gian kết thúc của lịch hẹn
     * @return bool
     */
    private function isTechnicianAvailable(int $technicianId, Carbon $startTime, Carbon $endTime): bool
    {
        $conflictingAppointment = Appointment::where('technician_user_id', $technicianId)
                                             ->where(function ($query) use ($startTime, $endTime) {
                                                 $query->where('start_time', '<', $endTime)
                                                       ->where('end_time', '>', $startTime);
                                             })
                                             ->whereIn('status', ['cho_xac_nhan', 'da_xac_nhan'])
                                             ->exists();
                                             
        return !$conflictingAppointment;
    }

    /**
     * Giao kỹ thuật viên rảnh rỗi nhất (ít lịch hẹn nhất trong ngày).
     *
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @return User|null
     */
    private function assignTechnician(Carbon $startTime, Carbon $endTime): ?User
    {
        // Giả định role 'technician' có id = 2
        $technicians = User::whereHas('role', function ($query) {
            $query->where('name', 'technician');
        })->get();

        if ($technicians->isEmpty()) {
            Log::warning("No technicians found with role 'technician'");
            return null;
        }

        $selectedTechnician = null;
        $minAppointments = PHP_INT_MAX;

        foreach ($technicians as $technician) {
            // Kiểm tra kỹ thuật viên có bị trùng lịch trong khung giờ này không
            $isAvailable = $this->isTechnicianAvailable($technician->id, $startTime, $endTime);

            if ($isAvailable) {
                // Đếm số lịch hẹn trong ngày của kỹ thuật viên này
                $appointmentCount = Appointment::where('technician_user_id', $technician->id)
                                               ->whereDate('start_time', $startTime->toDateString())
                                               ->whereIn('status', ['cho_xac_nhan', 'da_xac_nhan', 'da_hoan_thanh'])
                                               ->count();

                if ($appointmentCount < $minAppointments) {
                    $minAppointments = $appointmentCount;
                    $selectedTechnician = $technician;
                }
            }
        }
        
        if ($selectedTechnician) {
            Log::info("Assigned technician: {$selectedTechnician->username} with {$minAppointments} appointments on {$startTime->toDateString()}");
        } else {
            Log::warning("No technician available for time slot: {$startTime}");
        }

        return $selectedTechnician;
    }

    /**
     * Lấy danh sách các services
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllServices()
    {
        return Service::all();
    }
    /**
     * Lấy danh sách tất cả kỹ thuật viên.
     *
     * @return Collection
     */
    public function getAllTechnicians(): Collection
    {
        try {
            // Giả định bạn có role 'technician'
            return User::whereHas('role', function ($query) {
                $query->where('name', 'technician');
            })->get();
        } catch (\Exception $e) {
            Log::error("Error fetching all technicians: " . $e->getMessage());
            throw new \Exception("Không thể lấy danh sách kỹ thuật viên.");
        }
    }
    public function getTechnicianAppointments(int $technicianId, ?string $date = null): Collection
    {
        Log::info("Fetching appointments for technician ID: {$technicianId}");
        try {
            $query = Appointment::where('technician_user_id', $technicianId)
                                ->with(['customer:id,name,email,phone_number', 'service:id,name']);

            if ($date) {
                // Lọc theo ngày cụ thể
                $query->whereDate('start_time', $date);
            }

            // Sắp xếp theo thời gian bắt đầu
            return $query->orderBy('start_time')->get();
        } catch (\Exception $e) {
            Log::error("Error fetching appointments for technician ID {$technicianId}: " . $e->getMessage());
            throw new \Exception("Không thể lấy danh sách lịch hẹn của kỹ thuật viên.");
        }
    }
}