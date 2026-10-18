<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id(); $table->uuid('uuid')->unique(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180); $table->string('status', 32)->default('recording')->index();
            $table->timestamp('started_at'); $table->timestamp('ended_at')->nullable();
            $table->string('mime_type', 100)->nullable(); $table->unsignedInteger('expected_chunks')->nullable();
            $table->longText('transcript')->nullable(); $table->json('notes')->nullable();
            $table->string('audio_path')->nullable(); $table->string('document_path')->nullable();
            $table->string('drive_folder_id')->nullable(); $table->string('drive_status', 32)->default('pending');
            $table->text('error_message')->nullable(); $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('meeting_chunks', function (Blueprint $table) {
            $table->id(); $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence'); $table->string('mime_type', 100); $table->string('local_path');
            $table->unsignedBigInteger('size_bytes'); $table->string('drive_file_id')->nullable();
            $table->string('drive_status', 25)->default('pending'); $table->text('drive_error')->nullable();
            $table->timestamps(); $table->unique(['meeting_id', 'sequence']);
        });
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->longText('value')->nullable(); $table->timestamps();
        });
        Schema::create('meeting_recipients', function (Blueprint $table) {
            $table->id(); $table->string('department', 35)->unique(); $table->string('email')->nullable();
            $table->boolean('enabled')->default(false); $table->boolean('is_executive')->default(false); $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('meeting_recipients'); Schema::dropIfExists('integration_settings');
        Schema::dropIfExists('meeting_chunks'); Schema::dropIfExists('meetings');
    }
};
