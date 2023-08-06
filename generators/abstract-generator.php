<?php

namespace Tangible\Populater;

interface AbstractGenerator
{
    // function which generates something.
    public function generate( int $argument ): bool|object;

    // function which removes all generated items
    public function remove_generated(): void;

}