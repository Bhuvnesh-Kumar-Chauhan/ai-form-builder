<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubmissionSeeder extends Seeder
{
    public function run()
    {
        $forms = Form::where('is_published', true)->get();

        foreach ($forms as $form) {
            // Create 5-10 submissions per form
            $count = rand(5, 10);

            for ($i = 0; $i < $count; $i++) {
                $this->createSubmission($form);
            }
        }
    }

    private function createSubmission($form)
    {
        $data = [];

        foreach ($form->fields as $field) {
            $data[$field->field_key] = $this->generateFieldValue($field);
        }

        FormSubmission::create([
            'form_id' => $form->id,
            'data' => $data,
            'ip_address' => $this->generateIP(),
            'user_agent' => $this->generateUserAgent(),
            'meta_data' => [
                'referrer' => 'https://google.com',
                'browser' => 'Chrome',
                'device' => 'Desktop',
            ],
            'is_spam' => rand(0, 10) > 8, // 20% chance of being spam
            'submitted_at' => now()->subDays(rand(0, 30)),
        ]);
    }

    private function generateFieldValue($field)
    {
        switch ($field->type) {
            case 'text':
                return $this->randomText(rand(5, 20));
            case 'email':
                return $this->randomEmail();
            case 'number':
                return rand(1, 100);
            case 'phone':
                return '+'.rand(1, 99).rand(1000000000, 9999999999);
            case 'textarea':
                return $this->randomText(rand(20, 100));
            case 'select':
                $options = $field->options->pluck('value')->toArray();

                return ! empty($options) ? $options[array_rand($options)] : null;
            case 'radio':
                $options = $field->options->pluck('value')->toArray();

                return ! empty($options) ? $options[array_rand($options)] : null;
            case 'checkbox':
                $options = $field->options->pluck('value')->toArray();

                return ! empty($options) ? [$options[array_rand($options)]] : [];
            case 'rating':
                return rand(1, 5);
            case 'date':
                return now()->subDays(rand(0, 365))->format('Y-m-d');
            case 'url':
                return 'https://example.com/'.Str::random(10);
            default:
                return 'Sample value';
        }
    }

    private function randomText($length)
    {
        $words = [
            'Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit',
            'sed', 'do', 'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore',
            'magna', 'aliqua', 'Ut', 'enim', 'ad', 'minim', 'veniam', 'quis', 'nostrud',
            'exercitation', 'ullamco', 'laboris', 'nisi', 'ut', 'aliquip', 'ex', 'ea',
            'commodo', 'consequat', 'Duis', 'aute', 'irure', 'dolor', 'in', 'reprehenderit',
        ];

        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $text .= $words[array_rand($words)].' ';
        }

        return trim($text);
    }

    private function randomEmail()
    {
        $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'example.com', 'test.com'];

        return Str::random(rand(5, 10)).'@'.$domains[array_rand($domains)];
    }

    private function generateIP()
    {
        return rand(1, 255).'.'.rand(0, 255).'.'.rand(0, 255).'.'.rand(0, 255);
    }

    private function generateUserAgent()
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        ];

        return $agents[array_rand($agents)];
    }
}
