<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Academic\AnnouncementCommentService;
use App\Services\Academic\AnnouncementService;
use App\Services\Academic\StickerLibraryService;
use App\Support\ActorIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementFeedController extends Controller
{
    public function __construct(
        private AnnouncementService $announcements,
        private AnnouncementCommentService $comments,
        private StickerLibraryService $stickers,
    ) {}

    public function index(Request $request)
    {
        $rows = $this->announcements->getActiveForUser($request->user());
        $counts = $this->comments->counts($rows->pluck('Announcement_ID'));
        $rows = $rows->map(function ($row) use ($counts): array {
            $row = $this->present((array) $row);
            $row['Comment_Count'] = $counts[$row['Announcement_ID']] ?? 0;

            return $row;
        });

        return view('announcements.feed.index', ['announcements' => $rows]);
    }

    public function show(Request $request, string $id)
    {
        $announcement = $this->announcements->getVisibleForUser($id, $request->user());
        abort_unless($announcement, 403, 'Anda tidak memiliki akses ke pengumuman ini.');
        $announcement = $this->present($announcement);
        $comments = $this->comments->paginate($id, $request->user());
        $library = $this->stickers->picker(ActorIdentity::required());

        return view('announcements.feed.show', compact('announcement', 'comments', 'library'));
    }

    private function present(array $row): array
    {
        $row['Priority_Label'] = $this->announcements->priorityLabel($row['Priority'] ?? 'NORMAL');
        $row['Audience_Label'] = $this->announcements->audienceLabel($row);
        $row['Start_Label'] = $this->formatDate($this->announcements->startAt($row));
        $row['Expiry_Label'] = $this->formatDate($this->announcements->expiresAt($row));
        $creator = trim((string) ($row['Created_By'] ?? ''));
        $row['Creator_Name'] = $creator === '' ? 'WMS' : (DB::connection(config('wms_runtime.connection'))->table('MASTER_USER')->where('User_ID', $creator)->value('Full_Name') ?: $creator);

        return $row;
    }

    private function formatDate($date): string
    {
        return $date ? $date->locale('id')->translatedFormat('j F Y, H.i').' WIB' : '-';
    }
}
