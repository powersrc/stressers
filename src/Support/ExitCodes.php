<?php

namespace App\Support;

interface ExitCodes
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const FATAL   = -1;
}