<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ReturnCaseEvidence extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'return_case_evidence';

    protected $fillable = ['uploaded_by', 'kind', 'path', 'original_name', 'created_at'];
}
