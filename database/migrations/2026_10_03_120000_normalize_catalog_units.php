<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Maps the units typed by hand in the catalog to the standard codes.
 * Unrecognised units are kept as typed. Lines of quotes, invoices and
 * credit notes already issued are snapshots and are left untouched.
 */
return new class extends Migration
{
    private const ALIASES = [
        'unit' => ['unité', 'unite', 'u', 'unit', 'unités', 'unites'],
        'piece' => ['pièce', 'piece', 'pièces', 'pieces', 'pc', 'pcs'],
        'hour' => ['h', 'heure', 'heures', 'hr', 'hrs', 'hour', 'hours'],
        'day' => ['j', 'jour', 'jours', 'day', 'days'],
        'week' => ['semaine', 'semaines', 'week', 'weeks'],
        'month' => ['mois', 'month', 'months'],
        'year' => ['an', 'ans', 'année', 'annee', 'années', 'year', 'years'],
        'flat_rate' => ['forfait', 'forfaits'],
        'trip' => ['voyage', 'voyages', 'trip', 'trips'],
        'km' => ['km', 'kilomètre', 'kilometre', 'kilomètres', 'kilometres'],
        'm' => ['m', 'mètre', 'metre', 'mètres', 'metres', 'ml'],
        'm2' => ['m2', 'm²', 'mètre carré', 'metre carre', 'mètres carrés'],
        'm3' => ['m3', 'm³', 'mètre cube', 'metre cube', 'mètres cubes'],
        'kg' => ['kg', 'kilo', 'kilos', 'kilogramme', 'kilogrammes'],
        't' => ['t', 'tonne', 'tonnes'],
        'l' => ['l', 'litre', 'litres', 'liter', 'liters'],
        'box' => ['carton', 'cartons', 'box'],
        'lot' => ['lot', 'lots'],
    ];

    public function up(): void
    {
        DB::table('catalog_items')->select(['id', 'unit'])->orderBy('id')->each(function (object $item) {
            $typed = mb_strtolower(trim($item->unit));
            foreach (self::ALIASES as $code => $aliases) {
                if (in_array($typed, $aliases, true)) {
                    DB::table('catalog_items')->where('id', $item->id)->update(['unit' => $code]);

                    return;
                }
            }
        });
    }

    public function down(): void
    {
        foreach (array_keys(self::ALIASES) as $code) {
            DB::table('catalog_items')->where('unit', $code)->update(['unit' => self::ALIASES[$code][0]]);
        }
    }
};
