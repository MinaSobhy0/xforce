<?php

namespace Modules\Services\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Services\Models\ParameterTemplate;

class ParameterTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            $this->getLaserHairRemovalTemplate(),
            $this->getIPLPhotoFacialTemplate(),
            $this->getBotoxInjectionTemplate(),
            $this->getDermalFillerTemplate(),
            $this->getMicroneedlingTemplate(),
            $this->getChemicalPeelTemplate(),
            $this->getBodyContouringTemplate(),
            $this->getSkinTighteningTemplate(),
        ];

        foreach ($templates as $template) {
            ParameterTemplate::updateOrCreate(
                ['template_code' => $template['template_code']],
                $template
            );
        }
    }

    protected function getLaserHairRemovalTemplate(): array
    {
        return [
            'tenant_id' => null,
            'template_name' => 'Laser Hair Removal',
            'template_code' => 'LASER_HAIR_REMOVAL',
            'description' => 'Standard parameters for laser hair removal treatments',
            'service_category' => 'laser',
            'is_system' => true,
            'is_active' => true,
            'parameters' => [
                [
                    'key' => 'wavelength',
                    'type' => 'select',
                    'label' => ['en' => 'Wavelength', 'ar' => 'الطول الموجي'],
                    'required' => true,
                    'unit' => 'nm',
                    'options' => [
                        ['value' => '755', 'label' => ['en' => '755nm (Alexandrite)', 'ar' => '755nm (الكسندريت)']],
                        ['value' => '810', 'label' => ['en' => '810nm (Diode)', 'ar' => '810nm (ديود)']],
                        ['value' => '1064', 'label' => ['en' => '1064nm (Nd:YAG)', 'ar' => '1064nm (ند:ياج)']],
                    ],
                    'help_text' => ['en' => 'Select based on skin type and hair color', 'ar' => 'اختر بناءً على نوع البشرة ولون الشعر'],
                ],
                [
                    'key' => 'fluence',
                    'type' => 'decimal',
                    'label' => ['en' => 'Fluence', 'ar' => 'كثافة الطاقة'],
                    'required' => true,
                    'unit' => 'J/cm²',
                    'min' => 5,
                    'max' => 60,
                    'step' => 0.5,
                    'default_value' => 20,
                ],
                [
                    'key' => 'pulse_duration',
                    'type' => 'number',
                    'label' => ['en' => 'Pulse Duration', 'ar' => 'مدة النبضة'],
                    'required' => true,
                    'unit' => 'ms',
                    'min' => 3,
                    'max' => 400,
                    'step' => 1,
                    'default_value' => 30,
                ],
                [
                    'key' => 'spot_size',
                    'type' => 'select',
                    'label' => ['en' => 'Spot Size', 'ar' => 'حجم البقعة'],
                    'required' => true,
                    'unit' => 'mm',
                    'options' => [
                        ['value' => '6', 'label' => ['en' => '6mm', 'ar' => '6مم']],
                        ['value' => '8', 'label' => ['en' => '8mm', 'ar' => '8مم']],
                        ['value' => '10', 'label' => ['en' => '10mm', 'ar' => '10مم']],
                        ['value' => '12', 'label' => ['en' => '12mm', 'ar' => '12مم']],
                        ['value' => '15', 'label' => ['en' => '15mm', 'ar' => '15مم']],
                        ['value' => '18', 'label' => ['en' => '18mm', 'ar' => '18مم']],
                    ],
                    'default_value' => '10',
                ],
                [
                    'key' => 'repetition_rate',
                    'type' => 'number',
                    'label' => ['en' => 'Repetition Rate', 'ar' => 'معدل التكرار'],
                    'required' => false,
                    'unit' => 'Hz',
                    'min' => 1,
                    'max' => 10,
                    'default_value' => 2,
                ],
                [
                    'key' => 'cooling_level',
                    'type' => 'select',
                    'label' => ['en' => 'Cooling Level', 'ar' => 'مستوى التبريد'],
                    'required' => true,
                    'options' => [
                        ['value' => 'low', 'label' => ['en' => 'Low', 'ar' => 'منخفض']],
                        ['value' => 'medium', 'label' => ['en' => 'Medium', 'ar' => 'متوسط']],
                        ['value' => 'high', 'label' => ['en' => 'High', 'ar' => 'عالي']],
                        ['value' => 'max', 'label' => ['en' => 'Maximum', 'ar' => 'أقصى']],
                    ],
                    'default_value' => 'high',
                ],
                [
                    'key' => 'total_pulses',
                    'type' => 'number',
                    'label' => ['en' => 'Total Pulses', 'ar' => 'إجمالي النبضات'],
                    'required' => false,
                    'unit' => 'pulses',
                    'min' => 0,
                    'max' => 10000,
                ],
                [
                    'key' => 'skin_type',
                    'type' => 'select',
                    'label' => ['en' => 'Fitzpatrick Skin Type', 'ar' => 'نوع البشرة (فيتزباتريك)'],
                    'required' => true,
                    'options' => [
                        ['value' => '1', 'label' => ['en' => 'Type I - Very Fair', 'ar' => 'النوع الأول - فاتح جداً']],
                        ['value' => '2', 'label' => ['en' => 'Type II - Fair', 'ar' => 'النوع الثاني - فاتح']],
                        ['value' => '3', 'label' => ['en' => 'Type III - Medium', 'ar' => 'النوع الثالث - متوسط']],
                        ['value' => '4', 'label' => ['en' => 'Type IV - Olive', 'ar' => 'النوع الرابع - زيتوني']],
                        ['value' => '5', 'label' => ['en' => 'Type V - Brown', 'ar' => 'النوع الخامس - بني']],
                        ['value' => '6', 'label' => ['en' => 'Type VI - Dark', 'ar' => 'النوع السادس - داكن']],
                    ],
                ],
                [
                    'key' => 'hair_color',
                    'type' => 'select',
                    'label' => ['en' => 'Hair Color', 'ar' => 'لون الشعر'],
                    'required' => false,
                    'options' => [
                        ['value' => 'black', 'label' => ['en' => 'Black', 'ar' => 'أسود']],
                        ['value' => 'dark_brown', 'label' => ['en' => 'Dark Brown', 'ar' => 'بني داكن']],
                        ['value' => 'brown', 'label' => ['en' => 'Brown', 'ar' => 'بني']],
                        ['value' => 'light_brown', 'label' => ['en' => 'Light Brown', 'ar' => 'بني فاتح']],
                        ['value' => 'blonde', 'label' => ['en' => 'Blonde', 'ar' => 'أشقر']],
                        ['value' => 'red', 'label' => ['en' => 'Red', 'ar' => 'أحمر']],
                        ['value' => 'gray', 'label' => ['en' => 'Gray/White', 'ar' => 'رمادي/أبيض']],
                    ],
                ],
                [
                    'key' => 'test_patch',
                    'type' => 'boolean',
                    'label' => ['en' => 'Test Patch Performed', 'ar' => 'تم إجراء اختبار الرقعة'],
                    'required' => false,
                    'default_value' => false,
                ],
                [
                    'key' => 'gel_applied',
                    'type' => 'boolean',
                    'label' => ['en' => 'Cooling Gel Applied', 'ar' => 'تم وضع جل التبريد'],
                    'required' => false,
                    'default_value' => true,
                ],
                [
                    'key' => 'passes',
                    'type' => 'number',
                    'label' => ['en' => 'Number of Passes', 'ar' => 'عدد التمريرات'],
                    'required' => false,
                    'min' => 1,
                    'max' => 5,
                    'default_value' => 2,
                ],
                [
                    'key' => 'treatment_notes',
                    'type' => 'textarea',
                    'label' => ['en' => 'Treatment Notes', 'ar' => 'ملاحظات العلاج'],
                    'required' => false,
                    'placeholder' => ['en' => 'Enter any observations or notes...', 'ar' => 'أدخل أي ملاحظات...'],
                ],
            ],
        ];
    }

    protected function getIPLPhotoFacialTemplate(): array
    {
        return [
            'tenant_id' => null,
            'template_name' => 'IPL PhotoFacial',
            'template_code' => 'IPL_PHOTOFACIAL',
            'description' => 'Standard parameters for IPL photofacial treatments',
            'service_category' => 'ipl',
            'is_system' => true,
            'is_active' => true,
            'parameters' => [
                [
                    'key' => 'wavelength_range',
                    'type' => 'select',
                    'label' => ['en' => 'Wavelength Filter', 'ar' => 'مرشح الطول الموجي'],
                    'required' => true,
                    'unit' => 'nm',
                    'options' => [
                        ['value' => '515-1200', 'label' => ['en' => '515-1200nm (General)', 'ar' => '515-1200nm (عام)']],
                        ['value' => '560-1200', 'label' => ['en' => '560-1200nm (Pigment)', 'ar' => '560-1200nm (تصبغ)']],
                        ['value' => '590-1200', 'label' => ['en' => '590-1200nm (Vascular)', 'ar' => '590-1200nm (أوعية)']],
                        ['value' => '640-1200', 'label' => ['en' => '640-1200nm (Hair)', 'ar' => '640-1200nm (شعر)']],
                    ],
                ],
                [
                    'key' => 'fluence',
                    'type' => 'decimal',
                    'label' => ['en' => 'Fluence', 'ar' => 'كثافة الطاقة'],
                    'required' => true,
                    'unit' => 'J/cm²',
                    'min' => 8,
                    'max' => 40,
                    'step' => 0.5,
                    'default_value' => 15,
                ],
                [
                    'key' => 'pulse_duration',
                    'type' => 'number',
                    'label' => ['en' => 'Pulse Duration', 'ar' => 'مدة النبضة'],
                    'required' => true,
                    'unit' => 'ms',
                    'min' => 2,
                    'max' => 50,
                    'default_value' => 15,
                ],
                [
                    'key' => 'pulse_delay',
                    'type' => 'number',
                    'label' => ['en' => 'Pulse Delay', 'ar' => 'تأخير النبضة'],
                    'required' => false,
                    'unit' => 'ms',
                    'min' => 10,
                    'max' => 100,
                    'default_value' => 30,
                ],
                [
                    'key' => 'double_pulse',
                    'type' => 'boolean',
                    'label' => ['en' => 'Double Pulse Mode', 'ar' => 'وضع النبضة المزدوجة'],
                    'required' => false,
                    'default_value' => true,
                ],
                [
                    'key' => 'cooling_temp',
                    'type' => 'number',
                    'label' => ['en' => 'Cooling Temperature', 'ar' => 'درجة التبريد'],
                    'required' => false,
                    'unit' => '°C',
                    'min' => 0,
                    'max' => 10,
                    'default_value' => 5,
                ],
                [
                    'key' => 'treatment_area',
                    'type' => 'select',
                    'label' => ['en' => 'Treatment Area', 'ar' => 'منطقة العلاج'],
                    'required' => true,
                    'options' => [
                        ['value' => 'face', 'label' => ['en' => 'Full Face', 'ar' => 'الوجه بالكامل']],
                        ['value' => 'neck', 'label' => ['en' => 'Neck', 'ar' => 'الرقبة']],
                        ['value' => 'decollete', 'label' => ['en' => 'Décolleté', 'ar' => 'منطقة الصدر']],
                        ['value' => 'hands', 'label' => ['en' => 'Hands', 'ar' => 'اليدين']],
                    ],
                ],
                [
                    'key' => 'total_pulses',
                    'type' => 'number',
                    'label' => ['en' => 'Total Pulses', 'ar' => 'إجمالي النبضات'],
                    'required' => false,
                    'unit' => 'pulses',
                    'min' => 0,
                    'max' => 5000,
                ],
                [
                    'key' => 'passes',
                    'type' => 'number',
                    'label' => ['en' => 'Number of Passes', 'ar' => 'عدد التمريرات'],
                    'required' => false,
                    'min' => 1,
                    'max' => 3,
                    'default_value' => 1,
                ],
            ],
        ];
    }

    protected function getBotoxInjectionTemplate(): array
    {
        return [
            'tenant_id' => null,
            'template_name' => 'Botox Injection',
            'template_code' => 'BOTOX_INJECTION',
            'description' => 'Standard parameters for botulinum toxin injections',
            'service_category' => 'injectable',
            'is_system' => true,
            'is_active' => true,
            'parameters' => [
                [
                    'key' => 'product_brand',
                    'type' => 'select',
                    'label' => ['en' => 'Product Brand', 'ar' => 'العلامة التجارية'],
                    'required' => true,
                    'options' => [
                        ['value' => 'botox', 'label' => ['en' => 'Botox (Allergan)', 'ar' => 'بوتوكس (أليرجان)']],
                        ['value' => 'dysport', 'label' => ['en' => 'Dysport (Galderma)', 'ar' => 'ديسبورت (جالديرما)']],
                        ['value' => 'xeomin', 'label' => ['en' => 'Xeomin (Merz)', 'ar' => 'زيومين (ميرز)']],
                    ],
                ],
                [
                    'key' => 'total_units',
                    'type' => 'number',
                    'label' => ['en' => 'Total Units', 'ar' => 'إجمالي الوحدات'],
                    'required' => true,
                    'unit' => 'units',
                    'min' => 1,
                    'max' => 200,
                ],
                [
                    'key' => 'dilution',
                    'type' => 'select',
                    'label' => ['en' => 'Dilution', 'ar' => 'التخفيف'],
                    'required' => true,
                    'options' => [
                        ['value' => '1ml', 'label' => ['en' => '1ml saline per 100U', 'ar' => '1 مل محلول ملحي لكل 100 وحدة']],
                        ['value' => '2ml', 'label' => ['en' => '2ml saline per 100U', 'ar' => '2 مل محلول ملحي لكل 100 وحدة']],
                        ['value' => '2.5ml', 'label' => ['en' => '2.5ml saline per 100U', 'ar' => '2.5 مل محلول ملحي لكل 100 وحدة']],
                        ['value' => '4ml', 'label' => ['en' => '4ml saline per 100U', 'ar' => '4 مل محلول ملحي لكل 100 وحدة']],
                    ],
                    'default_value' => '2.5ml',
                ],
                [
                    'key' => 'forehead_units',
                    'type' => 'number',
                    'label' => ['en' => 'Forehead (Units)', 'ar' => 'الجبهة (وحدات)'],
                    'required' => false,
                    'unit' => 'units',
                    'min' => 0,
                    'max' => 30,
                ],
                [
                    'key' => 'glabella_units',
                    'type' => 'number',
                    'label' => ['en' => 'Glabella (Units)', 'ar' => 'بين الحاجبين (وحدات)'],
                    'required' => false,
                    'unit' => 'units',
                    'min' => 0,
                    'max' => 30,
                ],
                [
                    'key' => 'crows_feet_units',
                    'type' => 'number',
                    'label' => ['en' => "Crow's Feet (Units)", 'ar' => 'خطوط حول العين (وحدات)'],
                    'required' => false,
                    'unit' => 'units',
                    'min' => 0,
                    'max' => 30,
                ],
                [
                    'key' => 'other_areas',
                    'type' => 'textarea',
                    'label' => ['en' => 'Other Treatment Areas', 'ar' => 'مناطق علاج أخرى'],
                    'required' => false,
                    'placeholder' => ['en' => 'Specify other areas and units...', 'ar' => 'حدد المناطق الأخرى والوحدات...'],
                ],
                [
                    'key' => 'batch_number',
                    'type' => 'text',
                    'label' => ['en' => 'Batch/Lot Number', 'ar' => 'رقم الدفعة'],
                    'required' => true,
                ],
                [
                    'key' => 'expiry_date',
                    'type' => 'date',
                    'label' => ['en' => 'Product Expiry Date', 'ar' => 'تاريخ انتهاء المنتج'],
                    'required' => true,
                ],
            ],
        ];
    }

    protected function getDermalFillerTemplate(): array
    {
        return [
            'tenant_id' => null,
            'template_name' => 'Dermal Filler',
            'template_code' => 'DERMAL_FILLER',
            'description' => 'Standard parameters for dermal filler injections',
            'service_category' => 'injectable',
            'is_system' => true,
            'is_active' => true,
            'parameters' => [
                [
                    'key' => 'product_brand',
                    'type' => 'select',
                    'label' => ['en' => 'Product Brand', 'ar' => 'العلامة التجارية'],
                    'required' => true,
                    'options' => [
                        ['value' => 'juvederm_ultra', 'label' => ['en' => 'Juvederm Ultra', 'ar' => 'جوفيديرم ألترا']],
                        ['value' => 'juvederm_voluma', 'label' => ['en' => 'Juvederm Voluma', 'ar' => 'جوفيديرم فولوما']],
                        ['value' => 'restylane', 'label' => ['en' => 'Restylane', 'ar' => 'ريستيلان']],
                        ['value' => 'belotero', 'label' => ['en' => 'Belotero', 'ar' => 'بيلوتيرو']],
                        ['value' => 'radiesse', 'label' => ['en' => 'Radiesse', 'ar' => 'راديس']],
                    ],
                ],
                [
                    'key' => 'total_volume',
                    'type' => 'decimal',
                    'label' => ['en' => 'Total Volume', 'ar' => 'الحجم الإجمالي'],
                    'required' => true,
                    'unit' => 'ml',
                    'min' => 0.1,
                    'max' => 10,
                    'step' => 0.1,
                ],
                [
                    'key' => 'treatment_area',
                    'type' => 'select',
                    'label' => ['en' => 'Treatment Area', 'ar' => 'منطقة العلاج'],
                    'required' => true,
                    'options' => [
                        ['value' => 'lips', 'label' => ['en' => 'Lips', 'ar' => 'الشفاه']],
                        ['value' => 'nasolabial', 'label' => ['en' => 'Nasolabial Folds', 'ar' => 'خطوط الابتسامة']],
                        ['value' => 'marionette', 'label' => ['en' => 'Marionette Lines', 'ar' => 'خطوط الماريونيت']],
                        ['value' => 'cheeks', 'label' => ['en' => 'Cheeks', 'ar' => 'الخدود']],
                        ['value' => 'chin', 'label' => ['en' => 'Chin', 'ar' => 'الذقن']],
                        ['value' => 'jawline', 'label' => ['en' => 'Jawline', 'ar' => 'خط الفك']],
                        ['value' => 'under_eyes', 'label' => ['en' => 'Under Eyes', 'ar' => 'تحت العين']],
                    ],
                ],
                [
                    'key' => 'injection_technique',
                    'type' => 'select',
                    'label' => ['en' => 'Injection Technique', 'ar' => 'تقنية الحقن'],
                    'required' => true,
                    'options' => [
                        ['value' => 'needle', 'label' => ['en' => 'Needle', 'ar' => 'إبرة']],
                        ['value' => 'cannula', 'label' => ['en' => 'Cannula', 'ar' => 'كانيولا']],
                    ],
                ],
                [
                    'key' => 'needle_gauge',
                    'type' => 'select',
                    'label' => ['en' => 'Needle/Cannula Gauge', 'ar' => 'حجم الإبرة/الكانيولا'],
                    'required' => false,
                    'options' => [
                        ['value' => '27g', 'label' => ['en' => '27G', 'ar' => '27G']],
                        ['value' => '30g', 'label' => ['en' => '30G', 'ar' => '30G']],
                        ['value' => '25g_cannula', 'label' => ['en' => '25G Cannula', 'ar' => '25G كانيولا']],
                        ['value' => '22g_cannula', 'label' => ['en' => '22G Cannula', 'ar' => '22G كانيولا']],
                    ],
                ],
                [
                    'key' => 'anesthesia',
                    'type' => 'select',
                    'label' => ['en' => 'Anesthesia Used', 'ar' => 'التخدير المستخدم'],
                    'required' => false,
                    'options' => [
                        ['value' => 'none', 'label' => ['en' => 'None', 'ar' => 'لا يوجد']],
                        ['value' => 'topical', 'label' => ['en' => 'Topical Cream', 'ar' => 'كريم موضعي']],
                        ['value' => 'dental_block', 'label' => ['en' => 'Dental Block', 'ar' => 'تخدير أسنان']],
                        ['value' => 'lidocaine_in_product', 'label' => ['en' => 'Lidocaine in Product', 'ar' => 'ليدوكايين في المنتج']],
                    ],
                    'default_value' => 'lidocaine_in_product',
                ],
                [
                    'key' => 'batch_number',
                    'type' => 'text',
                    'label' => ['en' => 'Batch/Lot Number', 'ar' => 'رقم الدفعة'],
                    'required' => true,
                ],
                [
                    'key' => 'expiry_date',
                    'type' => 'date',
                    'label' => ['en' => 'Product Expiry Date', 'ar' => 'تاريخ انتهاء المنتج'],
                    'required' => true,
                ],
            ],
        ];
    }

    protected function getMicroneedlingTemplate(): array
    {
        return [
            'tenant_id' => null,
            'template_name' => 'Microneedling',
            'template_code' => 'MICRONEEDLING',
            'description' => 'Standard parameters for microneedling treatments',
            'service_category' => 'skin_rejuvenation',
            'is_system' => true,
            'is_active' => true,
            'parameters' => [
                [
                    'key' => 'device_type',
                    'type' => 'select',
                    'label' => ['en' => 'Device Type', 'ar' => 'نوع الجهاز'],
                    'required' => true,
                    'options' => [
                        ['value' => 'dermapen', 'label' => ['en' => 'Dermapen', 'ar' => 'ديرمابن']],
                        ['value' => 'skinpen', 'label' => ['en' => 'SkinPen', 'ar' => 'سكين بن']],
                        ['value' => 'vivace', 'label' => ['en' => 'Vivace (RF)', 'ar' => 'فيفاس (RF)']],
                        ['value' => 'morpheus8', 'label' => ['en' => 'Morpheus8 (RF)', 'ar' => 'مورفيوس8 (RF)']],
                    ],
                ],
                [
                    'key' => 'needle_depth',
                    'type' => 'decimal',
                    'label' => ['en' => 'Needle Depth', 'ar' => 'عمق الإبرة'],
                    'required' => true,
                    'unit' => 'mm',
                    'min' => 0.25,
                    'max' => 3.0,
                    'step' => 0.25,
                    'default_value' => 1.0,
                ],
                [
                    'key' => 'speed',
                    'type' => 'select',
                    'label' => ['en' => 'Speed Setting', 'ar' => 'إعداد السرعة'],
                    'required' => true,
                    'options' => [
                        ['value' => 'low', 'label' => ['en' => 'Low', 'ar' => 'منخفض']],
                        ['value' => 'medium', 'label' => ['en' => 'Medium', 'ar' => 'متوسط']],
                        ['value' => 'high', 'label' => ['en' => 'High', 'ar' => 'عالي']],
                    ],
                    'default_value' => 'medium',
                ],
                [
                    'key' => 'treatment_area',
                    'type' => 'select',
                    'label' => ['en' => 'Treatment Area', 'ar' => 'منطقة العلاج'],
                    'required' => true,
                    'options' => [
                        ['value' => 'face', 'label' => ['en' => 'Full Face', 'ar' => 'الوجه بالكامل']],
                        ['value' => 'neck', 'label' => ['en' => 'Neck', 'ar' => 'الرقبة']],
                        ['value' => 'decollete', 'label' => ['en' => 'Décolleté', 'ar' => 'منطقة الصدر']],
                        ['value' => 'hands', 'label' => ['en' => 'Hands', 'ar' => 'اليدين']],
                        ['value' => 'scalp', 'label' => ['en' => 'Scalp', 'ar' => 'فروة الرأس']],
                        ['value' => 'body', 'label' => ['en' => 'Body (Stretch Marks)', 'ar' => 'الجسم (علامات التمدد)']],
                    ],
                ],
                [
                    'key' => 'serum_used',
                    'type' => 'text',
                    'label' => ['en' => 'Serum/Product Used', 'ar' => 'السيروم/المنتج المستخدم'],
                    'required' => false,
                    'placeholder' => ['en' => 'e.g., Hyaluronic acid, PRP, Growth factors', 'ar' => 'مثال: حمض الهيالورونيك، PRP، عوامل النمو'],
                ],
                [
                    'key' => 'prp_used',
                    'type' => 'boolean',
                    'label' => ['en' => 'PRP Used', 'ar' => 'تم استخدام PRP'],
                    'required' => false,
                    'default_value' => false,
                ],
                [
                    'key' => 'passes',
                    'type' => 'number',
                    'label' => ['en' => 'Number of Passes', 'ar' => 'عدد التمريرات'],
                    'required' => false,
                    'min' => 1,
                    'max' => 5,
                    'default_value' => 3,
                ],
                [
                    'key' => 'needle_cartridge_lot',
                    'type' => 'text',
                    'label' => ['en' => 'Needle Cartridge Lot #', 'ar' => 'رقم دفعة الإبر'],
                    'required' => false,
                ],
            ],
        ];
    }

    protected function getChemicalPeelTemplate(): array
    {
        return [
            'tenant_id' => null,
            'template_name' => 'Chemical Peel',
            'template_code' => 'CHEMICAL_PEEL',
            'description' => 'Standard parameters for chemical peel treatments',
            'service_category' => 'skin_rejuvenation',
            'is_system' => true,
            'is_active' => true,
            'parameters' => [
                [
                    'key' => 'peel_type',
                    'type' => 'select',
                    'label' => ['en' => 'Peel Type', 'ar' => 'نوع التقشير'],
                    'required' => true,
                    'options' => [
                        ['value' => 'glycolic', 'label' => ['en' => 'Glycolic Acid', 'ar' => 'حمض الجليكوليك']],
                        ['value' => 'salicylic', 'label' => ['en' => 'Salicylic Acid', 'ar' => 'حمض الساليسيليك']],
                        ['value' => 'lactic', 'label' => ['en' => 'Lactic Acid', 'ar' => 'حمض اللاكتيك']],
                        ['value' => 'mandelic', 'label' => ['en' => 'Mandelic Acid', 'ar' => 'حمض المندليك']],
                        ['value' => 'tca', 'label' => ['en' => 'TCA', 'ar' => 'TCA']],
                        ['value' => 'jessner', 'label' => ['en' => "Jessner's", 'ar' => 'جيسنر']],
                        ['value' => 'vi_peel', 'label' => ['en' => 'VI Peel', 'ar' => 'VI Peel']],
                    ],
                ],
                [
                    'key' => 'concentration',
                    'type' => 'select',
                    'label' => ['en' => 'Concentration', 'ar' => 'التركيز'],
                    'required' => true,
                    'options' => [
                        ['value' => '10', 'label' => ['en' => '10%', 'ar' => '10%']],
                        ['value' => '20', 'label' => ['en' => '20%', 'ar' => '20%']],
                        ['value' => '30', 'label' => ['en' => '30%', 'ar' => '30%']],
                        ['value' => '35', 'label' => ['en' => '35%', 'ar' => '35%']],
                        ['value' => '50', 'label' => ['en' => '50%', 'ar' => '50%']],
                        ['value' => '70', 'label' => ['en' => '70%', 'ar' => '70%']],
                    ],
                ],
                [
                    'key' => 'peel_depth',
                    'type' => 'select',
                    'label' => ['en' => 'Peel Depth', 'ar' => 'عمق التقشير'],
                    'required' => true,
                    'options' => [
                        ['value' => 'superficial', 'label' => ['en' => 'Superficial', 'ar' => 'سطحي']],
                        ['value' => 'medium', 'label' => ['en' => 'Medium', 'ar' => 'متوسط']],
                        ['value' => 'deep', 'label' => ['en' => 'Deep', 'ar' => 'عميق']],
                    ],
                    'default_value' => 'superficial',
                ],
                [
                    'key' => 'application_time',
                    'type' => 'number',
                    'label' => ['en' => 'Application Time', 'ar' => 'وقت التطبيق'],
                    'required' => true,
                    'unit' => 'minutes',
                    'min' => 1,
                    'max' => 30,
                    'default_value' => 5,
                ],
                [
                    'key' => 'layers_applied',
                    'type' => 'number',
                    'label' => ['en' => 'Number of Layers', 'ar' => 'عدد الطبقات'],
                    'required' => false,
                    'min' => 1,
                    'max' => 6,
                    'default_value' => 2,
                ],
                [
                    'key' => 'neutralized',
                    'type' => 'boolean',
                    'label' => ['en' => 'Neutralized', 'ar' => 'تم التعديل'],
                    'required' => false,
                    'default_value' => true,
                ],
                [
                    'key' => 'frosting_level',
                    'type' => 'select',
                    'label' => ['en' => 'Frosting Level', 'ar' => 'مستوى التبييض'],
                    'required' => false,
                    'options' => [
                        ['value' => 'none', 'label' => ['en' => 'None', 'ar' => 'لا يوجد']],
                        ['value' => 'level1', 'label' => ['en' => 'Level 1 (Erythema)', 'ar' => 'المستوى 1 (احمرار)']],
                        ['value' => 'level2', 'label' => ['en' => 'Level 2 (Pink/White)', 'ar' => 'المستوى 2 (وردي/أبيض)']],
                        ['value' => 'level3', 'label' => ['en' => 'Level 3 (White)', 'ar' => 'المستوى 3 (أبيض)']],
                    ],
                ],
            ],
        ];
    }

    protected function getBodyContouringTemplate(): array
    {
        return [
            'tenant_id' => null,
            'template_name' => 'Body Contouring',
            'template_code' => 'BODY_CONTOURING',
            'description' => 'Standard parameters for body contouring treatments',
            'service_category' => 'body',
            'is_system' => true,
            'is_active' => true,
            'parameters' => [
                [
                    'key' => 'device_type',
                    'type' => 'select',
                    'label' => ['en' => 'Device/Treatment Type', 'ar' => 'نوع الجهاز/العلاج'],
                    'required' => true,
                    'options' => [
                        ['value' => 'coolsculpting', 'label' => ['en' => 'CoolSculpting', 'ar' => 'كول سكلبتينج']],
                        ['value' => 'emsculpt', 'label' => ['en' => 'EMSculpt', 'ar' => 'إم سكلبت']],
                        ['value' => 'rf_body', 'label' => ['en' => 'RF Body Contouring', 'ar' => 'نحت الجسم بالترددات']],
                        ['value' => 'cavitation', 'label' => ['en' => 'Ultrasonic Cavitation', 'ar' => 'التجويف بالموجات']],
                        ['value' => 'lipo_laser', 'label' => ['en' => 'Lipo Laser', 'ar' => 'ليبو ليزر']],
                    ],
                ],
                [
                    'key' => 'treatment_area',
                    'type' => 'select',
                    'label' => ['en' => 'Treatment Area', 'ar' => 'منطقة العلاج'],
                    'required' => true,
                    'options' => [
                        ['value' => 'abdomen', 'label' => ['en' => 'Abdomen', 'ar' => 'البطن']],
                        ['value' => 'flanks', 'label' => ['en' => 'Flanks/Love Handles', 'ar' => 'الجانبين']],
                        ['value' => 'thighs_inner', 'label' => ['en' => 'Inner Thighs', 'ar' => 'الفخذ الداخلي']],
                        ['value' => 'thighs_outer', 'label' => ['en' => 'Outer Thighs', 'ar' => 'الفخذ الخارجي']],
                        ['value' => 'arms', 'label' => ['en' => 'Arms', 'ar' => 'الذراعين']],
                        ['value' => 'back', 'label' => ['en' => 'Back', 'ar' => 'الظهر']],
                        ['value' => 'chin', 'label' => ['en' => 'Double Chin', 'ar' => 'الذقن المزدوجة']],
                        ['value' => 'buttocks', 'label' => ['en' => 'Buttocks', 'ar' => 'الأرداف']],
                    ],
                ],
                [
                    'key' => 'duration',
                    'type' => 'number',
                    'label' => ['en' => 'Treatment Duration', 'ar' => 'مدة العلاج'],
                    'required' => true,
                    'unit' => 'minutes',
                    'min' => 15,
                    'max' => 120,
                    'default_value' => 35,
                ],
                [
                    'key' => 'intensity',
                    'type' => 'number',
                    'label' => ['en' => 'Intensity Level', 'ar' => 'مستوى الشدة'],
                    'required' => false,
                    'unit' => '%',
                    'min' => 10,
                    'max' => 100,
                    'default_value' => 70,
                ],
                [
                    'key' => 'temperature',
                    'type' => 'number',
                    'label' => ['en' => 'Temperature', 'ar' => 'درجة الحرارة'],
                    'required' => false,
                    'unit' => '°C',
                    'min' => -10,
                    'max' => 45,
                ],
                [
                    'key' => 'pre_measurements',
                    'type' => 'text',
                    'label' => ['en' => 'Pre-Treatment Measurements', 'ar' => 'القياسات قبل العلاج'],
                    'required' => false,
                    'placeholder' => ['en' => 'e.g., Waist: 32", Hips: 38"', 'ar' => 'مثال: الخصر: 82سم، الأرداف: 97سم'],
                ],
                [
                    'key' => 'contractions',
                    'type' => 'number',
                    'label' => ['en' => 'Muscle Contractions', 'ar' => 'انقباضات العضلات'],
                    'required' => false,
                    'help_text' => ['en' => 'For EMSculpt type treatments', 'ar' => 'لعلاجات نوع EMSculpt'],
                ],
            ],
        ];
    }

    protected function getSkinTighteningTemplate(): array
    {
        return [
            'tenant_id' => null,
            'template_name' => 'Skin Tightening',
            'template_code' => 'SKIN_TIGHTENING',
            'description' => 'Standard parameters for skin tightening treatments',
            'service_category' => 'skin_rejuvenation',
            'is_system' => true,
            'is_active' => true,
            'parameters' => [
                [
                    'key' => 'device_type',
                    'type' => 'select',
                    'label' => ['en' => 'Device Type', 'ar' => 'نوع الجهاز'],
                    'required' => true,
                    'options' => [
                        ['value' => 'ultherapy', 'label' => ['en' => 'Ultherapy (HIFU)', 'ar' => 'ألثيرابي (HIFU)']],
                        ['value' => 'thermage', 'label' => ['en' => 'Thermage (RF)', 'ar' => 'ثيرماج (RF)']],
                        ['value' => 'rf_microneedling', 'label' => ['en' => 'RF Microneedling', 'ar' => 'ديرما RF']],
                        ['value' => 'hifu', 'label' => ['en' => 'HIFU (Generic)', 'ar' => 'HIFU (عام)']],
                        ['value' => 'laser_skin_tightening', 'label' => ['en' => 'Laser Skin Tightening', 'ar' => 'شد الجلد بالليزر']],
                    ],
                ],
                [
                    'key' => 'treatment_depth',
                    'type' => 'select',
                    'label' => ['en' => 'Treatment Depth', 'ar' => 'عمق العلاج'],
                    'required' => true,
                    'unit' => 'mm',
                    'options' => [
                        ['value' => '1.5', 'label' => ['en' => '1.5mm (Dermis)', 'ar' => '1.5مم (الأدمة)']],
                        ['value' => '3.0', 'label' => ['en' => '3.0mm (Subcutaneous)', 'ar' => '3.0مم (تحت الجلد)']],
                        ['value' => '4.5', 'label' => ['en' => '4.5mm (SMAS)', 'ar' => '4.5مم (SMAS)']],
                    ],
                ],
                [
                    'key' => 'energy_level',
                    'type' => 'decimal',
                    'label' => ['en' => 'Energy Level', 'ar' => 'مستوى الطاقة'],
                    'required' => true,
                    'unit' => 'J',
                    'min' => 0.3,
                    'max' => 2.0,
                    'step' => 0.1,
                    'default_value' => 1.0,
                ],
                [
                    'key' => 'treatment_area',
                    'type' => 'select',
                    'label' => ['en' => 'Treatment Area', 'ar' => 'منطقة العلاج'],
                    'required' => true,
                    'options' => [
                        ['value' => 'face_full', 'label' => ['en' => 'Full Face', 'ar' => 'الوجه بالكامل']],
                        ['value' => 'face_lower', 'label' => ['en' => 'Lower Face', 'ar' => 'أسفل الوجه']],
                        ['value' => 'neck', 'label' => ['en' => 'Neck', 'ar' => 'الرقبة']],
                        ['value' => 'brow', 'label' => ['en' => 'Brow Lift', 'ar' => 'رفع الحاجب']],
                        ['value' => 'decollete', 'label' => ['en' => 'Décolleté', 'ar' => 'منطقة الصدر']],
                    ],
                ],
                [
                    'key' => 'total_lines',
                    'type' => 'number',
                    'label' => ['en' => 'Total Lines/Shots', 'ar' => 'إجمالي الخطوط/النبضات'],
                    'required' => false,
                    'min' => 50,
                    'max' => 2000,
                ],
                [
                    'key' => 'anesthesia',
                    'type' => 'select',
                    'label' => ['en' => 'Anesthesia', 'ar' => 'التخدير'],
                    'required' => false,
                    'options' => [
                        ['value' => 'none', 'label' => ['en' => 'None', 'ar' => 'لا يوجد']],
                        ['value' => 'topical', 'label' => ['en' => 'Topical', 'ar' => 'موضعي']],
                        ['value' => 'oral', 'label' => ['en' => 'Oral Sedation', 'ar' => 'تهدئة فموية']],
                    ],
                    'default_value' => 'topical',
                ],
                [
                    'key' => 'passes',
                    'type' => 'number',
                    'label' => ['en' => 'Number of Passes', 'ar' => 'عدد التمريرات'],
                    'required' => false,
                    'min' => 1,
                    'max' => 3,
                    'default_value' => 1,
                ],
            ],
        ];
    }
}
