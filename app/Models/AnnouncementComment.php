<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnnouncementComment extends Model
{
    use SoftDeletes;

    protected $table = 'ANNOUNCEMENT_COMMENT';

    protected $primaryKey = 'Comment_ID';

    public $incrementing = false;

    public $timestamps = false;

    const DELETED_AT = 'Deleted_At';

    protected $guarded = ['Comment_ID', 'User_ID', 'Created_At', 'Updated_At', 'Deleted_At'];
}
