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
        $headers = ['ARTICLE', 'DESIGNATION'];
        
        if ($this->type === 'valeur') {
            $headers[] = 'EF Finale';
            $headers[] = 'EF Valeur';
            $headers[] = 'GD Finale';
            $headers[] = 'GD Valeur';
        } else {
            $headers[] = 'EF ' . ucfirst($this->type);
            $headers[] = 'GD ' . ucfirst($this->type);
        }
        
        $headers[] = 'Ecart';
        return $headers;
    }

    public function map($row): array
    {
        $mapped = [
            $row->ARTICLE,
            $row->designation,
        ];

        if ($this->type === 'valeur') {
            $mapped[] = $row->ef_finale;
            $mapped[] = $row->val_ef;
            $mapped[] = $row->gd_finale;
            $mapped[] = $row->val_gd;
        } else {
            $mapped[] = $row->val_ef;
            $mapped[] = $row->val_gd;
        }

        $mapped[] = $row->ecart;

        return $mapped;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }
}
