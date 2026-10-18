<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->boolean('can_view_history')->default(false);
            $table->boolean('can_manage_settings')->default(false);
            $table->boolean('is_active')->default(true);
        });
        // Akun paling awal sudah dipakai sebagai admin sebelum fitur hak akses dibuat.
        // Akun lain tetap tanpa hak tambahan sampai administrator mengaturnya.
        $firstId = DB::table('users')->orderBy('id')->value('id');
        if ($firstId) DB::table('users')->where('id', $firstId)->update([
            'is_admin' => true, 'can_view_history' => true, 'can_manage_settings' => true,
        ]);
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('process_stage', 40)->default('recording');
            $table->timestamp('stage_updated_at')->nullable();
            $table->text('drive_error')->nullable();
        });
        DB::table('meetings')->where('status', 'queued')->update(['process_stage'=>'queued']);
        DB::table('meetings')->where('status', 'completed')->update(['process_stage'=>'completed']);
        DB::table('meetings')->where('status', 'failed')->update(['process_stage'=>'failed']);
        DB::table('meetings')->where('status', 'processing')->update(['process_stage'=>'processing']);
    }
    public function down(): void {
        Schema::table('meetings', fn(Blueprint $table) => $table->dropColumn(['process_stage','stage_updated_at','drive_error']));
        Schema::table('users', fn(Blueprint $table) => $table->dropColumn(['is_admin','can_view_history','can_manage_settings','is_active']));
    }
};
