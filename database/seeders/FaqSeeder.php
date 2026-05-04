<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Why does my Wellness Score change every day?',
                'answer' => 'Your score is dynamic. Daily actions—like drinking less water or missing a workout—directly influence the calculation. It is a live reflection of your current lifestyle choices.',
                'is_active' => true,
            ],
            [
                'question' => 'How can I improve my score quickly?',
                'answer' => 'Meeting your Hydration and Sleep targets are the fastest ways to see a boost. Consistency in logging your daily activities also ensures the algorithm has enough data to reward your progress.',
                'is_active' => true,
            ],
            [
                'question' => 'What happens if I forget to input data for a day?',
                'answer' => 'If data is missing, the system uses a Baseline average, which may temporarily lower your score. We recommend consistent check-ins to keep your score accurate.',
                'is_active' => true,
            ],
            [
                'question' => 'Does having a medical condition (e.g., Asthma or Thyroid) automatically lower my score?',
                'answer' => 'No. Having a medical condition does not mean a low score. The system measures how well you are living a healthy life with your specific profile. Managing your habits effectively will still result in a high Wellness Score.',
                'is_active' => true,
            ],
            [
                'question' => 'Is the Wellness Score a medical diagnosis?',
                'answer' => 'No. The Wellness Score is a digital tool designed for lifestyle tracking and motivation. It should not be used as a replacement for professional medical advice or clinical reports.',
                'is_active' => true,
            ],
            [
                'question' => 'Where can I find a tutorial on how to use BioVue?',
                'answer' => 'Please visit our YouTube channel for a complete guide to BioVue dashboards: https://youtube.com/@biovuedigitalwellness. You can also find the YouTube link in the Footer.',
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']], // unique condition
                [
                    'answer' => $faq['answer'],
                    'is_active' => $faq['is_active'],
                ]
            );
        }
    }
}