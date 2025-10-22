<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResGd2017 extends Model
{
    protected $table = 'RES_GD_2017';
    public $timestamps = false;
    protected $fillable = ['ARTICLE','DESIGNATION','INITIAL','ENTREE','SORTIE','FINALE','PUMP','VALEUR'];
}
