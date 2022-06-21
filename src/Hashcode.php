<?php

namespace Eduka\Payments;

use Eduka\Payments\Models\Hashcode as HashcodeModel;
use Illuminate\Support\Str;

class Hashcode
{
    public static function __callStatic($method, $args)
    {
        return HashcodeService::new()->{$method}(...$args);
    }
}

/**
 * Manages hash codes for different types of purposes. Still, the lifecycle
 * is the same:
 * 1. The hashcode is generated and stored in the database.
 * 2. The hashcode can then be check if it exists and it not burnt.
 * 3. The hascode can be burnt, and then it cannot be used again.
 */
class HashcodeService
{
    protected $hashcode;

    public function __construct()
    {
        //
    }

    public static function new(...$args)
    {
        return new self(...$args);
    }

    public function with(string $hashcode)
    {
        $this->hashcode = $hashcode;

        $this->check();

        return $this;
    }

    public function create()
    {
        $this->hashcode = (string) Str::random(20);

        HashcodeModel::create(['code' => $this->hashcode]);

        return $this->hashcode;
    }

    public function get()
    {
        return $this->hashcode;
    }

    /**
     * Checks if an hashcode exists and it's NOT burnt.
     *
     * @param  string $hashcode [description]
     * @return [type]           [description]
     */
    public function exists()
    {
        return HashcodeModel::where('code', $this->hashcode)->exists();
    }

    public function existed()
    {
        return HashcodeModel::withTrashed()
                            ->firstWhere('code', $this->hashcode)
                            ->trashed();
    }

    /**
     * Burn hashcode. Basically soft deletes it.
     *
     * @param  string $hashcode
     * @return bool
     */
    public function burn()
    {
        // Means, it's not yet soft deleted.
        if ($this->exists($this->hashcode)) {
            HashcodeModel::where('code', $this->hashcode)->delete();
            return;
        }

        throw new \Exception('Hashcode already burnt. Security exception');
    }

    public function isBurnt()
    {
        return HashcodeModel::withTrashed()
                            ->firstWhere('code', $this->hashcode)
                            ->trashed();
    }

    public function revive()
    {
        HashcodeModel::withTrashed()
                     ->where('code', $this->hashcode)
                     ->restore();

        return $this;
    }

    protected function check()
    {
        $data = HashcodeModel::withTrashed()
                             ->firstWhere('code', $this->hashcode);


        if (!$data) {
            throw new \Exception('Hashcode unknown. Security exception');
        };
    }
}
