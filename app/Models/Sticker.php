<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sticker extends Model
{
    protected $table = 'MASTER_STICKER';

    protected $primaryKey = 'Sticker_ID';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['Sticker_ID', 'File_Path', 'Mime_Type', 'File_Size', 'Width', 'Height', 'Created_At', 'Updated_At', 'Created_By', 'Updated_By'];

    protected $casts = ['Is_Active' => 'boolean', 'Sort_Order' => 'integer'];
}
