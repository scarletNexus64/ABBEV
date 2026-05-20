<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Devises ISO 4217 actives. `rate_from_xof` = combien d'unités de la devise
     * vaut 1 XOF (les prix sont stockés en XOF côté backend et convertis).
     *
     * Taux indicatifs basés sur les valeurs de marché de mai 2026. À actualiser
     * périodiquement via l'admin si nécessaire.
     */
    public function run(): void
    {
        foreach ($this->currencies() as $row) {
            Currency::updateOrCreate(
                ['code' => $row[0]],
                [
                    'name' => $row[1],
                    'symbol' => $row[2],
                    'decimals' => $row[3],
                    'rate_from_xof' => $row[4],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * [code, name, symbol, decimals, rate_from_xof]
     */
    private function currencies(): array
    {
        return [
            // Devises CFA & directement liées (peg EUR : 1 EUR = 655.957 XOF)
            ['XOF', 'Franc CFA BCEAO', 'CFA',     0, 1.0],
            ['XAF', 'Franc CFA BEAC',  'FCFA',    0, 1.0],
            ['EUR', 'Euro',            '€',       2, 0.00152449], // 1/655.957
            ['KMF', 'Franc comorien',  'CF',      0, 0.749],

            // Devises majeures
            ['USD', 'Dollar américain',         '$',   2, 0.001769],
            ['GBP', 'Livre sterling',           '£',   2, 0.001394],
            ['CHF', 'Franc suisse',             'CHF', 2, 0.001602],
            ['JPY', 'Yen japonais',             '¥',   0, 0.273],
            ['CNY', 'Yuan chinois',             '¥',   2, 0.01281],
            ['CAD', 'Dollar canadien',          'C$',  2, 0.00248],
            ['AUD', 'Dollar australien',        'A$',  2, 0.00272],
            ['NZD', 'Dollar néo-zélandais',     'NZ$', 2, 0.00298],
            ['HKD', 'Dollar de Hong Kong',      'HK$', 2, 0.01378],
            ['SGD', 'Dollar de Singapour',      'S$',  2, 0.00235],
            ['SEK', 'Couronne suédoise',        'kr',  2, 0.0186],
            ['NOK', 'Couronne norvégienne',     'kr',  2, 0.01885],
            ['DKK', 'Couronne danoise',         'kr',  2, 0.01138],
            ['ISK', 'Couronne islandaise',      'kr',  0, 0.246],
            ['CZK', 'Couronne tchèque',         'Kč',  2, 0.0387],
            ['PLN', 'Złoty polonais',           'zł',  2, 0.00736],
            ['HUF', 'Forint hongrois',          'Ft',  2, 0.625],
            ['RON', 'Leu roumain',              'lei', 2, 0.00777],
            ['BGN', 'Lev bulgare',              'лв',  2, 0.00298],
            ['HRK', 'Kuna croate',              'kn',  2, 0.01148],
            ['RUB', 'Rouble russe',             '₽',   2, 0.146],
            ['UAH', 'Hryvnia ukrainienne',      '₴',   2, 0.073],
            ['TRY', 'Livre turque',             '₺',   2, 0.063],
            ['ILS', 'Shekel israélien',         '₪',   2, 0.00641],

            // Afrique
            ['NGN', 'Naira nigérian',           '₦',   2, 2.74],
            ['GHS', 'Cedi ghanéen',             '₵',   2, 0.0260],
            ['ZAR', 'Rand sud-africain',        'R',   2, 0.0325],
            ['EGP', 'Livre égyptienne',         'E£',  2, 0.0867],
            ['MAD', 'Dirham marocain',          'DH',  2, 0.0176],
            ['DZD', 'Dinar algérien',           'DA',  2, 0.238],
            ['TND', 'Dinar tunisien',           'DT',  3, 0.00547],
            ['LYD', 'Dinar libyen',             'LD',  3, 0.00863],
            ['SDG', 'Livre soudanaise',         'SDG', 2, 1.062],
            ['SSP', 'Livre sud-soudanaise',     'SSP', 2, 7.85],
            ['ETB', 'Birr éthiopien',           'Br',  2, 0.222],
            ['KES', 'Shilling kényan',          'KSh', 2, 0.229],
            ['UGX', 'Shilling ougandais',       'USh', 0, 6.56],
            ['TZS', 'Shilling tanzanien',       'TSh', 2, 4.44],
            ['RWF', 'Franc rwandais',           'RF',  0, 2.49],
            ['BIF', 'Franc burundais',          'FBu', 0, 5.21],
            ['DJF', 'Franc djiboutien',         'Fdj', 0, 0.315],
            ['SOS', 'Shilling somalien',        'Sh',  2, 1.011],
            ['ERN', 'Nakfa érythréen',          'Nfk', 2, 0.0265],
            ['MGA', 'Ariary malgache',          'Ar',  2, 8.30],
            ['MUR', 'Roupie mauricienne',       '₨',   2, 0.0823],
            ['SCR', 'Roupie seychelloise',      '₨',   2, 0.0243],
            ['ZMW', 'Kwacha zambien',           'ZK',  2, 0.0479],
            ['MWK', 'Kwacha malawite',          'MK',  2, 3.07],
            ['AOA', 'Kwanza angolais',          'Kz',  2, 1.62],
            ['MZN', 'Metical mozambicain',      'MT',  2, 0.113],
            ['ZWL', 'Dollar zimbabwéen',        'Z$',  2, 0.640],
            ['BWP', 'Pula du Botswana',         'P',   2, 0.0241],
            ['NAD', 'Dollar namibien',          'N$',  2, 0.0325],
            ['LSL', 'Loti du Lesotho',          'L',   2, 0.0325],
            ['SZL', 'Lilangeni eswatini',       'E',   2, 0.0325],
            ['CDF', 'Franc congolais',          'FC',  2, 4.99],
            ['GMD', 'Dalasi gambien',           'D',   2, 0.123],
            ['GNF', 'Franc guinéen',            'FG',  0, 15.30],
            ['LRD', 'Dollar libérien',          'L$',  2, 0.317],
            ['SLL', 'Leone sierra-léonais',     'Le',  2, 39.6],
            ['SLE', 'Nouveau leone',            'Le',  2, 0.0396],
            ['CVE', 'Escudo cap-verdien',       '$',   2, 0.168],
            ['STN', 'Dobra de São Tomé',        'Db',  2, 0.0373],
            ['MRU', 'Ouguiya mauritanien',      'UM',  2, 0.0703],

            // Amériques
            ['MXN', 'Peso mexicain',            '$',   2, 0.0345],
            ['BRL', 'Réal brésilien',           'R$',  2, 0.0101],
            ['ARS', 'Peso argentin',            '$',   2, 1.97],
            ['CLP', 'Peso chilien',             '$',   0, 1.66],
            ['COP', 'Peso colombien',           '$',   2, 7.45],
            ['PEN', 'Sol péruvien',             'S/',  2, 0.00662],
            ['VES', 'Bolívar vénézuélien',      'Bs.', 2, 0.0879],
            ['UYU', 'Peso uruguayen',           '$U',  2, 0.0727],
            ['PYG', 'Guarani paraguayen',       '₲',   0, 13.65],
            ['BOB', 'Boliviano',                'Bs',  2, 0.0122],
            ['DOP', 'Peso dominicain',          'RD$', 2, 0.107],
            ['GTQ', 'Quetzal guatémaltèque',    'Q',   2, 0.0137],
            ['HNL', 'Lempira hondurien',        'L',   2, 0.0440],
            ['NIO', 'Córdoba nicaraguayen',     'C$',  2, 0.0651],
            ['CRC', 'Colón costaricain',        '₡',   2, 0.911],
            ['PAB', 'Balboa panaméen',          'B/.', 2, 0.001769],
            ['CUP', 'Peso cubain',              '$',   2, 0.0425],
            ['HTG', 'Gourde haïtienne',         'G',   2, 0.234],
            ['JMD', 'Dollar jamaïcain',         'J$',  2, 0.279],
            ['TTD', 'Dollar trinidadien',       'TT$', 2, 0.0120],
            ['BBD', 'Dollar barbadien',         'Bds$', 2, 0.00354],
            ['BSD', 'Dollar bahaméen',          'B$',  2, 0.001769],
            ['BZD', 'Dollar bélizien',          'BZ$', 2, 0.00354],
            ['XCD', 'Dollar des Caraïbes orientales', 'EC$', 2, 0.00478],
            ['SRD', 'Dollar surinamais',        '$',   2, 0.0626],
            ['GYD', 'Dollar guyanais',          'G$',  2, 0.370],
            ['AWG', 'Florin arubais',           'ƒ',   2, 0.00319],
            ['ANG', 'Florin antillais',         'ƒ',   2, 0.00319],
            ['KYD', 'Dollar des îles Caïmans',  'CI$', 2, 0.00147],
            ['BMD', 'Dollar bermudien',         '$',   2, 0.001769],

            // Asie
            ['INR', 'Roupie indienne',          '₹',   2, 0.1494],
            ['PKR', 'Roupie pakistanaise',      '₨',   2, 0.494],
            ['BDT', 'Taka bangladais',          '৳',   2, 0.211],
            ['LKR', 'Roupie srilankaise',       'Rs',  2, 0.535],
            ['NPR', 'Roupie népalaise',         '₨',   2, 0.239],
            ['BTN', 'Ngultrum bhoutanais',      'Nu',  2, 0.1494],
            ['MVR', 'Rufiyaa des Maldives',     'Rf',  2, 0.0273],
            ['AFN', 'Afghani',                  '؋',   2, 0.124],
            ['IRR', 'Rial iranien',             '﷼',   2, 74.5],
            ['IQD', 'Dinar irakien',            'ع.د', 3, 2.318],
            ['SYP', 'Livre syrienne',           '£',   2, 22.99],
            ['LBP', 'Livre libanaise',          'ل.ل', 2, 158.4],
            ['JOD', 'Dinar jordanien',          'JD',  3, 0.001253],
            ['KWD', 'Dinar koweïtien',          'د.ك', 3, 0.000543],
            ['BHD', 'Dinar bahreïni',           '.د.ب', 3, 0.000666],
            ['OMR', 'Rial omanais',             'ر.ع.', 3, 0.000681],
            ['QAR', 'Riyal qatari',             'ر.ق', 2, 0.00644],
            ['SAR', 'Riyal saoudien',           'ر.س', 2, 0.00663],
            ['AED', 'Dirham émirati',           'د.إ', 2, 0.00650],
            ['YER', 'Rial yéménite',            '﷼',   2, 0.443],
            ['THB', 'Baht thaïlandais',         '฿',   2, 0.0584],
            ['VND', 'Dong vietnamien',          '₫',   0, 44.85],
            ['LAK', 'Kip laotien',              '₭',   2, 38.3],
            ['KHR', 'Riel cambodgien',          '៛',   2, 7.13],
            ['MMK', 'Kyat birman',              'K',   2, 3.71],
            ['MYR', 'Ringgit malaisien',        'RM',  2, 0.00794],
            ['IDR', 'Roupie indonésienne',      'Rp',  2, 28.83],
            ['PHP', 'Peso philippin',           '₱',   2, 0.0991],
            ['BND', 'Dollar de Brunei',         'B$',  2, 0.00235],
            ['TWD', 'Dollar de Taïwan',         'NT$', 2, 0.0573],
            ['KRW', 'Won sud-coréen',           '₩',   0, 2.41],
            ['KPW', 'Won nord-coréen',          '₩',   2, 1.592],
            ['MNT', 'Tugrik mongol',            '₮',   2, 6.05],
            ['MOP', 'Pataca de Macao',          'MOP$', 2, 0.01419],
            ['KZT', 'Tenge kazakh',             '₸',   2, 0.882],
            ['UZS', 'Som ouzbek',               'soʻm', 2, 22.45],
            ['TJS', 'Somoni tadjik',            'SM',  2, 0.01890],
            ['KGS', 'Som kirghize',             'с',   2, 0.1539],
            ['TMT', 'Manat turkmène',           'T',   2, 0.00619],
            ['AZN', 'Manat azerbaïdjanais',     '₼',   2, 0.00301],
            ['GEL', 'Lari géorgien',            '₾',   2, 0.00480],
            ['AMD', 'Dram arménien',            '֏',   2, 0.682],
            ['BYN', 'Rouble biélorusse',        'Br',  2, 0.00586],
            ['MDL', 'Leu moldave',              'L',   2, 0.0313],
            ['RSD', 'Dinar serbe',              'дин.', 2, 0.1788],
            ['MKD', 'Denar macédonien',         'ден.', 2, 0.0939],
            ['ALL', 'Lek albanais',             'L',   2, 0.1565],
            ['BAM', 'Mark convertible',         'KM',  2, 0.00298],

            // Océanie & Pacifique
            ['FJD', 'Dollar fidjien',           'FJ$', 2, 0.00401],
            ['PGK', 'Kina papouasien',          'K',   2, 0.00712],
            ['SBD', 'Dollar des îles Salomon',  'SI$', 2, 0.01494],
            ['TOP', 'Pa\'anga tongan',          'T$',  2, 0.00419],
            ['VUV', 'Vatu vanuatuan',           'Vt',  0, 0.214],
            ['WST', 'Tala samoan',              'WS$', 2, 0.00489],
            ['XPF', 'Franc CFP',                'F',   0, 0.182],
        ];
    }
}
