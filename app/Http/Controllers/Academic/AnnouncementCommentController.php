<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Services\Academic\AnnouncementCommentService;
use App\Services\Academic\ExternalGifSearchService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AnnouncementCommentController extends Controller
{
    public function __construct(
        private AnnouncementCommentService $comments,
        private ExternalGifSearchService $externalGifs,
    ) {}

    public function store(Request $request, string $announcementId)
    {
        $data = $request->validate([
            'Comment_Type' => 'required|in:TEXT,STICKER,GIF',
            'Comment_Text' => 'required_if:Comment_Type,TEXT|nullable|string|max:'.AnnouncementCommentService::MAX_TEXT_LENGTH,
            'Sticker_ID' => 'required_if:Comment_Type,STICKER|nullable|string|max:191',
            'External_GIF_ID' => 'required_if:Comment_Type,GIF|nullable|string|max:191|regex:/^[A-Za-z0-9_-]+$/',
            'Parent_Comment_ID' => 'nullable|string|max:191',
            'Mention_User_IDs' => 'prohibited_unless:Comment_Type,TEXT|nullable|array|max:'.AnnouncementCommentService::MAX_MENTIONS,
            'Mention_User_IDs.*' => 'string|max:191|distinct',
        ], [
            'Comment_Text.required_if' => 'Komentar tidak boleh kosong.',
            'Comment_Text.max' => 'Komentar maksimal '.AnnouncementCommentService::MAX_TEXT_LENGTH.' karakter.',
            'Sticker_ID.required_if' => 'Pilih stiker yang akan dikirim.',
            'External_GIF_ID.required_if' => 'Pilih GIF online yang akan dikirim.',
            'Mention_User_IDs.max' => 'Maksimal '.AnnouncementCommentService::MAX_MENTIONS.' mention per komentar.',
        ]);

        try {
            if ($data['Comment_Type'] === 'TEXT') {
                $this->comments->createText(
                    $announcementId,
                    $request->user(),
                    (string) ($data['Comment_Text'] ?? ''),
                    $data['Parent_Comment_ID'] ?? null,
                    $data['Mention_User_IDs'] ?? [],
                );
            } elseif ($data['Comment_Type'] === 'STICKER') {
                $this->comments->createSticker(
                    $announcementId,
                    $request->user(),
                    (string) ($data['Sticker_ID'] ?? ''),
                    $data['Parent_Comment_ID'] ?? null,
                );
            } else {
                $this->comments->createExternalGif(
                    $announcementId,
                    $request->user(),
                    (string) ($data['External_GIF_ID'] ?? ''),
                    $data['Parent_Comment_ID'] ?? null,
                );
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw ValidationException::withMessages([
                'Comment_Text' => $this->safeExceptionMessage($e, 'Komentar tidak dapat dikirim. Periksa kembali input Anda.'),
            ]);
        }

        return back()->with('success', empty($data['Parent_Comment_ID']) ? 'Komentar terkirim.' : 'Balasan terkirim.');
    }

    public function mentions(Request $request, string $announcementId)
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:50']);

        return response()->json([
            'results' => $this->comments->searchMentionCandidates($announcementId, $request->user(), $data['q']),
        ]);
    }

    public function gifs(Request $request, string $announcementId)
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:50']);
        $this->comments->assertCanInteract($announcementId, $request->user());

        return response()->json($this->externalGifs->search($data['q']));
    }

    public function destroy(Request $request, string $announcementId, string $commentId)
    {
        $this->comments->delete($announcementId, $commentId, $request->user());

        return back()->with('success', 'Komentar dihapus.');
    }
}
