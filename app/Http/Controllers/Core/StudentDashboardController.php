<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Academic\AnnouncementCommentService;
use App\Services\Academic\StickerLibraryService;
use App\Services\Core\AvatarResolver;
use App\Services\Dashboard\StudentDashboardService;
use App\Support\ActorIdentity;
use Illuminate\Support\Facades\Auth;

class StudentDashboardController extends Controller
{
    public function __construct(
        protected StudentDashboardService $dashboardService,
        protected AnnouncementCommentService $commentService,
        protected StickerLibraryService $stickerLibrary,
        protected AvatarResolver $avatarResolver,
    ) {}

    public function index()
    {
        $data = $this->dashboardService->getDashboardData();
        $user = Auth::user();
        $announcements = collect($data['announcements'] ?? [])->values();

        if ($announcements->isNotEmpty()) {
            $announcementIds = $announcements->pluck('Announcement_ID');
            $counts = $this->commentService->counts($announcementIds);
            $latest = $this->commentService->latestForAnnouncements($announcementIds, $user, 3);
            $authors = $this->commentService->userProfiles($announcements->pluck('Created_By'));

            $announcements = $announcements->map(function ($announcement) use ($counts, $latest, $authors): array {
                $announcement = (array) $announcement;
                $id = (string) ($announcement['Announcement_ID'] ?? '');
                $authorId = trim((string) ($announcement['Created_By'] ?? ''));
                $author = $authors[$authorId] ?? null;
                $announcement['Comment_Count'] = $counts[$id] ?? 0;
                $announcement['Latest_Comments'] = $latest[$id] ?? collect();
                $announcement['Creator_Name'] = $author?->User_Name ?: $author?->Username ?: ($authorId ?: 'WMS');
                $announcement['Creator_Role'] = $author?->Role_Name ?: 'WMS';
                $announcement['Creator_Avatar_URL'] = $author?->Avatar_URL;

                return $announcement;
            });

            $data['announcementStickerLibrary'] = $this->stickerLibrary->picker(ActorIdentity::required());
            $data['announcementCommenterAvatar'] = $this->avatarResolver->resolve($user);
        } else {
            $data['announcementStickerLibrary'] = ['packs' => collect(), 'stickers' => collect()];
            $data['announcementCommenterAvatar'] = null;
        }
        $data['announcements'] = $announcements;

        return view('dashboard.student', $data);
    }
}
