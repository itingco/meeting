<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IntegrationSetting extends Model
{
    protected $fillable = ['name', 'value'];
    protected $casts = ['value' => 'encrypted'];
}
