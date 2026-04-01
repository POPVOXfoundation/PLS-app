<?php

namespace App\Support\PlsAssistant;

use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Document;

interface AssistantSourceTextExtractor
{
    public function extract(AssistantSourceDocument|Document $document): AssistantSourceExtractionResult;
}
