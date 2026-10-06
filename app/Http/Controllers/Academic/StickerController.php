<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Services\Academic\StickerLibraryService;
use App\Support\ActorIdentity;
use Illuminate\Http\Request;

class StickerController extends Controller
{
    public function __construct(private StickerLibraryService $stickers) {}

    public function index(Request $request)
    {
        return response()->json($this->stickers->picker(
            ActorIdentity::required(),
            $request->string('pack')->toString() ?: null,
            $request->boolean('favorites'),
            $request->string('search')->toString() ?: null,
        ));
    }

    public function favorite(string $id)
    {
        $this->stickers->favorite(ActorIdentity::required(), $id);

        return back();
    }

    public function unfavorite(string $id)
    {
        $this->stickers->unfavorite(ActorIdentity::required(), $id);

        return back();
    }
}
