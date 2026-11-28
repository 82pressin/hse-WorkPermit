<?php

namespace App\Http\Controllers;

use App\Filament\Resources\PermitRequestResource;
use Barryvdh\DomPDF\Facade\Pdf;

class PermitPdfController extends Controller
{
    public function __invoke(int $permit)
    {
        abort_unless(auth()->check(), 403);

        // pakai query resource, jadi otomatis mengikuti aturan visibilitas per role
        $permit = PermitRequestResource::getEloquentQuery()->findOrFail($permit);

        $final = in_array($permit->status, ['diterbitkan', 'selesai']);
        $draft = in_array($permit->status, ['penyusunan_surat', 'menunggu_gm'])
            && in_array(auth()->user()->role, ['foreman_hse', 'spv_hse', 'gm', 'admin']);

        abort_unless($final || $draft, 404);

        $approval = $permit->approvals()
            ->whereNull('expired_at')
            ->where('document_type', 'surat_izin')
            ->with('approver')
            ->latest('id')
            ->first();

        $name = str_replace('/', '-', $permit->permit_number ?? 'draft-' . $permit->id);

        return Pdf::loadView('pdf.work-permit', [
            'permit' => $permit,
            'approval' => $approval,
            'isDraft' => ! $final,
        ])->setPaper('a4')->stream("Surat-Izin-Kerja-{$name}.pdf");
    }
}