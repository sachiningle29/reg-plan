<?php

namespace Database\Seeders;

use App\Models\MaterialItem;
use Illuminate\Database\Seeder;

class BsMaterialItemsSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            '30" X-52,310 ppf,Leop',
            '20", X-56, 133#, Leopard',
            'Casing Floating Equipment (20")',
            '13 3/8", L-80 68#, BTC',
            '13 3/8", J-55 68#, BTC',
            '13 3/8" P-110,68 ppf,BTC',
            'Casing Floating Equipment (13 3/8")',
            '11 3/4"-N80-60 PPF -FJ',
            'Casing Floating Equipment (11 3/4")',
            '9 5/8", L-80, 47PPF, BTC',
            'CASING PIPE 9 5/8" L-80, 47PPF HSL-XD THREAD',
            'CROSS OVER 9 5/8", L-80, 47PPF, BTC PIN X HSL XD BOX',
            'CROSS OVER 9 5/8", L-80, 47PPF, HSL XD PIN X BTC BOX',
            'PREM CASING 9-5/8",13CR, L-80 47 PPF,JFE LION THREAD',
            'X-OVER 9 5/8",L80, 47PPF 13CR JFELION BOX X BTC PIN',
            'X-OVER 9 5/8",L80, 47PPF 13CR JFELION PIN X BTC BOX',
            '9 5/8" L-80-47PPF-PREMIUM-FJ',
            'Casing Floating Equipment-FJ',
            '9 5/8" P-110,47 ppf,BTC',
            'CASING PIPE 9-5/8", P-110, 47 PPF TSH-3SB THREAD',
            'X-OVER 9-5/8", P-110, 47 PPF PREM TSH-3SB PIN X BTC BOX',
            'X-OVER 9-5/8"P-110 47# PREM TSH 3SB BOX X BTC PIN',
            'PREM CASING 9-5/8" P-110, 47 PPF TMK CENTUM THREAD',
            'CROSS OVER 9-5/8",P-110, 47PPF TMK CENT* BOX X BTC PIN',
            'CROSS OVER 9-5/8",P-110, 47PPF TMK CENT* PIN X BTC BOX',
            'Casing Floating Equipment (9 5/8")',
            '9 5/8" Q-125, 53.5 ppf, HSL-XD',
            'CROSS OVER 9 5/8",Q-125,53.5PPF HSL XD PIN X BTC BOX',
            'CROSS OVER 9 5/8",Q-125,53.5PPF HSL XD BOX X BTC PIN',
            '9 5/8"-Q-125, 53.5 ppf Floating Equipment',
            '7"Liner, 13 Cr L-80, 29#, Premium',
            '7" L-80-29 PPF BTC',
            '7" Liner, CRA, 29# premium',
            'Casing Floating Equipment (7")',
            '7"P-110,29 ppf,BTC',
            '7"P-110,29 ppf, Prem',
            '7" Liner Hanger with floating Equipment',
            'Q-125, 35ppf, Prem',
            '7" Expandable Liner Hanger with floating Equipment',
            '5" L-80,18 PPF XL',
            'Casing Floating Equipment (5")',
            'Well Head 4 CP 15 M',
            'Well Head 3 CP 10 M',
            'Well Head 2 CP 5 M',
            'A-Sec 21 1/4" 2M',
            'B-Sec (13 5/8") 5M',
            'C-Sec( 9 5/8")5M',
            'C-Sec, 10M',
            'C-Sec-13 CR, 5M',
            'C-Sec-13 CR,10M',
            'C-Sec-13 CR,15M',
            'C-Sec Dual 13 Cr,11',
            'C-Sec Dual Normal,11',
            'X-Mas Tree 3-1/2" Tub (165100315)',
            'X-MAS TREE 3-1/2" 13CR. MONO BLOCK 5M, (160000012)',
            'X-MAS TREE 3-1/2" 13CR. MONO BLOCK 15M,',
            'X-MAS TREE 3-1/2" 13CR. MONO BLOCK 10M, ()',
        ];

        foreach ($items as $order => $name) {
            MaterialItem::updateOrCreate(
                ['asset_name' => 'BS', 'item_name' => $name],
                ['sort_order' => $order + 1]
            );
        }
    }
}
