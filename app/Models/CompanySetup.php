<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetup extends Model
{
    protected $fillable = [
        'company_name',
        'company_tin',
        'company_address',
        'company_rdo',
        'company_contact',
        'company_email',
        'payroll_frequency',
        'currency',
    ];
}
