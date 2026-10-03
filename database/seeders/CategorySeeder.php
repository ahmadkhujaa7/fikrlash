<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        ['Hayot', 'hayot', 'Kundalik hayot, oila, munosabatlar va tajribalar', 'heart'],
        ['Texnologiya', 'texnologiya', 'Gadjetlar, internet, sun’iy intellekt va yangiliklar', 'cpu'],
        ['Ta’lim', 'talim', 'Maktab, universitet, o‘qish usullari va ustozlar', 'graduation-cap'],
        ['Biznes', 'biznes', 'Tadbirkorlik, startaplar, marketing va moliya', 'briefcase'],
        ['Ilm-fan', 'ilm-fan', 'Fizika, biologiya, tadqiqotlar va kashfiyotlar', 'flask'],
        ['Jamiyat', 'jamiyat', 'Shahar, qonunlar, ijtimoiy muammolar va yechimlar', 'users'],
        ['Falsafa', 'falsafa', 'Ma’no, ong, axloq va katta savollar', 'brain'],
        ['Dasturlash', 'dasturlash', 'Kod, frameworklar, karyera va texnik maslahatlar', 'code'],
        ['Kitoblar', 'kitoblar', 'O‘qilgan kitoblar, tavsiyalar va iqtiboslar', 'book'],
        ['Shaxsiy rivojlanish', 'shaxsiy-rivojlanish', 'Odatlar, motivatsiya, intizom va maqsadlar', 'trending-up'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $order => [$name, $slug, $description, $icon]) {
            Category::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'icon' => $icon, 'sort_order' => $order, 'is_active' => true,
            ]);
        }
    }
}
