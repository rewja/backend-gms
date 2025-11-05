<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class UserTemplateExport implements FromCollection, WithHeadings, WithColumnWidths, WithStyles, WithTitle
{
    public function collection()
    {
        // Return collection with one empty row as template
        // This ensures Excel file is valid and users can start filling from row 2
        return new Collection([
            ['', '', '', '', '', '', ''] // Empty row for data entry
        ]);
    }

    public function headings(): array
    {
        return [
            'Nama',
            'Email',
            'Password',
            'Role',
            'Kategori',
            'Departemen',
            'Jabatan',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25, // Nama
            'B' => 30, // Email
            'C' => 20, // Password
            'D' => 15, // Role
            'E' => 15, // Kategori
            'F' => 20, // Departemen
            'G' => 20, // Jabatan
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style header row (row 1)
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Set row height for header
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [];
    }

    public function title(): string
    {
        return 'Template Import Pengguna';
    }
}

