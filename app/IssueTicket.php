<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IssueTicket extends Model
{

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by', 'id');
    }

    public function solvedBy()
    {
        return $this->belongsTo(User::class, 'solved_by', 'id');
    }
}
