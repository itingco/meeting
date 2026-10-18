<?php
namespace Tests\Feature;
use App\Jobs\ProcessMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
class MeetingFlowTest extends TestCase
{
    use RefreshDatabase;
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/meetings')->assertRedirect('/login');
    }
    public function test_chunk_upload_and_finish_dispatches_processing(): void
    {
        Storage::fake('local'); Queue::fake();
        $user = User::create(['name'=>'Tester','email'=>'tester@ingco.co.id','password'=>'verylongpassword123']);
        $this->actingAs($user);
        $uuid = $this->postJson('/meetings', ['title'=>'Test Internal Meeting'])->assertCreated()->json('uuid');
        $this->assertTrue(Str::isUuid($uuid));
        $file = UploadedFile::fake()->createWithContent('chunk.webm', 'mock media bytes');
        $this->post('/meetings/'.$uuid.'/chunks', [
            'sequence'=>0, 'mime_type'=>'audio/webm', 'audio'=>$file,
        ], ['Accept'=>'application/json'])->assertOk();
        $this->postJson('/meetings/'.$uuid.'/finish', ['expected_chunks'=>2])->assertStatus(409);
        $this->postJson('/meetings/'.$uuid.'/finish', ['expected_chunks'=>1])->assertOk()->assertJsonPath('status','queued');
        Queue::assertPushed(ProcessMeeting::class, 1);
    }
}
