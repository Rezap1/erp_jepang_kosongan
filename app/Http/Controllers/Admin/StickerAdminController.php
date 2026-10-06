<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Academic\StickerLibraryService;
use Illuminate\Http\Request;

class StickerAdminController extends Controller
{
    public function __construct(private StickerLibraryService $stickers) {}

    public function index()
    {
        return view('admin.stickers.index', $this->stickers->adminLibrary());
    }

    public function storePack(Request $request)
    {
        $data = $request->validate(['Name' => 'required|string|max:120', 'Sort_Order' => 'nullable|integer|min:0|max:9999', 'Is_Active' => 'nullable|boolean']);
        $this->stickers->createPack($data);

        return back()->with('success', 'Pack stiker dibuat.');
    }

    public function updatePack(Request $request, string $id)
    {
        $data = $request->validate(['Name' => 'required|string|max:120', 'Sort_Order' => 'nullable|integer|min:0|max:9999', 'Is_Active' => 'nullable|boolean']);
        $this->stickers->updatePack($id, $data);

        return back()->with('success', 'Pack stiker diperbarui.');
    }

    public function storeSticker(Request $request)
    {
        $data = $request->validate([
            'Sticker_Pack_ID' => 'required|string|max:191', 'Name' => 'required|string|max:120',
            'Sort_Order' => 'nullable|integer|min:0|max:9999', 'Is_Active' => 'nullable|boolean',
            'Sticker_File' => 'required|file|mimes:png,webp|max:2048|dimensions:min_width=32,min_height=32,max_width=1024,max_height=1024',
        ]);
        $this->stickers->uploadSticker($data, $request->file('Sticker_File'));

        return back()->with('success', 'Stiker diunggah.');
    }

    public function updateSticker(Request $request, string $id)
    {
        $data = $request->validate([
            'Sticker_Pack_ID' => 'required|string|max:191', 'Name' => 'required|string|max:120',
            'Sort_Order' => 'nullable|integer|min:0|max:9999', 'Is_Active' => 'nullable|boolean',
        ]);
        $this->stickers->updateSticker($id, $data);

        return back()->with('success', 'Stiker diperbarui.');
    }
}
