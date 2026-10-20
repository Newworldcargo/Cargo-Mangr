<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AllowUnclassifiedDraftServices extends Migration
{
    public function up()
    {
        Schema::table('billing_services', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_id')->nullable()->change();
        });
    }

    public function down()
    {
        throw new RuntimeException('Unclassified reference services must be retained.');
    }
}
