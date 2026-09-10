<?php

namespace App\Workshops\Contracts;

/**
 * Reads what a workshop says about itself and names the services it offers. The keyword
 * classifier is the default; an LLM-backed one can replace it without the pipeline depending on it.
 */
interface ServiceClassifier
{
    /** @return array<string, array{confidence: int, snippets: list<string>}> service key => evidence */
    public function classify(string $text): array;
}
