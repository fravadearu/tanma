<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'report_date'];

    protected $casts = [
        'report_date' => 'date'
    ];

    public function tasks()
    {
        return $this->hasMany(ReportTask::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}