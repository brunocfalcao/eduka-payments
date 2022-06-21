<?php

use Eduka\Cube\Models\Course;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * The migration filename date should be always after all the eduka migration
 * file dates. This file (or all the course migration files) should be the
 * last ones to be loaded into the database.
 *
 * E.g.: 2022_11_31_0000001_create_payments_schema
 *       2022_11_31_0000002_update_payments_schema_1
 *       2022_11_31_0000003_update_payments_schema_2
 *       ...
 */
class CreatePaymentsSchema extends Migration
{
    public function up()
    {
        Schema::create('hashcodes', function (Blueprint $table) {
            $table->id();
            $table->string('code');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();

            /**
             * All paddle default fields.
             */
            $fields = [
                'alert_id',
                'alert_name',
                'balance_currency',
                'balance_earnings',
                'balance_fee',
                'balance_gross',
                'balance_tax',
                'country',
                'coupon',
                'currency',
                'customer_name',
                'earnings',
                'email',
                'event_time',
                'fee',
                'ip',
                'marketing_consent',
                'order_id',
                'passthrough',
                'payment_method',
                'payment_tax',
                'product_id',
                'product_name',
                'quantity',
                'receipt_url',
                'sale_gross',
                'used_price_override',
                'p_signature',
            ];

            foreach ($fields as $field) {
                $table->text($field)
                      ->nullable();
            }

            $table->string('checkout_id')
                  ->unique();

            $table->foreignId('visitor_id')
                  ->nullable();

            $table->foreignId('user_id')
                  ->nullable();

            $table->boolean('is_processed')
                  ->default(false)
                  ->comment('When is processed, it means validations ok, and email sent');

            $table->string('error_message')
                  ->nullable()
                  ->comment('In case there is an error in the job, we can capture the message here');

            $table->timestamps();
        });

        $basePath = __DIR__.'/../../';
    }
}
