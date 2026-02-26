<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatutorySetup extends Model
{
    protected $fillable = [
        'sss_table',
        'sss_split_rule',
        'sss_ee_percent',
        'sss_er_percent',
        'ph_ee_percent',
        'ph_er_percent',
        'ph_min_cap',
        'ph_max_cap',
        'ph_split_rule',
        'pi_ee_percent',
        'pi_ee_percent_low',
        'pi_ee_threshold',
        'pi_er_percent',
        'pi_cap',
        'pi_split_rule',
    ];

    protected $casts = [
        'sss_table' => 'array',
    ];
}
