<?php

namespace App\Models\Accounting;

use App\Laravue\Models\User;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'entry_date' => 'date:Y-m-d',
        'total' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class)->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReversal(): bool
    {
        return $this->reverses_entry_id !== null;
    }
}
