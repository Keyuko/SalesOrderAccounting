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
        Schema::create('vehicle_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->string('no_pol', 20)->nullable();
            $table->string('driver_name', 50)->nullable();
            
            // Checklist fields
            $table->string('memo', 10)->nullable();
            $table->string('sim', 10)->nullable();
            $table->string('stnk', 10)->nullable();
            $table->string('kir', 10)->nullable();
            $table->string('segitiga', 10)->nullable();
            $table->string('apar', 10)->nullable();
            $table->string('apd', 10)->nullable();
            $table->string('p3k', 10)->nullable();
            $table->string('kondisi_ban', 10)->nullable();
            $table->string('ban_cadangan', 10)->nullable();
            $table->string('dongkrak', 10)->nullable();
            $table->string('kunci_std', 10)->nullable();
            $table->string('sabuk', 10)->nullable();
            $table->string('sertifikat', 10)->nullable();
            $table->string('sopan', 10)->nullable();
            $table->string('lampu', 10)->nullable();
            $table->string('wiper', 10)->nullable();
            $table->string('spion', 10)->nullable();
            $table->string('b3', 10)->nullable();
            $table->string('surat_jln', 10)->nullable();
            $table->string('muatan_aman', 15)->nullable();
            $table->string('isi_bagasi', 10)->nullable();

            // Notes fields
            $table->string('note_memo', 200)->nullable();
            $table->string('note_sim', 200)->nullable();
            $table->string('note_stnk', 200)->nullable();
            $table->string('note_kir', 200)->nullable();
            $table->string('note_s3', 200)->nullable();
            $table->string('note_apr', 200)->nullable();
            $table->string('note_apd', 200)->nullable();
            $table->string('note_pp', 200)->nullable();
            $table->string('note_ban', 200)->nullable();
            $table->string('note_cad', 200)->nullable();
            $table->string('note_dong', 200)->nullable();
            $table->string('note_std', 200)->nullable();
            $table->string('note_sab', 200)->nullable();
            $table->string('note_dep', 200)->nullable(); // Added from the SQL missing fields
            $table->string('note_sop', 200)->nullable();
            $table->string('catatan', 1000)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_checklists');
    }
};
