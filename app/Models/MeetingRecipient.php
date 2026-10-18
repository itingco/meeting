<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MeetingRecipient extends Model
{
    protected $fillable = ['department', 'email', 'enabled', 'is_executive'];
    protected $casts = ['enabled' => 'boolean', 'is_executive' => 'boolean'];
}
