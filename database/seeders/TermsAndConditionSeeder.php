<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TermsAndCondition;

class TermsAndConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TermsAndCondition::updateOrCreate(
            ['id' => 1], // একটাই ID ধরে রাখবে
            [
                'content' => [
                    'en' => "Welcome to our platform. By using our services, you agree to follow these terms and conditions.",
                    'bn' => "আমাদের প্ল্যাটফর্মে স্বাগতম। আমাদের সার্ভিস ব্যবহার করার মাধ্যমে আপনি এই শর্তাবলী মেনে চলতে সম্মত হচ্ছেন।"
                ],
                'is_active' => true,
            ]
        );
    }
}
