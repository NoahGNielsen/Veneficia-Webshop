<?php
// The Wheel of the Year: Wicca's eight sabbats, for the homepage. No API or database. The solstices and equinoxes are
// worked out (Meeus, Astronomical Algorithms, chapter 27: the mean instants for 2000-3000, without the small periodic
// terms, so right to within about half an hour); the four cross-quarter days have their fixed traditional dates. Dates
// are Danish time.
//
// A product tagged with a sabbat's key ("yule", "samhain", ...) shows on the homepage while that sabbat is the next
// one; one tagged with a moon phase ("fuldmåne", ...) shows while the moon is in it (see index.php).

// key => [name, what it marks, month and day for the fixed ones or which solstice/equinox]
const SABBATS = [
    'imbolc' => ['Imbolc', 'Lyset vender tilbage', [2, 1]],
    'ostara' => ['Ostara', 'Forårsjævndøgn', 'march'],
    'beltane' => ['Beltane', 'Sommerens begyndelse', [5, 1]],
    'litha' => ['Litha', 'Sommersolhverv, årets længste dag', 'june'],
    'lughnasadh' => ['Lughnasadh', 'Den første høst', [8, 1]],
    'mabon' => ['Mabon', 'Efterårsjævndøgn, den anden høst', 'september'],
    'samhain' => ['Samhain', 'Årets mørke halvdel begynder', [10, 31]],
    'yule' => ['Yule', 'Vintersolhverv, årets længste nat', 'december'],
];

const SEASON_MONTHS = ['januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december'];

// The date (Danish time) of a year's equinox or solstice: $which is march, june, september or december
function solsticeDate(int $year, string $which): DateTimeImmutable
{
    $y = ($year - 2000) / 1000;
    $jde = match ($which) {
        'march' => 2451623.80984 + 365242.37404 * $y + 0.05169 * $y ** 2 - 0.00411 * $y ** 3 - 0.00057 * $y ** 4,
        'june' => 2451716.56767 + 365241.62603 * $y + 0.00325 * $y ** 2 + 0.00888 * $y ** 3 - 0.00030 * $y ** 4,
        'september' => 2451810.21715 + 365242.01767 * $y - 0.11575 * $y ** 2 + 0.00337 * $y ** 3 + 0.00078 * $y ** 4,
        'december' => 2451900.05952 + 365242.74049 * $y - 0.06223 * $y ** 2 - 0.00823 * $y ** 3 + 0.00032 * $y ** 4,
    };
    // Julian day to Unix time, then to Danish time and back to midnight
    $moment = new DateTimeImmutable('@' . (int)round(($jde - 2440587.5) * 86400));
    return $moment->setTimezone(new DateTimeZone('Europe/Copenhagen'))->setTime(0, 0);
}

// Every sabbat of a year, in calendar order: key => date
function sabbatDates(int $year): array
{
    $dates = [];
    foreach (SABBATS as $key => [, , $when]) {
        $dates[$key] = is_array($when)
            ? new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $when[0], $when[1]), new DateTimeZone('Europe/Copenhagen'))
            : solsticeDate($year, $when);
    }
    asort($dates);
    return $dates;
}

// The next sabbat from $today (today's counts as next): ['key', 'name', 'meaning', 'date', 'days'], plus the one
// before it ("previous") for "the season we're in"
function nextSabbat(DateTimeImmutable $today): array
{
    $today = $today->setTimezone(new DateTimeZone('Europe/Copenhagen'))->setTime(0, 0);
    $year = (int)$today->format('Y');
    $all = [];
    foreach ([$year - 1, $year, $year + 1] as $y) {
        foreach (sabbatDates($y) as $key => $date) {
            $all[] = [$key, $date];
        }
    }
    foreach ($all as $i => [$key, $date]) {
        if ($date >= $today) {
            [$prevKey, $prevDate] = $all[$i - 1];
            return [
                'key' => $key,
                'name' => SABBATS[$key][0],
                'meaning' => SABBATS[$key][1],
                'date' => $date,
                'days' => (int)$today->diff($date)->days,
                'previous' => ['key' => $prevKey, 'name' => SABBATS[$prevKey][0], 'date' => $prevDate],
            ];
        }
    }
    throw new LogicException('No sabbat after ' . $today->format('Y-m-d'));
}

// "21. december"
function seasonDate(DateTimeImmutable $date): string
{
    return $date->format('j') . '. ' . SEASON_MONTHS[(int)$date->format('n') - 1];
}
