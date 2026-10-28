<?php

namespace Splicewire\Composition\Music\Notation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One stored notation output, by reference (music-capability-lift ticket 02 Q2): a media row in collection
 * `notation` on the addressed composition. Dense bodies never cross inline; `artifactRef` is the media uuid
 * the caller downloads.
 */
#[TypeScript]
#[Title('Notation artifact')]
class NotationArtifact extends Data
{
    public function __construct(
        #[Title('Format')]
        public NotationFormat $format,
        #[Title('Artifact ref')]
        #[Description('The media uuid the caller fetches the bytes by.')]
        public string $artifactRef,
        #[Title('Content type')]
        public string $contentType,
        #[Title('Bytes')]
        public int $bytes,
        #[Title('sha256')]
        #[Description('sha256 of the stored bytes.')]
        public string $sha256,
        #[Title('Page')]
        #[Description('1-based page number for a per-page format (svg); null otherwise.')]
        public ?int $page = null,
    ) {}
}
