<?php

namespace Database\Seeders;

use App\Enums\GameStage;
use App\Models\PointScheme\PointScheme;
use App\Models\PointScheme\PointSchemeRule;
use Illuminate\Database\Seeder;

/**
 * Przedziały 4–80 graczy (bez dziur i bez nakładania się).
 * Punktacja rośnie z rozmiarem turnieju (ten sam etap = więcej pkt przy większej puli).
 */
class PointSchemeSeeder extends Seeder
{
    public function run(): void
    {
        if (PointScheme::query()->exists()) {
            return;
        }

        $scheme4to8 = PointScheme::create([
            'name' => 'od 4 do 8 osób',
            'min_players' => 4,
            'max_players' => 8,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme4to8->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 1],
            ['point_scheme_id' => $scheme4to8->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 2],
            ['point_scheme_id' => $scheme4to8->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 3],
            ['point_scheme_id' => $scheme4to8->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 5],
            ['point_scheme_id' => $scheme4to8->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 6],
            ['point_scheme_id' => $scheme4to8->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 8],
            ['point_scheme_id' => $scheme4to8->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 10],
            ['point_scheme_id' => $scheme4to8->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 13],
        ]);

        $scheme9to16 = PointScheme::create([
            'name' => 'od 9 do 16 osób',
            'min_players' => 9,
            'max_players' => 16,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme9to16->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 2],
            ['point_scheme_id' => $scheme9to16->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 3],
            ['point_scheme_id' => $scheme9to16->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 5],
            ['point_scheme_id' => $scheme9to16->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 7],
            ['point_scheme_id' => $scheme9to16->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 10],
            ['point_scheme_id' => $scheme9to16->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 13],
            ['point_scheme_id' => $scheme9to16->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 16],
            ['point_scheme_id' => $scheme9to16->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 20],
        ]);

        $scheme17to24 = PointScheme::create([
            'name' => 'od 17 do 24 osób',
            'min_players' => 17,
            'max_players' => 24,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme17to24->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 2],
            ['point_scheme_id' => $scheme17to24->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 4],
            ['point_scheme_id' => $scheme17to24->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 6],
            ['point_scheme_id' => $scheme17to24->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 9],
            ['point_scheme_id' => $scheme17to24->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 12],
            ['point_scheme_id' => $scheme17to24->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 16],
            ['point_scheme_id' => $scheme17to24->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 19],
            ['point_scheme_id' => $scheme17to24->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 24],
        ]);

        $scheme25to32 = PointScheme::create([
            'name' => 'od 25 do 32 osób',
            'min_players' => 25,
            'max_players' => 32,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme25to32->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 2],
            ['point_scheme_id' => $scheme25to32->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 4],
            ['point_scheme_id' => $scheme25to32->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 7],
            ['point_scheme_id' => $scheme25to32->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 10],
            ['point_scheme_id' => $scheme25to32->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 14],
            ['point_scheme_id' => $scheme25to32->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 18],
            ['point_scheme_id' => $scheme25to32->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 22],
            ['point_scheme_id' => $scheme25to32->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 26],
        ]);

        $scheme33to40 = PointScheme::create([
            'name' => 'od 33 do 40 osób',
            'min_players' => 33,
            'max_players' => 40,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme33to40->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 5, 'points' => 2],
            ['point_scheme_id' => $scheme33to40->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 4],
            ['point_scheme_id' => $scheme33to40->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 7],
            ['point_scheme_id' => $scheme33to40->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 10],
            ['point_scheme_id' => $scheme33to40->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 14],
            ['point_scheme_id' => $scheme33to40->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 18],
            ['point_scheme_id' => $scheme33to40->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 22],
            ['point_scheme_id' => $scheme33to40->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 26],
            ['point_scheme_id' => $scheme33to40->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 30],
        ]);

        $scheme41to48 = PointScheme::create([
            'name' => 'od 41 do 48 osób',
            'min_players' => 41,
            'max_players' => 48,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 6, 'points' => 2],
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 5, 'points' => 3],
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 5],
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 8],
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 11],
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 15],
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 19],
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 24],
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 29],
            ['point_scheme_id' => $scheme41to48->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 34],
        ]);

        $scheme49to56 = PointScheme::create([
            'name' => 'od 49 do 56 osób',
            'min_players' => 49,
            'max_players' => 56,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 6, 'points' => 2],
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 5, 'points' => 4],
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 6],
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 9],
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 12],
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 17],
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 21],
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 26],
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 31],
            ['point_scheme_id' => $scheme49to56->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 37],
        ]);

        $scheme57to64 = PointScheme::create([
            'name' => 'od 57 do 64 osób',
            'min_players' => 57,
            'max_players' => 64,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 6, 'points' => 2],
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 5, 'points' => 4],
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 7],
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 10],
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 13],
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 18],
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 23],
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 28],
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 33],
            ['point_scheme_id' => $scheme57to64->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 39],
        ]);

        $scheme65to72 = PointScheme::create([
            'name' => 'od 65 do 72 osób',
            'min_players' => 65,
            'max_players' => 72,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 6, 'points' => 3],
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 5, 'points' => 5],
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 8],
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 11],
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 14],
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 20],
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 25],
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 30],
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 35],
            ['point_scheme_id' => $scheme65to72->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 41],
        ]);

        $scheme73to80 = PointScheme::create([
            'name' => 'od 73 do 80 osób',
            'min_players' => 73,
            'max_players' => 80,
        ]);
        PointSchemeRule::insert([
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 6, 'points' => 3],
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 5, 'points' => 6],
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 4, 'points' => 9],
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::GROUP->value, 'place' => 3, 'points' => 12],
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::EIGHT->value, 'place' => null, 'points' => 15],
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::QUARTER->value, 'place' => null, 'points' => 22],
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 4, 'points' => 27],
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::THIRD->value, 'place' => 3, 'points' => 33],
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 2, 'points' => 38],
            ['point_scheme_id' => $scheme73to80->id, 'elimination_stage' => GameStage::FINAL->value, 'place' => 1, 'points' => 44],
        ]);
    }
}
