<?php

namespace Eduka\Payments\Commands;

use Eduka\Cube\Models\Variant;
use Illuminate\Console\Command;

class Test extends Command
{
    protected $signature = 'eduka-payments:test';

    protected $description = 'Just a test command';

    public function handle()
    {
        $variant = Variant::find(2);

        dd($variant->price());
    }
}
