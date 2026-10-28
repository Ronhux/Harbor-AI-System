<?php

namespace Database\Seeders;

use App\Models\AiDataSource;
use App\Models\CommodityReference;
use App\Models\UnitConversionReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AiReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        AiDataSource::updateOrCreate([
            'source_type' => AiDataSource::TYPE_OFFICIAL_REFERENCE,
            'source_name' => AiDataSource::SOURCE_PSA_MAJOR_CROPS,
        ], [
            'source_url' => 'https://openstat.psa.gov.ph/Metadata/Prices/Farmgate-Prices',
            'organization' => 'Philippine Statistics Authority (PSA)',
            'geographic_scope' => 'Philippines, reported by selected region; observation geography must be preserved',
            'description' => 'Official PSA OpenSTAT table ' . AiDataSource::PSA_MAJOR_CROPS_TABLE_ID
                . ': Major Crops farmgate prices by region at monthly frequency. Region II observations are regional references, not Aparri-specific prices.',
            'is_official' => true,
            'is_active' => true,
        ]);

        // Canonical names retain the exact PSA source labels; aliases are curated separately.
        $exactPsaCommodityLabels = [
            ['Ampalaya fruit [Bitter gourd]', 'Vegetables'],
            ['Banana Bungulan', 'Fruits'],
            ['Banana Cavendish', 'Fruits'],
            ['Banana Lakatan', 'Fruits'],
            ['Banana Latundan', 'Fruits'],
            ['Banana Others', 'Fruits'],
            ['Banana Saba', 'Fruits'],
            ['Cabbage', 'Vegetables'],
            ['Calamansi', 'Fruits'],
            ['Camote [Sweet potato]', 'Vegetables'],
            ['Cassava for food, fresh tubers', 'Vegetables'],
            ['Coconut Mature', 'Fruits'],
            ['Coconut Young', 'Fruits'],
            ['Eggplant, long, purple', 'Vegetables'],
            ['Eggplant, native, long', 'Vegetables'],
            ['Eggplant, native, round', 'Vegetables'],
            ['Mango Carabao', 'Fruits'],
            ['Mango Indian', 'Fruits'],
            ['Mango Others', 'Fruits'],
            ['Mango Piko', 'Fruits'],
            ['Onion, red creole (Bermuda red)', 'Vegetables'],
            ['Onion, red shallot (Sibuyas Tagalog)', 'Vegetables'],
            ['Onion, yellow granex (Bermuda white)', 'Vegetables'],
            ['Pineapple, Formosa', 'Fruits'],
            ['Pineapple, Hawaiian', 'Fruits'],
            ['Pineapple, Native', 'Fruits'],
            ['Potato', 'Vegetables'],
            ['Tomato', 'Vegetables'],
        ];
        $approvedAliases = [
            'Cabbage' => ['Repolyo'],
            'Camote [Sweet potato]' => ['Camote', 'Kamote'],
            'Cassava for food, fresh tubers' => ['Cassava Kamoteng Kahoy', 'Kamoteng Kahoy'],
        ];

        foreach ($exactPsaCommodityLabels as [$label, $category]) {
            $commodity = CommodityReference::firstOrCreate(
                ['normalized_name' => Str::slug($label)],
                [
                    'canonical_name' => $label,
                    'category' => $category,
                    'aliases' => [],
                    'notes' => 'Exact PSA OpenSTAT commodity label; identity mapping only, with no inferred alias.',
                    'is_active' => true,
                ]
            );

            $aliases = array_values(array_unique([
                ...($commodity->aliases ?? []),
                ...($approvedAliases[$label] ?? []),
            ]));
            if ($aliases !== ($commodity->aliases ?? [])) {
                $commodity->aliases = $aliases;
                $commodity->save();
            }
        }

        $conversions = [
            ['kg', 'g', 1000],
            ['g', 'kg', 0.001],
        ];

        foreach ($conversions as [$from, $to, $factor]) {
            UnitConversionReference::query()
                ->updateOrCreate([
                    'commodity_reference_id' => null,
                    'from_unit' => $from,
                    'to_unit' => $to,
                ], [
                    'conversion_factor' => $factor,
                    'source_reference' => 'Phase 1 requirement: 1 kg = 1,000 g',
                    'verification_status' => 'verified',
                    'notes' => 'Universal metric mass conversion; not a product-specific conversion.',
                ]);
        }
    }
}
