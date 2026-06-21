<?php

namespace Modules\VehicleManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds a comprehensive, ready-to-use catalogue of vehicle categories, brands and
 * models so the driver vehicle-registration dropdowns are populated out of the box.
 *
 * Idempotent: existing rows are left in place (only re-activated); never mutates a
 * primary key, so brand_id foreign links stay intact. The driver brand/model API
 * filters is_active = 1, so everything here is seeded active.
 *
 * Run: php artisan db:seed --class="Modules\VehicleManagement\Database\Seeders\VehicleBrandModelSeeder"
 */
class VehicleBrandModelSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ---- Vehicle categories (type must be car|motor_bike) ----
        if (Schema::hasTable('vehicle_categories')) {
            $categories = [
                ['Sedan', 'car'], ['SUV', 'car'], ['Hatchback', 'car'], ['Luxury', 'car'],
                ['Van', 'car'], ['Pickup', 'car'], ['Electric', 'car'], ['Motorbike', 'motor_bike'],
            ];
            foreach ($categories as [$name, $type]) {
                $this->ensureRow('vehicle_categories', ['name' => $name], [
                    'description' => $name . ' vehicles',
                    'image' => '',
                    'type' => $type,
                ], $now);
            }
        }

        // ---- Brands -> models ----
        foreach ($this->brands() as $brandName => $models) {
            $brandId = DB::table('vehicle_brands')->where('name', $brandName)->value('id');
            if (!$brandId) {
                $brandId = (string) Str::uuid();
                DB::table('vehicle_brands')->insert([
                    'id' => $brandId,
                    'name' => $brandName,
                    'description' => $brandName . ' vehicles',
                    'image' => '',
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('vehicle_brands')->where('id', $brandId)->update(['is_active' => 1, 'updated_at' => $now]);
            }

            foreach ($models as $modelName) {
                $exists = DB::table('vehicle_models')->where('name', $modelName)->where('brand_id', $brandId)->exists();
                if (!$exists) {
                    DB::table('vehicle_models')->insert([
                        'id' => (string) Str::uuid(),
                        'name' => $modelName,
                        'brand_id' => $brandId,
                        'seat_capacity' => 4,
                        'maximum_weight' => 500,
                        'hatch_bag_capacity' => 2,
                        'engine' => '1500',
                        'description' => $brandName . ' ' . $modelName,
                        'image' => '',
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('vehicle_models')->where('name', $modelName)->where('brand_id', $brandId)->update(['is_active' => 1, 'updated_at' => $now]);
                }
            }
        }
    }

    private function ensureRow(string $table, array $key, array $extra, $now): void
    {
        if (!DB::table($table)->where($key)->exists()) {
            DB::table($table)->insert(array_merge($key, $extra, [
                'id' => (string) Str::uuid(),
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        } else {
            DB::table($table)->where($key)->update(['is_active' => 1, 'updated_at' => $now]);
        }
    }

    private function brands(): array
    {
        return [
            'Toyota' => ['Corolla', 'Camry', 'RAV4', 'Hilux', 'Yaris', 'Land Cruiser', 'Prius', 'Fortuner'],
            'Honda' => ['Civic', 'Accord', 'CR-V', 'City', 'Fit', 'HR-V', 'Pilot'],
            'Ford' => ['Focus', 'Fiesta', 'Ranger', 'Explorer', 'Escape', 'Mustang', 'F-150'],
            'Hyundai' => ['Elantra', 'Accent', 'Tucson', 'Santa Fe', 'Sonata', 'i10', 'Creta'],
            'Kia' => ['Rio', 'Cerato', 'Sportage', 'Sorento', 'Picanto', 'Seltos'],
            'Nissan' => ['Sentra', 'Altima', 'X-Trail', 'Versa', 'Kicks', 'Patrol', 'Navara'],
            'Volkswagen' => ['Golf', 'Jetta', 'Passat', 'Tiguan', 'Polo', 'Vento'],
            'BMW' => ['3 Series', '5 Series', 'X3', 'X5', '7 Series', 'X1'],
            'Mercedes-Benz' => ['C-Class', 'E-Class', 'GLC', 'GLE', 'A-Class', 'S-Class'],
            'Chevrolet' => ['Spark', 'Aveo', 'Cruze', 'Tahoe', 'Onix', 'Tracker'],
            'Suzuki' => ['Swift', 'Alto', 'Vitara', 'Baleno', 'Ertiga', 'Jimny'],
            'Mitsubishi' => ['Lancer', 'Outlander', 'Montero', 'L200', 'Mirage', 'ASX'],
            'Renault' => ['Logan', 'Sandero', 'Duster', 'Clio', 'Kwid', 'Captur'],
            'Peugeot' => ['208', '301', '3008', '2008', '308', '508'],
            'Mazda' => ['Mazda2', 'Mazda3', 'CX-5', 'CX-3', 'Mazda6', 'CX-9'],
            'Volvo' => ['XC40', 'XC60', 'XC90', 'S60', 'S90'],
            'Audi' => ['A3', 'A4', 'Q3', 'Q5', 'A6', 'Q7'],
            'Jeep' => ['Wrangler', 'Compass', 'Cherokee', 'Renegade', 'Grand Cherokee'],
            'Fiat' => ['Uno', 'Palio', 'Cronos', 'Argo', 'Mobi', 'Toro'],
            'Tesla' => ['Model 3', 'Model Y', 'Model S', 'Model X'],
            'Lexus' => ['ES', 'RX', 'NX', 'IS', 'UX'],
            'Subaru' => ['Impreza', 'Forester', 'Outback', 'XV', 'Legacy'],
            'Dodge' => ['Charger', 'Challenger', 'Durango', 'Journey'],
            'Jaguar' => ['XE', 'XF', 'F-Pace', 'E-Pace'],
            'Land Rover' => ['Defender', 'Discovery', 'Range Rover', 'Evoque'],
            'Porsche' => ['Macan', 'Cayenne', '911', 'Panamera'],
            'Mini' => ['Cooper', 'Countryman', 'Clubman'],
            'Isuzu' => ['D-Max', 'MU-X'],
            'Geely' => ['Coolray', 'Emgrand', 'Azkarra'],
            'BYD' => ['Han', 'Tang', 'Song', 'Dolphin'],
            'Chery' => ['Tiggo', 'Arrizo', 'QQ'],
            'Great Wall' => ['Wingle', 'Poer', 'Haval H6'],
            'Citroen' => ['C3', 'C4', 'C-Elysee', 'Berlingo'],
            'Seat' => ['Ibiza', 'Leon', 'Arona', 'Ateca'],
            'Skoda' => ['Octavia', 'Fabia', 'Kodiaq', 'Rapid'],
            'Acura' => ['ILX', 'TLX', 'RDX', 'MDX'],
            'Infiniti' => ['Q50', 'QX50', 'QX60'],
            'Cadillac' => ['XT4', 'XT5', 'Escalade'],
            'GMC' => ['Sierra', 'Yukon', 'Acadia'],
            'Ram' => ['1500', '2500'],
            'Yamaha' => ['YZF-R3', 'MT-15', 'FZ', 'Fazer', 'NMAX'],
            'Bajaj' => ['Pulsar', 'Boxer', 'Avenger', 'Dominar'],
        ];
    }
}
