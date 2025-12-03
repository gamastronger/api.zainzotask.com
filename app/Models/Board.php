<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Board extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
    ];

    /**
     * Get the user that owns the board.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the columns for the board.
     */
    public function columns()
    {
        return $this->hasMany(Column::class)->orderBy('position');
    }
}
