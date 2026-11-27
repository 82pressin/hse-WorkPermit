<?php

namespace App\Services;

use App\Models\PermitHistory;
use App\Models\PermitRequest;
use App\Models\User;
use App\Models\Approval;
use DomainException;
use Illuminate\Support\Facades\DB;

class PermitWorkflow
{
    private const HSE_ROLES = ['foreman_hse', 'spv_hse'];

    public function logSubmitted(PermitRequest $permit, User $user): void
    {
        $this->log($permit, $user, null, 'review_hse');
    }

    public function approveReview(PermitRequest $permit, User $user): void
    {
        $this->requireRole($user, self::HSE_ROLES);
        $this->requireStatus($permit, 'review_hse');

        if (! $permit->documents()->where('type', 'jsea')->exists()) {
            throw new DomainException('Dokumen JSEA belum diunggah, permohonan belum bisa diloloskan.');
        }

        $this->transition($permit, $user, 'menunggu_jsea');
    }

    public function returnForRevision(PermitRequest $permit, User $user, string $note): void
    {
        $this->requireRole($user, self::HSE_ROLES);
        $this->requireStatus($permit, 'review_hse');

        if (trim($note) === '') {
            throw new DomainException('Catatan revisi wajib diisi.');
        }

        $this->transition($permit, $user, 'perlu_revisi', $note);
    }

    public function resubmit(PermitRequest $permit, User $user): void
    {
        $this->requireStatus($permit, 'perlu_revisi');

        $isOwnerDept = $user->role === 'pemohon' && $user->department === $permit->department;
        if (! $isOwnerDept && $user->role !== 'admin') {
            throw new DomainException('Hanya pemohon dari departemen ini yang bisa mengajukan ulang.');
        }

        $this->transition($permit, $user, 'review_hse', null, [
            'revision_count' => $permit->revision_count + 1,
        ]);
    }

    private function transition(PermitRequest $permit, User $user, string $to, ?string $note = null, array $extra = []): void
    {
        DB::transaction(function () use ($permit, $user, $to, $note, $extra) {
            $from = $permit->status;

            if ($to === 'perlu_revisi') {
                $permit->approvals()
                ->whereNull('expired_at')
                ->update(['expired_at' => now()]);
            }
            $permit->update(['status' => $to] + $extra);
            $this->log($permit, $user, $from, $to, $note);
        });
    }

    private function log(PermitRequest $permit, User $user, ?string $from, string $to, ?string $note = null): void
    {
        PermitHistory::create([
            'permit_request_id' => $permit->id,
            'user_id' => $user->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }

    private function requireRole(User $user, array $roles): void
    {
        if (! in_array($user->role, $roles)) {
            throw new DomainException('Kamu tidak punya akses untuk aksi ini.');
        }
    }

    private function requireStatus(PermitRequest $permit, string $status): void
    {
        if ($permit->status !== $status) {
            throw new DomainException('Status permohonan tidak sesuai untuk aksi ini.');
        }
    }

    public function approveJsea(PermitRequest $permit, User $user): void
    {
        $this->requireRole($user, PermitRequest::JSEA_APPROVER_ROLES);
        $this->requireStatus($permit, 'menunggu_jsea');

        DB::transaction(function () use ($permit, $user) {
            if ($permit->hasActiveApprovalFrom('jsea', $user->role)) {
                throw new DomainException('Kamu sudah menyetujui JSEA ini.');
            }

            Approval::create([
                'permit_request_id' => $permit->id,
                'document_type' => 'jsea',
                'approver_role' => $user->role,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            $this->log($permit, $user, 'menunggu_jsea', 'menunggu_jsea', 'JSEA disetujui');

            $signed = $permit->approvals()->whereNull('expired_at')
                ->where('document_type', 'jsea')->pluck('approver_role');

            if (collect(PermitRequest::JSEA_APPROVER_ROLES)->diff($signed)->isEmpty()) {
                $this->transition($permit, $user, 'penyusunan_surat');
            }
        });
    }

    public function returnFromJsea(PermitRequest $permit, User $user, string $note): void
    {
        $this->requireRole($user, PermitRequest::JSEA_APPROVER_ROLES);
        $this->requireStatus($permit, 'menunggu_jsea');

        if (trim($note) === '') {
            throw new DomainException('Catatan revisi wajib diisi.');
        }

        $this->transition($permit, $user, 'perlu_revisi', $note);
    }
}