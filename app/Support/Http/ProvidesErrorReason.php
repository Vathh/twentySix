<?php

namespace App\Support\Http;

interface ProvidesErrorReason
{
    public function reason(): string;
}
