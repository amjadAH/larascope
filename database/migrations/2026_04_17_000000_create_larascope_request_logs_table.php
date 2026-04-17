<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function tableName(): string
    {
        return config('larascope.database.table');
    }

    private function connectionName(): ?string
    {
        return config('larascope.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->connectionName())->create($this->tableName(), function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->text('url');
            $table->string('path', 1000);
            $table->string('route_name', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->decimal('duration_ms', 10, 2);
            $table->decimal('memory_peak_mb', 10, 4);
            $table->unsignedInteger('query_count')->default(0);
            $table->json('queries')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->dropIfExists($this->tableName());
    }
};
