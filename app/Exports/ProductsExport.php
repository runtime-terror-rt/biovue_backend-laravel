<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithCustomCsvSettings
{
    public function collection()
    {
        return Product::all();
    }

    public function headings(): array
    {
        return [
            'name',
            'description',
            'category',
            'price',
            'redirect_url',
            'status',
            'image',
        ];
    }

    public function map($product): array
    {
        return [
            $product->name,
            str_replace(["\r", "\n"], ' ', $product->description), 
            str_replace(["\r", "\n"], ' ', $product->category),
            $product->price,
            $product->redirect_url,
            $product->status,
            $product->image,
        ];
    }

    
    public function getCsvSettings(): array
    {
        return [
            'use_bom' => true, 
            'enclosure' => '"',
        ];
    }
}
