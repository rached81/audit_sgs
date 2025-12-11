<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResEf2016 extends Model
{
    protected $table = 'RES_EF_2016';
    public $timestamps = false;
    protected $fillable = ['ARTICLE','DESIGNATION','INITIAL','ENTREE','SORTIE','FINALE','PUMP','VALEUR'];
}
