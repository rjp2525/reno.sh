<?php

namespace Database\Seeders;

use App\Enums\PrintProductType;
use App\Models\PrintProduct;
use Illuminate\Database\Seeder;

/**
 * Starter print catalog. Products are created INACTIVE on purpose: confirm each
 * SKU with the "Verify" action in /admin/print-products (or the Prodigi
 * dashboard), adjust prices, then switch them on.
 */
class PrintProductSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            // 3:2 (most DSLR/mirrorless frames)
            [8, 12], [12, 18], [16, 24], [20, 30], [24, 36],
            // 4:5 and 4:3
            [8, 10], [16, 20], [12, 16], [18, 24],
        ];

        $lines = [
            [PrintProductType::FINE_ART, 'Fine Art Print', 'GLOBAL-HPR', 'Hahnemühle Photo Rag 308gsm, archival pigment inks. Ships rolled in a tube.', 150, 1.0, null],
            [PrintProductType::CANVAS, 'Stretched Canvas', 'GLOBAL-CAN', 'Gallery-wrapped canvas on a 38mm stretcher bar, ready to hang.', 100, 1.6, ['wrap' => 'ImageWrap']],
            [PrintProductType::FRAMED, 'Framed Print', 'GLOBAL-CFPM', 'Classic wooden frame with mount and shatterproof glaze.', 150, 2.2, ['color' => 'black']],
        ];

        $sort = 0;
        foreach ($lines as [$type, $name, $prefix, $description, $dpi, $multiplier, $attributes]) {
            foreach ($sizes as [$w, $h]) {
                // Rough retail pricing: $0.30/sq in, $35 minimum, × product multiplier.
                $price = (int) (max(3500, $w * $h * 30) * $multiplier);

                PrintProduct::firstOrCreate(
                    ['sku' => "{$prefix}-{$w}X{$h}"],
                    [
                        'name' => $name,
                        'type' => $type,
                        'width_in' => $w,
                        'height_in' => $h,
                        'price' => (int) (ceil($price / 500) * 500 - 100),
                        'currency' => config('prints.currency'),
                        'description' => $description,
                        'attributes' => $attributes,
                        'min_dpi' => $dpi,
                        'is_active' => false,
                        'sort_order' => $sort++,
                    ],
                );
            }
        }
    }
}
