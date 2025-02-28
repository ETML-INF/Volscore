<?php

function rosterIsValid($roster) : bool
{

    if (!is_array($roster)) return false;
    foreach ($roster as $player) {
        if (!$player->validated) return false;
    }
    return true;
}

function romanNumber($n) {
    switch ($n) {
        case 1:
            return 'I';
        case 2:
            return 'II';
        case 3:
            return 'III';
        case 4:
            return 'IV';
        case 5:
            return 'V';
        case 6:
            return 'VI';
        default:
            return '?';
    }
}

function minutesBetween ($start, $end)
{
    if (!$start || !$end) return "?";
    $start_date = new DateTime($start);
    $since_start = $start_date->diff(new DateTime($end));
    return $since_start->i;
}
?>