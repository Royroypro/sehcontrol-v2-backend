<?php

use Carbon\Carbon;

if (! function_exists('peru_now')) {
    function peru_now(): Carbon
    {
        return now()->timezone('America/Lima');
    }
}
