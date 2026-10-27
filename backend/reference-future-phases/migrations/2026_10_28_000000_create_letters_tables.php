<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('letter_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('form_schema_json');
            $table->timestamps();
        });
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('letter_type_id')->constrained();
            $table->json('data_json');
            $table->string('status')->default('diajukan');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('letters');
        Schema::dropIfExists('letter_types');
    }
};