<?php

namespace App\Contracts;

use App\Exceptions\AiException;
use App\Services\Ai\AiAnalysisResult;

interface AiProvider
{
    /**
     * Post matnini tahlil qiladi.
     *
     * @param  list<string>  $categorySlugs  Ruxsat etilgan kategoriyalar
     *
     * @throws AiException
     */
    public function analyzePost(string $content, array $categorySlugs): AiAnalysisResult;

    public function name(): string;
}
