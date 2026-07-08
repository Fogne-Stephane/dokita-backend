<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::table('appointments', function (Blueprint $table) {
        $table->text('patient_notes')->nullable()->after('notes');
        $table->integer('rating')->nullable()->after('patient_notes');
    });
}

public function down(): void
{
    Schema::table('appointments', function (Blueprint $table) {
        $table->dropColumn(['patient_notes', 'rating']);
    });
}
};
