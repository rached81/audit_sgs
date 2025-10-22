<?php

namespace App\Imports;

use App\Models\ResEf2017;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockImport implements OnEachRow, WithHeadingRow, SkipsEmptyRows
{
    protected $model;

    public function __construct($modelClass) { $this->model = $modelClass; }

    public function onRow(Row $row)
    {
        $r = $row->toArray();
        $map = fn($k) => $r[$k] ?? $r[strtolower($k)] ?? null;

        ($this->model)::create([
            'ARTICLE'     => (int)($map('ARTICLE') ?? $map('ARTCOD')),
            'DESIGNATION' => $map('Désignation') ?? $map('DESIGNATION'),
            'INITIAL'     => $map('Initial') ?? $map('DEPART'),
            'ENTREE'      => $map('Entrée') ?? $map('ENTREE'),
            'SORTIE'      => $map('Sortie'),
            'FINALE'      => $map('Finale') ?? $map('ACTUEL'),
            'PUMP'        => $map('PUMP') ?? $map('PUMP.2016'),
            'VALEUR'      => $map('Valeur'),
        ]);
    }
}
//
//class StockImport implements ToModel
//{
//    /**
//    * @param array $row
//    *
//    * @return \Illuminate\Database\Eloquent\Model|null
//    */
//    public function model(array $row)
//    {
//        return new ResEf2017([
//            //
//        ]);
//    }
//}
