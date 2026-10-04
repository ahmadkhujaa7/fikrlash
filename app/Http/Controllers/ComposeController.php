<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Post yozish qulayliklari: # va @ yozilganda takliflar (JSON).
 * Teg topilmasa — foydalanuvchi yangi teg yaratadi (u post chop etilganda paydo bo‘ladi).
 */
class ComposeController extends Controller
{
    public function tags(Request $request): JsonResponse
    {
        $slug = TextNormalizer::tagSlug((string) $request->query('q', ''));

        $tags = Tag::query()
            ->select('tags.id', 'tags.name', 'tags.slug')
            ->selectSub(fn ($q) => $q->from('post_tag')->selectRaw('COUNT(*)')->whereColumn('post_tag.tag_id', 'tags.id'), 'posts_count')
            ->when($slug !== '', fn (Builder $q) => $q->where('slug', 'like', addcslashes($slug, '%_\\').'%'))
            ->orderByDesc('posts_count')
            ->limit(6)
            ->get()
            ->map(fn (Tag $t) => ['name' => $t->name, 'slug' => $t->slug, 'posts_count' => (int) $t->posts_count]);

        return response()->json([
            'tags' => $tags,
            // Aynan shu teg hali yo‘q bo‘lsa — "yangi teg yaratish" taklifi.
            'can_create' => $slug !== '' && mb_strlen($slug) <= 50 && ! $tags->contains('slug', $slug),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $q = addcslashes(mb_strtolower(ltrim(trim((string) $request->query('q', '')), '@')), '%_\\');
        $me = $request->user();

        $users = User::query()->visible()
            ->whereKeyNot($me->id)
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('username', 'like', $q.'%')->orWhere('name', 'like', '%'.$q.'%')))
            // Avval o‘zi obuna bo‘lganlar, keyin tasdiqlanganlar, keyin mashhurlar.
            ->orderByRaw('EXISTS (SELECT 1 FROM follows WHERE follows.follower_id = ? AND follows.following_id = users.id) DESC', [$me->id])
            ->orderByRaw('verified_at IS NULL')
            ->orderByDesc('followers_count')
            ->limit(6)
            ->get(['id', 'name', 'username', 'avatar_path', 'verified_at']);

        return response()->json(['users' => $users->map(fn (User $u) => [
            'name' => $u->name,
            'username' => $u->username,
            'avatar_url' => $u->avatarUrl(),
            'initials' => $u->initials(),
            'tone' => $u->tone(),
            'verified' => $u->isVerified(),
        ])]);
    }
}
