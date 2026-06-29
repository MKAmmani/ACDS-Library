<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the old flat inbox_messages table
        Schema::dropIfExists('inbox_messages');

        // Thread headers — one per conversation
        Schema::create('inbox_threads', function (Blueprint $table) {
            $table->id();

            // user_to_staff: member asks library staff
            // staff_to_admin: staff sends internal message to chief librarian / admin
            $table->enum('channel', ['user_to_staff', 'staff_to_admin']);

            $table->string('subject')->nullable();
            $table->enum('query_type', ['reference', 'acquisition', 'citation', 'support', 'other'])->nullable();

            // Who started the thread (user or staff)
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Friendly name when from_user_id is null (walk-in / phone / email patron)
            $table->string('from_name')->nullable();

            $table->enum('status', ['open', 'closed'])->default('open');

            // Role of the last person to send a message — lets us compute unread without a JOIN
            $table->string('last_sender_role')->nullable(); // 'user' | 'staff' | 'admin'

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        // Individual messages within a thread
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('inbox_threads')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name')->nullable(); // fallback when sender_id is null
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('inbox_threads');
    }
};
