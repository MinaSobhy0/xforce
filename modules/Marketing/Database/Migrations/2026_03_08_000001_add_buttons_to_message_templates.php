<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->jsonb('buttons_json')->nullable()->after('variables_json');
            $table->string('header_type')->nullable()->after('buttons_json'); // none, text, image, document
            $table->jsonb('header_content')->nullable()->after('header_type');
            $table->text('footer')->nullable()->after('header_content');
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn(['buttons_json', 'header_type', 'header_content', 'footer']);
        });
    }
};
