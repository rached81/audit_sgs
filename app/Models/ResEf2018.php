<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResEf2018 extends Model
{
    protected $table = 'RES_EF_2018';
    public $timestamps = false;
    protected $fillable = ['ARTICLE','DESIGNATION','INITIAL','ENTREE','SORTIE','FINALE','PUMP','VALEUR'];
}
