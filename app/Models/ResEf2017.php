<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResEf2017 extends Model
{
    protected $table = 'RES_EF_2017';
    public $timestamps = false;
    protected $fillable = ['ARTICLE','DESIGNATION','INITIAL','ENTREE','SORTIE','FINALE','PUMP','VALEUR'];
}
