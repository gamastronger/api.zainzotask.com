<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'column_id',
        'title',
        'description',
        'image',
        'due_date',
        'completed',
        'position',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'due_date' => 'date',
        'completed' => 'boolean',
    ];

    /**
     * Get the column that owns the card.
     */
    public function column()
    {
        return $this->belongsTo(Column::class);
    }

    /**
     * Get the labels for the card.
     */
    public function labels()
    {
        return $this->hasMany(CardLabel::class);
    }
}
