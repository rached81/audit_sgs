<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuditExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $results;
    protected $type;
    protected $efTable;
    protected $gdTable;

    public function __construct($results, $type, $efTable, $gdTable)
    {
        $this->results = collect($results);
        $this->type = $type;
        $this->efTable = $efTable;
        $this->gdTable = $gdTable;
    }

    public function collection()
    {
        return $this->results;
    }

    public function headings(): array
    {
        return [
            'ARTICLE', 
            'DESIGNATION',
            // EF
            'EF_INITIAL', 'EF_ENTREE', 'EF_SORTIE', 'EF_FINALE', 'EF_PUMP', 'EF_VALEUR',
            // GD
            'GD_INITIAL', 'GD_ENTREE', 'GD_SORTIE', 'GD_FINALE', 'GD_PUMP', 'GD_VALEUR',
            'ECART'
        ];
    }

    public function map($row): array
    {
        return [
            $row->ARTICLE,
            $row->designation,
            
            $row->ef_initial,
            $row->ef_entree,
            $row->ef_sortie,
            $row->ef_finale,
            $row->ef_pump,
            $row->ef_valeur,

            $row->gd_initial,
            $row->gd_entree,
            $row->gd_sortie,
            $row->gd_finale,
            $row->gd_pump,
            $row->gd_valeur,

            $row->ecart,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }
}
