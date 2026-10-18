<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MeetingChunk extends Model
{
    protected $fillable = ['meeting_id', 'sequence', 'mime_type', 'local_path', 'size_bytes', 'drive_file_id', 'drive_status', 'drive_error'];
    public function meeting() { return $this->belongsTo(Meeting::class); }
}
