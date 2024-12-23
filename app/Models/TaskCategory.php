<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskCategory extends Model
{
    protected $fillable = [
        'name',
        'has_dor_date',
        'has_batch',
        'has_claim',
        'has_time_range',
        'has_sheets',
        'has_email',
        'has_form'
    ];

    protected $casts = [
        'has_batch' => 'boolean',
        'has_claim' => 'boolean',
        'has_time_range' => 'boolean',
        'has_sheets' => 'boolean',
        'has_email' => 'boolean',
        'has_form' => 'boolean',
        'has_dor_date' => 'boolean'
    ];

    public function report_tasks()
    {
        return $this->hasMany(ReportTask::class);
    }

    public function tasks()
{
    return $this->hasMany(ReportTask::class);
}
}
