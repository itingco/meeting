<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Meeting extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'title', 'status', 'started_at', 'ended_at', 'mime_type',
        'expected_chunks', 'transcript', 'notes', 'audio_path', 'document_path',
        'drive_folder_id', 'drive_status', 'drive_error', 'error_message', 'emailed_at',
        'process_stage', 'stage_updated_at',
    ];
    protected $casts = [
        'started_at' => 'datetime', 'ended_at' => 'datetime', 'emailed_at' => 'datetime',
        'stage_updated_at' => 'datetime', 'notes' => 'array',
    ];
    public function getRouteKeyName(): string { return 'uuid'; }
    public function user() { return $this->belongsTo(User::class); }
    public function chunks() { return $this->hasMany(MeetingChunk::class); }
}
