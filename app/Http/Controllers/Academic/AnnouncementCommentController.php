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
            'Comment_Text' => 'nullable|string|max:'.AnnouncementCommentService::MAX_TEXT_LENGTH,
            'Sticker_ID' => 'nullable|string|max:191',
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
