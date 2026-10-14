<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('installation_id', 64)->unique();
            $table->string('code', 8)->unique();
            $table->string('name', 100);
            $table->string('platform', 40)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->foreignUlid('device_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sync_operations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('device_id');
        });

        Schema::dropIfExists('devices');
    }
};
