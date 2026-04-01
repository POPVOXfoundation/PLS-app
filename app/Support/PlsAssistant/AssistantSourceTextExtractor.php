<?php

namespace App\Support\PlsAssistant;

use App\Domain\Documents\AssistantSourceDocument;

interface AssistantSourceTextExtractor
{
    public function extract(AssistantSourceDocument $document): AssistantSourceExtractionResult;
}
