<?php

namespace App\Services\Academic;

use App\Support\ActorIdentity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class StickerLibraryService
{
    public function picker(string $userId, ?string $packId = null, bool $favorites = false, ?string $search = null): array
    {
        $packs = $this->connection()->table('MASTER_STICKER_PACK')
            ->where('Is_Active', true)->orderBy('Sort_Order')->orderBy('Name')->get();
        $query = $this->connection()->table('MASTER_STICKER as stickers')
            ->join('MASTER_STICKER_PACK as packs', 'packs.Sticker_Pack_ID', '=', 'stickers.Sticker_Pack_ID')
            ->leftJoin('USER_STICKER_FAVORITE as favorites', function ($join) use ($userId): void {
                $join->on('favorites.Sticker_ID', '=', 'stickers.Sticker_ID')->where('favorites.User_ID', '=', $userId);
            })
            ->where('stickers.Is_Active', true)->where('packs.Is_Active', true);
        if ($packId) {
            $query->where('stickers.Sticker_Pack_ID', $packId);
        }
        if ($favorites) {
            $query->whereNotNull('favorites.User_ID');
        }
        if ($search = trim((string) $search)) {
            $query->where('stickers.Name', 'like', '%'.addcslashes($search, '%_\\').'%');
        }
        $stickers = $query->orderBy('packs.Sort_Order')->orderBy('stickers.Sort_Order')->orderBy('stickers.Name')
            ->select('stickers.*', 'packs.Name as Pack_Name', DB::raw('CASE WHEN favorites.User_ID IS NULL THEN 0 ELSE 1 END as Is_Favorite'))
            ->paginate(48)->through(function ($sticker): object {
                $sticker->URL = Storage::disk('public')->url($sticker->File_Path);

                return $sticker;
            });

        return compact('packs', 'stickers');
    }

    public function favorite(string $userId, string $stickerId): void
    {
        abort_unless($this->connection()->table('MASTER_STICKER')->where('Sticker_ID', $stickerId)->where('Is_Active', true)->exists(), 422, 'Stiker tidak tersedia.');
        $this->connection()->table('USER_STICKER_FAVORITE')->insertOrIgnore([
            'User_ID' => $userId, 'Sticker_ID' => $stickerId,
            'Created_At' => now(AnnouncementService::TIMEZONE)->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function unfavorite(string $userId, string $stickerId): void
    {
        $this->connection()->table('USER_STICKER_FAVORITE')->where('User_ID', $userId)->where('Sticker_ID', $stickerId)->delete();
    }

    public function createPack(array $data): string
    {
        $id = 'STP-'.Str::upper((string) Str::ulid());
        $now = now(AnnouncementService::TIMEZONE)->format('Y-m-d H:i:s.u');
        $this->connection()->table('MASTER_STICKER_PACK')->insert([
            'Sticker_Pack_ID' => $id, 'Name' => trim($data['Name']),
            'Is_Active' => (bool) ($data['Is_Active'] ?? true), 'Sort_Order' => (int) ($data['Sort_Order'] ?? 0),
            'Created_At' => $now, 'Updated_At' => $now,
            'Created_By' => ActorIdentity::required(), 'Updated_By' => ActorIdentity::required(),
        ]);

        return $id;
    }

    public function updatePack(string $id, array $data): void
    {
        abort_unless($this->connection()->table('MASTER_STICKER_PACK')->where('Sticker_Pack_ID', $id)->exists(), 404);
        $this->connection()->table('MASTER_STICKER_PACK')->where('Sticker_Pack_ID', $id)->update([
            'Name' => trim($data['Name']), 'Is_Active' => (bool) ($data['Is_Active'] ?? false),
            'Sort_Order' => (int) ($data['Sort_Order'] ?? 0),
            'Updated_At' => now(AnnouncementService::TIMEZONE)->format('Y-m-d H:i:s.u'), 'Updated_By' => ActorIdentity::required(),
        ]);
    }

    public function uploadSticker(array $data, UploadedFile $file): string
    {
        abort_unless($this->connection()->table('MASTER_STICKER_PACK')->where('Sticker_Pack_ID', $data['Sticker_Pack_ID'])->exists(), 422, 'Pack stiker tidak valid.');
        $fileSize = (int) $file->getSize();
        abort_unless($file->isValid() && $fileSize > 0 && $fileSize <= 2 * 1024 * 1024, 422, 'Ukuran media harus antara 1 byte dan 2 MB.');
        $image = @getimagesize($file->getRealPath());
        $mime = $image['mime'] ?? '';
        $extensions = ['image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        abort_unless(isset($extensions[$mime]), 422, 'Media hanya mendukung PNG, WEBP, atau GIF.');
        abort_unless(
            isset($image[0], $image[1]) && $image[0] >= 32 && $image[0] <= 1024 && $image[1] >= 32 && $image[1] <= 1024,
            422,
            'Dimensi media harus antara 32 x 32 dan 1024 x 1024 piksel.',
        );
        $extension = $extensions[$mime];
        $id = 'STK-'.Str::upper((string) Str::ulid());
        $path = $file->storeAs('stickers/'.strtolower($data['Sticker_Pack_ID']), Str::uuid().'.'.$extension, 'public');
        try {
            $now = now(AnnouncementService::TIMEZONE)->format('Y-m-d H:i:s.u');
            $this->connection()->table('MASTER_STICKER')->insert([
                'Sticker_ID' => $id, 'Sticker_Pack_ID' => $data['Sticker_Pack_ID'], 'Name' => trim($data['Name']),
                'File_Path' => $path, 'Mime_Type' => $mime, 'File_Size' => $fileSize,
                'Width' => $image[0], 'Height' => $image[1], 'Is_Active' => (bool) ($data['Is_Active'] ?? true),
                'Sort_Order' => (int) ($data['Sort_Order'] ?? 0), 'Created_At' => $now, 'Updated_At' => $now,
                'Created_By' => ActorIdentity::required(), 'Updated_By' => ActorIdentity::required(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return $id;
    }

    public function updateSticker(string $id, array $data): void
    {
        abort_unless($this->connection()->table('MASTER_STICKER')->where('Sticker_ID', $id)->exists(), 404);
        $this->connection()->table('MASTER_STICKER')->where('Sticker_ID', $id)->update([
            'Name' => trim($data['Name']), 'Sticker_Pack_ID' => $data['Sticker_Pack_ID'],
            'Is_Active' => (bool) ($data['Is_Active'] ?? false), 'Sort_Order' => (int) ($data['Sort_Order'] ?? 0),
            'Updated_At' => now(AnnouncementService::TIMEZONE)->format('Y-m-d H:i:s.u'), 'Updated_By' => ActorIdentity::required(),
        ]);
    }

    public function adminLibrary(): array
    {
        return [
            'packs' => $this->connection()->table('MASTER_STICKER_PACK')->orderBy('Sort_Order')->orderBy('Name')->get(),
            'stickers' => $this->connection()->table('MASTER_STICKER as stickers')->join('MASTER_STICKER_PACK as packs', 'packs.Sticker_Pack_ID', '=', 'stickers.Sticker_Pack_ID')
                ->select('stickers.*', 'packs.Name as Pack_Name')->orderBy('packs.Sort_Order')->orderBy('stickers.Sort_Order')->get()
                ->map(function ($sticker): object {
                    $sticker->URL = Storage::disk('public')->url($sticker->File_Path);

                    return $sticker;
                }),
        ];
    }

    private function connection()
    {
        return DB::connection((string) config('wms_runtime.connection', config('database.default')));
    }
}
