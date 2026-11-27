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

    public const STATUSES = [
        'review_hse' => 'Review HSE',
        'perlu_revisi' => 'Perlu Revisi',
        'menunggu_jsea' => 'Menunggu TTD JSEA',
        'penyusunan_surat' => 'Penyusunan Surat Izin',
        'menunggu_gm' => 'Menunggu TTD GM',
        'diterbitkan' => 'Diterbitkan',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];

    protected $attributes = [
        'status' => 'review_hse',
    ];

    public function histories()
    {
        return $this->hasMany(PermitHistory::class);
    }

    public function latestRevisionNote(): ?string
    {
        return $this->histories()->where('to_status', 'perlu_revisi')->latest('id')->value('note');
    }

    public const JSEA_APPROVER_ROLES = ['spv_hse', 'ka_dept_ga'];

    // Role di luar HSE yang tampilannya dibatasi
    public const RESTRICTED_ROLES = ['ka_dept_ga', 'gm'];
    
    // Status di mana tiap role punya giliran bertindak
    public const ACTIVE_STATUSES = [
        'foreman_hse' => ['review_hse', 'penyusunan_surat'],
        'spv_hse' => ['review_hse', 'menunggu_jsea', 'penyusunan_surat'],
        'ka_dept_ga' => ['menunggu_jsea'],
        'gm' => ['menunggu_gm'],
    ];

    public function approvals()
    {
        return $this->hasMany(Approval::class);
    }

    public function hasActiveApprovalFrom(string $type, string $role): bool
    {
        return $this->approvals()
            ->whereNull('expired_at')
            ->where('document_type', $type)
            ->where('approver_role', $role)
            ->exists();
    }
}
