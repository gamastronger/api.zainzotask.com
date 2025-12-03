<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardLabel extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_id',
        'label',
    ];

    /**
     * Get the card that owns the label.
     */
    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
