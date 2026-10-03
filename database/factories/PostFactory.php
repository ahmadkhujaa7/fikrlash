<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public const SAMPLES = [
        'Bugun bir narsani angladim: eng yaxshi g‘oyalar ko‘pincha sayr qilayotganda keladi. Telefonni uyda qoldirib, 30 daqiqa piyoda yurib ko‘ring.',
        'Laravel 12 da queue va scheduler bilan ishlash juda qulay. Og‘ir ishlarni fonga chiqaring — foydalanuvchi kutib qolmasin. #dasturlash #laravel',
        'Kitob o‘qish odati haqida: kuniga 10 bet ham yiliga 12 ta kitob degani. Kichik qadamlar katta natija beradi. #kitoblar',
        'Startap boshlashdan oldin o‘zingizga savol bering: bu muammo haqiqatan ham mavjudmi, yoki men shunchaki g‘oyani yaxshi ko‘rib qoldimmi?',
        'Ta’lim tizimida eng katta muammo — savol berishdan qo‘rqish. Bolalarga "bilmayman" deyishni o‘rgatish kerak. #talim',
        'Sun’iy intellekt ishimizni tortib olmaydi, lekin uni ishlata oladigan odamlar ishlata olmaydiganlarning o‘rnini egallaydi. #ai',
        'Toshkentda jamoat transporti so‘nggi yillarda ancha yaxshilandi. Sizningcha, yana nimani o‘zgartirish kerak?',
        'Falsafiy savol: agar xotiralaringiz butunlay o‘zgarsa, siz o‘sha odam bo‘lib qolasizmi?',
        'Har kuni ertalab 3 ta ustuvor vazifani yozib qo‘yaman. Kun oxirida qanchasi bajarilganini ko‘rish motivatsiya beradi. #odatlar',
        'Kimyo darsida o‘qituvchimiz aytgan gap hali ham esimda: "Tabiatda hech narsa yo‘qolmaydi, faqat shakl o‘zgaradi."',
        'Biznesda eng qimmat narsa — vaqt emas, diqqat. Mijozning diqqatini qozonish uchun nimalar qilyapsiz? #biznes #marketing',
        'Python yoki JavaScript? Boshlovchilarga qaysi birini tavsiya qilasiz va nega?',
        'Ota-onamizdan o‘rgangan eng muhim saboq: va’da berdingmi — bajar.',
        'Ilm-fan yangiliklari: koinotda suv mavjud sayyoralar soni biz o‘ylagandan ko‘p bo‘lishi mumkin ekan. #ilmfan',
        'Ingliz tilini o‘rganishda menga eng ko‘p yordam bergan narsa — serial ko‘rish emas, balki har kuni 5 daqiqa ovoz chiqarib o‘qish bo‘ldi.',
        'Jamiyatimizda kitobxonlikni qanday oshirsa bo‘ladi? Kutubxonalar zamonaviylashishi kerakmi?',
    ];

    public function definition(): array
    {
        $publishedAt = fake()->dateTimeBetween('-20 days', 'now');

        return [
            'user_id' => User::factory(),
            'content' => fake()->randomElement(self::SAMPLES),
            'status' => PostStatus::Published,
            'visibility' => PostVisibility::Public,
            'published_at' => $publishedAt,
            'created_at' => $publishedAt,
            'updated_at' => $publishedAt,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => PostStatus::Draft, 'published_at' => null]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['status' => PostStatus::Hidden]);
    }

    public function followersOnly(): static
    {
        return $this->state(fn () => ['visibility' => PostVisibility::Followers]);
    }
}
