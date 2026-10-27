<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pbb_objects', function (Blueprint $table) {
            $table->id();
            $table->string('nop', 24)->unique();
            $table->string('taxpayer_name');
            $table->timestamps();
        });
        Schema::create('pbb_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pbb_object_id')->constrained();
            $table->year('year');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('belum_bayar');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('pbb_bills');
        Schema::dropIfExists('pbb_objects');
    }
};