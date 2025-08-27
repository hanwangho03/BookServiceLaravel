<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'password',
        'name',
        'phone_number',
        'email',
        'role_id',
    ];

    protected $hidden = [
        'password',
    ];

    // Khóa ngoại
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // Các mối quan hệ khác
    public function appointmentsAsCustomer()
    {
        return $this->hasMany(Appointment::class, 'customer_user_id');
    }

    public function appointmentsAsTechnician()
    {
        return $this->hasMany(Appointment::class, 'technician_user_id');
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }
}
