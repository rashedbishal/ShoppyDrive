<?php

declare(strict_types=1);

namespace App\Support;

/**
 * BIZ-021: the districts customers choose from, the server's copy of
 * `BANGLADESH_DISTRICTS` in resources/js/lib/bangladesh-geography.ts. City stays
 * free text: only the exact pair checked in `Setting::shippingRateFor()` gets
 * the lower rate, so an invented city can only ever pay the normal one.
 */
class BangladeshDistricts
{
    /** @var list<string> */
    public const array ALL = [
        'Bagerhat',
        'Bandarban',
        'Barguna',
        'Barishal',
        'Bhola',
        'Bogura',
        'Brahmanbaria',
        'Chandpur',
        'Chapai Nawabganj',
        'Chattogram',
        'Chuadanga',
        'Cox\'s Bazar',
        'Cumilla',
        'Dhaka',
        'Dinajpur',
        'Faridpur',
        'Feni',
        'Gaibandha',
        'Gazipur',
        'Gopalganj',
        'Habiganj',
        'Jamalpur',
        'Jashore',
        'Jhalokati',
        'Jhenaidah',
        'Joypurhat',
        'Khagrachhari',
        'Khulna',
        'Kishoreganj',
        'Kurigram',
        'Kushtia',
        'Lakshmipur',
        'Lalmonirhat',
        'Madaripur',
        'Magura',
        'Manikganj',
        'Meherpur',
        'Moulvibazar',
        'Munshiganj',
        'Mymensingh',
        'Naogaon',
        'Narail',
        'Narayanganj',
        'Narsingdi',
        'Natore',
        'Netrokona',
        'Nilphamari',
        'Noakhali',
        'Pabna',
        'Panchagarh',
        'Patuakhali',
        'Pirojpur',
        'Rajbari',
        'Rajshahi',
        'Rangamati',
        'Rangpur',
        'Satkhira',
        'Shariatpur',
        'Sherpur',
        'Sirajganj',
        'Sunamganj',
        'Sylhet',
        'Tangail',
        'Thakurgaon',
    ];
}
