<?php
declare(strict_types=1);

namespace InfinityBlog\Audio;

interface TtsProvider
{
    /**
     * Synthesize audio from text.
     *
     * @return string Raw audio binary (e.g. MP3 bytes)
     * @throws TtsProviderException
     */
    public function synthesize(string $text, string $voice = 'alloy', string $format = 'mp3'): string;
}
