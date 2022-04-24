<?php

use Eduka\Cube\Models\Course;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

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
        Schema::create('payments_webbook', function (Blueprint $table) {
            dd('here');
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
                'checkout_id',
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

            $table->timestamps();

            $table->engine = 'InnoDB';
        });

        $basePath = __DIR__.'/../../';
    }
}
