<?php

namespace Modules\Equipment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Equipment\Models\EquipmentType;

class EquipmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => ['en' => 'Candela GentleMax Pro', 'ar' => 'كانديلا جنتل ماكس برو'],
                'manufacturer' => 'Candela',
                'model' => 'GentleMax Pro',
                'category' => 'laser',
                'max_shots' => 5000000,
                'specifications' => [
                    'wavelength' => '755nm/1064nm',
                    'spot_size' => '1.5mm - 24mm',
                    'cooling' => 'DCD (Dynamic Cooling Device)',
                ],
            ],
            [
                'name' => ['en' => 'Alma Soprano ICE Platinum', 'ar' => 'ألما سوبرانو آيس بلاتينيوم'],
                'manufacturer' => 'Alma Lasers',
                'model' => 'Soprano ICE Platinum',
                'category' => 'laser',
                'max_shots' => 20000000,
                'specifications' => [
                    'wavelength' => '755nm/810nm/1064nm',
                    'technology' => 'SHR (Super Hair Removal)',
                    'cooling' => 'ICE Plus',
                ],
            ],
            [
                'name' => ['en' => 'Lumenis LightSheer', 'ar' => 'لومينيس لايت شير'],
                'manufacturer' => 'Lumenis',
                'model' => 'LightSheer Duet',
                'category' => 'laser',
                'max_shots' => 10000000,
                'specifications' => [
                    'wavelength' => '800nm',
                    'spot_size' => '9mm x 9mm / 22mm x 35mm',
                    'technology' => 'High Speed / ChillTip',
                ],
            ],
            [
                'name' => ['en' => 'Cynosure Elite+', 'ar' => 'سينوشور إليت+'],
                'manufacturer' => 'Cynosure',
                'model' => 'Elite+',
                'category' => 'laser',
                'max_shots' => 8000000,
                'specifications' => [
                    'wavelength' => '755nm/1064nm',
                    'technology' => 'Alexandrite & Nd:YAG',
                ],
            ],
            [
                'name' => ['en' => 'Ultherapy', 'ar' => 'ألثيرابي'],
                'manufacturer' => 'Merz Aesthetics',
                'model' => 'Ulthera System',
                'category' => 'hifu',
                'max_shots' => null,
                'specifications' => [
                    'technology' => 'Microfocused Ultrasound',
                    'depth' => '1.5mm, 3mm, 4.5mm',
                ],
            ],
            [
                'name' => ['en' => 'CoolSculpting Elite', 'ar' => 'كول سكالبتنج إليت'],
                'manufacturer' => 'Allergan',
                'model' => 'CoolSculpting Elite',
                'category' => 'cryolipolysis',
                'max_shots' => null,
                'specifications' => [
                    'technology' => 'Cryolipolysis',
                    'applicators' => 'Dual applicators',
                ],
            ],
            [
                'name' => ['en' => 'Morpheus8', 'ar' => 'مورفيوس 8'],
                'manufacturer' => 'InMode',
                'model' => 'Morpheus8',
                'category' => 'rf',
                'max_shots' => null,
                'specifications' => [
                    'technology' => 'Fractional RF Microneedling',
                    'depth' => 'Up to 8mm',
                ],
            ],
            [
                'name' => ['en' => 'HydraFacial MD', 'ar' => 'هيدرا فيشل'],
                'manufacturer' => 'HydraFacial',
                'model' => 'Syndeo',
                'category' => 'hydrafacial',
                'max_shots' => null,
                'specifications' => [
                    'technology' => 'Vortex-Fusion',
                    'steps' => 'Cleanse, Extract, Hydrate',
                ],
            ],
            [
                'name' => ['en' => 'Dermapen 4', 'ar' => 'ديرمابن 4'],
                'manufacturer' => 'Dermapen World',
                'model' => 'Dermapen 4',
                'category' => 'microneedling',
                'max_shots' => null,
                'specifications' => [
                    'speed' => '1920 punctures/second',
                    'depth' => '0.1mm - 2.5mm',
                ],
            ],
            [
                'name' => ['en' => 'Celluma PRO', 'ar' => 'سيلوما برو'],
                'manufacturer' => 'Celluma',
                'model' => 'Celluma PRO',
                'category' => 'led',
                'max_shots' => null,
                'specifications' => [
                    'wavelengths' => 'Blue 465nm, Red 640nm, Near-IR 880nm',
                    'technology' => 'LED Light Therapy',
                ],
            ],
        ];

        foreach ($types as $type) {
            EquipmentType::updateOrCreate(
                ['model' => $type['model']],
                $type
            );
        }
    }
}
