<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        if (!isset($row['name']) || empty(trim($row['name']))) {
            return null;
        }

        $imagePath = null;

        if (!empty($row['image']) && filter_var($row['image'], FILTER_VALIDATE_URL)) {
            try {
                $imageContent = @file_get_contents($row['image']);
                if ($imageContent) {
                    $fileName = 'product_' . time() . '_' . Str::random(10) . '.jpg';
                    $imagePath = 'products/' . $fileName;
                    Storage::disk('public')->put($imagePath, $imageContent);
                }
            } catch (\Exception $e) {
                \Log::error("Image download failed for " . $row['name'] . ": " . $e->getMessage());
            }
        }

        return new Product([
            'supplier_id'  => auth()->id(), 
            'name'         => $row['name'],
            'description'  => $row['description'] ?? null,
            'category'     => $row['category'],
            'price'        => $row['price'],
            'redirect_url' => $row['redirect_url'] ?? null,
            'status'       => $row['status'] ?? 'draft',
            'image'        => $imagePath,
        ]);
    }

    
    public function rules(): array
    {
        return [
            'name'     => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|in:fitness,nutrition,supplements',
            'price'    => 'sometimes|required|numeric',
        ];
    }

    public function prepareForValidation($data, $index)
    {
        if (empty($data['name'])) {
            return [];
        }
        return $data;
    }
}