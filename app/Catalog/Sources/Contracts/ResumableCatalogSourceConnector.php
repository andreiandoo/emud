<?php

namespace App\Catalog\Sources\Contracts;

/**
 * Implemented by connectors whose feed is too long to finish inside a single job timeout.
 *
 * Without this, a killed import restarts from the beginning on every retry, so a source that
 * needs longer than the job timeout can never complete no matter how many attempts it gets.
 */
interface ResumableCatalogSourceConnector
{
    /**
     * Position the connector at a previously reported checkpoint.
     *
     * Must be called before iterating records. A checkpoint that does not belong to the
     * connector's current configuration has to be ignored rather than trusted, so that a
     * changed dataset or release starts a clean import instead of resuming into the middle
     * of a feed that no longer matches.
     *
     * @param  array<string, mixed>  $checkpoint
     */
    public function resumeFrom(array $checkpoint): void;

    /**
     * The connector's current position, safe to persist and hand back to resumeFrom().
     *
     * @return array<string, mixed>
     */
    public function checkpoint(): array;
}
