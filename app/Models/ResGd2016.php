<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResGd2016 extends Model
{
    protected $table = 'RES_GD_2016';
    public $timestamps = false;
    protected $fillable = ['ARTICLE','DESIGNATION','INITIAL','ENTREE','SORTIE','FINALE','PUMP','VALEUR'];
}
