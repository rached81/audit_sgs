<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockExport implements FromCollection, WithHeadings
{
    protected $query;
    protected $headings;

    public function __construct($query, $headings = [])
    {
        $this->query = $query;
        $this->headings = $headings;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->query->get();
    }

    public function headings(): array
    {
        return $this->headings ?: [
            'ARTICLE',
            'DESIGNATION',
            'INITIAL',
            'ENTREE',
            'SORTIE',
            'FINALE',
            'PUMP',
            'VALEUR',
        ];
    }
}
