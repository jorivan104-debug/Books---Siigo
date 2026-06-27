<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('integration', 50)->index();
            $table->string('operation', 50)->index();
            $table->string('status', 20)->index();
            $table->string('organization_id', 50)->nullable()->index();
            $table->string('external_id', 50)->nullable()->index();
            $table->string('siigo_invoice_id', 50)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('message')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
