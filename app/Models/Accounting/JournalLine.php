<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
