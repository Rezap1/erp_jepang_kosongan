<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Services\Academic\AnnouncementCommentService;
use Illuminate\Http\Request;

class AnnouncementCommentController extends Controller
{
    public function __construct(private AnnouncementCommentService $comments) {}

    public function store(Request $request, string $announcementId)
    {
        $data = $request->validate([
            'Comment_Type' => 'required|in:TEXT,STICKER',
            'Comment_Text' => 'required_if:Comment_Type,TEXT|nullable|string|max:'.AnnouncementCommentService::MAX_TEXT_LENGTH,
            'Sticker_ID' => 'required_if:Comment_Type,STICKER|nullable|string|max:191',
        ], [
            'Comment_Text.required_if' => 'Komentar tidak boleh kosong.',
            'Comment_Text.max' => 'Komentar maksimal '.AnnouncementCommentService::MAX_TEXT_LENGTH.' karakter.',
            'Sticker_ID.required_if' => 'Pilih stiker yang akan dikirim.',
        ]);

        if ($data['Comment_Type'] === 'TEXT') {
            $this->comments->createText($announcementId, $request->user(), (string) ($data['Comment_Text'] ?? ''));
        } else {
            $this->comments->createSticker($announcementId, $request->user(), (string) ($data['Sticker_ID'] ?? ''));
        }

        return back()->with('success', 'Komentar terkirim.');
    }

    public function destroy(Request $request, string $announcementId, string $commentId)
    {
        $this->comments->delete($announcementId, $commentId, $request->user());

        return back()->with('success', 'Komentar dihapus.');
    }
}
