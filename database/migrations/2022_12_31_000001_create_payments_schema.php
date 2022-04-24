<?php

use Eduka\Cube\Models\Course;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

/**
 * The migration filename date should be always after all the eduka migration
 * file dates. This file (or all the course migration files) should be the
 * last ones to be loaded into the database.
 *
 * E.g.: 2022_12_31_0000001_create_payments_schema
 *       2022_12_31_0000002_update_payments_schema_1
 *       2022_12_31_0000003_update_payments_schema_2
 *       ...
 */
class CreatePaymentsSchema extends Migration
{
    public function up()
    {
        $basePath = __DIR__.'/../../';
    }
}
