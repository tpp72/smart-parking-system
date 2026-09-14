<?php

namespace App\Http\Controllers;

use App\Models\OwnerApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** เอกสารแนบคำขอเป็น Owner — เปิดได้เฉพาะผู้ยื่นคำขอและ Admin (project-plan.md §19.3) */
class OwnerApplicationDocumentController extends Controller
{
    public function show(OwnerApplication $ownerApplication)
    {
        $user = Auth::user();

        abort_unless($user->role === 'admin' || $ownerApplication->user_id === $user->id, 403, 'ไม่มีสิทธิ์เปิดเอกสารนี้');

        $disk = Storage::disk(OwnerApplication::DOCUMENT_DISK);

        abort_unless($ownerApplication->document_path && $disk->exists($ownerApplication->document_path), 404, 'ไม่พบเอกสาร');

        return $disk->response($ownerApplication->document_path);
    }
}
