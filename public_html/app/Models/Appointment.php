<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_user_id',
        'technician_user_id',
        'service_id',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // Khóa ngoại
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // Mối quan hệ với Rating
    public function rating()
    {
        return $this->hasOne(Rating::class);
    }
}