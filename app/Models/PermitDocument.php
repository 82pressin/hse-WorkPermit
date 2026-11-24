<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermitDocument extends Model
{
    protected $guarded = [];

    public function permitRequest()
    {
        return $this->belongsTo(PermitRequest::class);
    }
}
