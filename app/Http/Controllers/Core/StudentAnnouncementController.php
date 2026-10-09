<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Academic\AnnouncementCommentService;
use App\Services\Academic\AnnouncementService;
use App\Services\Academic\StickerLibraryService;
use App\Services\Core\AvatarResolver;
use App\Services\Core\StudentService;
use App\Support\ActorIdentity;
use Illuminate\Support\Facades\Auth;

class StudentAnnouncementController extends Controller
{
    public function __construct(
        protected AnnouncementService $announcementService,
        protected StudentService $studentService,
        protected AnnouncementCommentService $commentService,
        protected StickerLibraryService $stickerLibrary,
        protected AvatarResolver $avatarResolver,
    ) {}

    public function index()
    {
        $announcements = $this->announcementService
            ->getActiveForUser(Auth::user())
            ->map(function ($announcement) {
                $announcement = (array) $announcement;
                $announcement['Priority_Label'] = $this->announcementService->priorityLabel($announcement['Priority'] ?? 'NORMAL');
                $announcement['Start_At_Label'] = $this->formatDate($this->announcementService->startAt($announcement));
                $announcement['Expires_At_Label'] = $this->formatDate($this->announcementService->expiresAt($announcement));

                return $announcement;
            })->values();

        return view('student.portal.announcements.index', compact('announcements'));
    }

    public function show(string $id)
    {
        $announcement = $this->announcementService->getVisibleForUser($id, Auth::user());
        abort_if(! $announcement, 403, 'Anda tidak memiliki akses ke pengumuman ini.');
        $announcement['Priority_Label'] = $this->announcementService->priorityLabel($announcement['Priority'] ?? 'NORMAL');
        $announcement['Audience_Label'] = $this->announcementService->audienceLabel($announcement);
        $announcement['Start_At_Label'] = $this->formatDate($this->announcementService->startAt($announcement));
        $announcement['Expires_At_Label'] = $this->formatDate($this->announcementService->expiresAt($announcement));
        $comments = $this->commentService->paginate($id, Auth::user());
        $library = $this->stickerLibrary->picker(ActorIdentity::required());
        $currentUserAvatar = $this->avatarResolver->resolve(Auth::user());

        return view('student.portal.announcements.show', compact('announcement', 'comments', 'library', 'currentUserAvatar'));
    }

    protected function currentStudent(): array
    {
        $user = Auth::user();
        abort_if(! $user, 403, 'Profil siswa tidak ditemukan.');
        $userId = $user->User_ID ?? Auth::id();
        $student = collect($this->studentService->getAllStudents())->firstWhere('User_ID', $userId);
        abort_if(! $student || empty($student['Student_ID']), 403, 'Profil siswa tidak ditemukan.');

        return (array) $student;
    }

    protected function formatDate($date): string
    {
        return $date ? $date->locale('id')->translatedFormat('j F Y, H.i').' WIB' : '-';
    }
}
