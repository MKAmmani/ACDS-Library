<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_name');
            $table->string('from_email')->nullable();
            $table->text('message');
            $table->enum('type', ['reference', 'acquisition', 'citation', 'support', 'other'])->default('reference');
            $table->enum('status', ['unread', 'read', 'replied', 'closed'])->default('unread');
            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
