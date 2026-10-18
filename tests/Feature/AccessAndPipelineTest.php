<?php
namespace Tests\Feature;
use App\Jobs\ProcessMeeting;
use App\Jobs\UploadChunkToDrive;
use App\Models\Meeting;
use App\Models\MeetingChunk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
class AccessAndPipelineTest extends TestCase
{
    use RefreshDatabase;
    private function user(string $name, array $access=[]): User
    {
        return User::create(array_merge(['name'=>$name,'email'=>strtolower($name).'@test.local','password'=>'Secur3Password!'], $access));
    }
    public function test_normal_user_cannot_open_history_of_other_users_or_settings(): void
    {
        $alice=$this->user('Alice'); $bob=$this->user('Bob');
        $meeting=Meeting::create(['uuid'=>(string)Str::uuid(),'user_id'=>$alice->id,'title'=>'Internal','status'=>'queued','started_at'=>now()]);
        $this->actingAs($bob)->get('/meetings/'.$meeting->uuid)->assertForbidden();
        $this->get('/settings/recipients')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/meetings')->assertOk();
    }
    public function test_history_only_permission_can_view_but_cannot_mutate_other_meetings(): void
    {
        $owner=$this->user('Owner'); $viewer=$this->user('Viewer',['can_view_history'=>true]);
        $meeting=Meeting::create(['uuid'=>(string)Str::uuid(),'user_id'=>$owner->id,'title'=>'Weekly','status'=>'queued','started_at'=>now()]);
        $this->actingAs($viewer)->get('/meetings/'.$meeting->uuid)->assertOk();
        $this->post('/meetings/'.$meeting->uuid.'/generate')->assertForbidden();
        $this->post('/meetings/'.$meeting->uuid.'/sync-drive')->assertForbidden();
        $this->get('/settings/recipients')->assertForbidden();
    }
    public function test_admin_may_manage_users_and_manual_drive_sync_dispatches_job(): void
    {
        $owner=$this->user('Admin',['is_admin'=>true,'can_view_history'=>true,'can_manage_settings'=>true]);
        $meeting=Meeting::create(['uuid'=>(string)Str::uuid(),'user_id'=>$owner->id,'title'=>'Sync','status'=>'queued','started_at'=>now()]);
        Storage::fake('local'); Storage::disk('local')->put('meetings/one/chunks/000000.part','bytes');
        $chunk=MeetingChunk::create(['meeting_id'=>$meeting->id,'sequence'=>0,'mime_type'=>'audio/webm','local_path'=>'meetings/one/chunks/000000.part','size_bytes'=>5,'drive_status'=>'disabled']);
        config(['meeting.drive_enabled'=>true]); Queue::fake();
        $this->actingAs($owner)->get('/users')->assertOk();
        $this->post('/meetings/'.$meeting->uuid.'/sync-drive')->assertRedirect();
        $this->assertEquals('pending',$chunk->fresh()->drive_status);
        Queue::assertPushed(UploadChunkToDrive::class,1);
    }
    public function test_manual_generate_is_diagnostic_when_ai_key_missing(): void
    {
        $owner=$this->user('Owner');
        $meeting=Meeting::create(['uuid'=>(string)Str::uuid(),'user_id'=>$owner->id,'title'=>'Sync','status'=>'queued','started_at'=>now(),'expected_chunks'=>1]);
        MeetingChunk::create(['meeting_id'=>$meeting->id,'sequence'=>0,'mime_type'=>'audio/webm','local_path'=>'any','size_bytes'=>3,'drive_status'=>'disabled']);
        config(['meeting.demo_ai'=>false,'services.openai.key'=>null]); Queue::fake();
        $this->actingAs($owner)->post('/meetings/'.$meeting->uuid.'/generate')->assertSessionHas('error');
        Queue::assertNotPushed(ProcessMeeting::class);
    }
}
