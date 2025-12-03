<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Column extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'board_id',
        'title',
        'color',
        'position',
    ];

    /**
     * Get the board that owns the column.
     */
    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Get the cards for the column.
     */
    public function cards()
    {
        return $this->hasMany(Card::class)->orderBy('position');
    }
}
