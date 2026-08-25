<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompanyAiProfileHistory extends Model
{
    use HasUuids;

    protected $table = 'company_ai_profile_histories';

    protected $fillable = [
        'id',
        'user_id',
        'company_id',
        'company_name',
        'tagline',
        'about_company',
        'sector',
        'established_year',
        'employee_count',
        'website',
        'email',
        'phone',
        'linkedin',
        'address',
        'vision',
        'mission',
        'competencies',
        'portfolios',
        'completeness_score',
    ];

    protected $casts = [
        'competencies' => 'array',
        'portfolios'   => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
