<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'email'];

    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function devices(): HasMany { return $this->hasMany(Device::class); }
    public function pairings(): HasMany { return $this->hasMany(DevicePairing::class); }
}
