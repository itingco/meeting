<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable
{
    use Notifiable;
    protected $fillable = ['name', 'email', 'password', 'is_admin', 'can_view_history', 'can_manage_settings', 'is_active'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'password' => 'hashed', 'is_admin' => 'boolean', 'can_view_history' => 'boolean',
        'can_manage_settings' => 'boolean', 'is_active' => 'boolean',
    ];
}
