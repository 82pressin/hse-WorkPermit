<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermitRequest extends Model
{
    protected $guarded = [];   // longgar dulu supaya gampang belajar; nanti kita perketat

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_hot_work' => 'boolean',
        'is_work_at_height' => 'boolean',
        'is_confined_space' => 'boolean',
        'is_excavation' => 'boolean',
        'is_chemical' => 'boolean',
        'is_electrical' => 'boolean',
        'is_mechanical' => 'boolean',
        'issued_at' => 'datetime',
    ];

    // Bobot ini ILUSTRASI. Wajib divalidasi ke pembimbing HSE sebelum dipakai.
    public const RISK_WEIGHTS = [
        'is_hot_work' => 3,
        'is_work_at_height' => 3,
        'is_confined_space' => 3,
        'is_chemical' => 2,
        'is_excavation' => 2,
        'is_electrical' => 2,
        'is_mechanical' => 1,
    ];

    protected static function booted(): void
    {
        static::saving(function (PermitRequest $permit) {
            $permit->risk_score = $permit->calculateRiskScore();
            $permit->risk_level = static::levelFor($permit->risk_score);
        });
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function calculateRiskScore(): int
    {
        $score = 0;
        foreach (self::RISK_WEIGHTS as $field => $weight) {
            if ($this->{$field}) {
                $score += $weight;
            }
        }
        return $score;
    }

    public static function levelFor(int $score): string
    {
        return match (true) {
            $score >= 6 => 'tinggi',
            $score >= 3 => 'sedang',
            default => 'rendah',
        };
    }

    public function documents()
    {
        return $this->hasMany(PermitDocument::class);
    }
}
