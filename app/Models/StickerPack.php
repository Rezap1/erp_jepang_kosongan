<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StickerPack extends Model
{
    protected $table = 'MASTER_STICKER_PACK';

    protected $primaryKey = 'Sticker_Pack_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['Sticker_Pack_ID', 'Created_At', 'Updated_At', 'Created_By', 'Updated_By'];

    protected $casts = ['Is_Active' => 'boolean', 'Sort_Order' => 'integer'];
}
