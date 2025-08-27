<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch hẹn đã được phê duyệt</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .header { background-color: #4CAF50; padding: 20px; color: #ffffff; text-align: center; }
        .content { padding: 20px 30px; line-height: 1.6; color: #333333; }
        .details { background-color: #f9f9f9; padding: 15px; border-radius: 6px; border-left: 5px solid #4CAF50; margin-top: 20px; }
        .details p { margin: 5px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #777777; border-top: 1px solid #eeeeee; }
        .button { display: inline-block; padding: 10px 20px; margin-top: 20px; background-color: #4CAF50; color: #ffffff; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Thông Báo Lịch Hẹn</h2>
        </div>
        <div class="content">
            <p>Xin chào **{{ $appointment->customer->name }}**,</p>
            <p>Chúng tôi xin thông báo rằng lịch hẹn của bạn đã được phê duyệt thành công. Vui lòng có mặt đúng giờ để kỹ thuật viên của chúng tôi có thể phục vụ bạn tốt nhất.</p>

            <div class="details">
                <p><strong>Dịch vụ:</strong> {{ $appointment->service->name }}</p>
                <p><strong>Thời gian:</strong> {{ \Carbon\Carbon::parse($appointment->start_time)->format('H:i, d/m/Y') }}</p>
                <p><strong>Kỹ thuật viên:</strong> {{ $appointment->technician->name }}</p>
            </div>
            
            <p>Chúng tôi rất mong được đón tiếp bạn.</p>
            <a href="{{ url('/') }}" class="button">Truy cập website</a>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Tên Công Ty Của Bạn. Tất cả quyền được bảo lưu.</p>
        </div>
    </div>
</body>
</html>
