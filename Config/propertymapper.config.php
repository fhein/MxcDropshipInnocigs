<?php
return [
    'article_name_option_fixes'     => [
        'blau-prisma'     => 'prisma-blau',
        'minz-grün'     => 'minzgrün',
        'chrom-prisma'     => [
            'chrome-prisma',
            'prisma-chrom'
        ],
        'gold-prisma'     => 'prisma-gold',
        'gunmetal-prisma'     => 'prisma-gunmetal',
        'regenbogen-prisma'     => 'prisma-regenbogen',
        'rot-prisma'     => 'prisma-rot',
        'grün-prisma'     => 'prisma-grün',
        'gunmetal chrome'     => 'gunmetal-chrom',
        'auto pink'     => 'auto-pink',
        '10 mg/ml'     => '- 10mg/ml',
        '20 mg/ml'     => '- 20mg/ml',
        'grau-weiß'     => 'grau-weiss',
        '0,25 Ohm'     => '0,25',
        '0,4 Ohm'     => '0,4',
        '1000er Packung'     => '1000er Packubng',
        '20er Packung'     => [
            '20er Packug',
            '(20 Stück Pro Packung)'
        ],
        'resin-rot'     => 'Resin rot',
        'resin-gelb'     => 'Resin gelb',
        '0 mg/ml'       => '0 mg/mgl',
        'weiss'     => ' weiß',
        '1,5 mg/ml'     => '1,5 ml',
        '3 mg/ml'       => '3mg/ml',
        'gebürsteter stahl'     => 'gebürsteter Stahl',
        'dunkelgrün'     => 'dunkel grün',
        '6 mg/ml'     => '6mg/ml',
        'matt-schwarz'     => 'matt schwarz',
        'schwarz-weiss'     => 'schwarz-weiß',
        'schwarz-weiß'     => 'schwarz-weiss',
        'weiß'     => 'weiss',
        '50PG / 50VG'     => [
            '50PG/50VG',
            '50VG/50PG'
        ],
        '70VG / 30PG'     => '70VG/30PG',
        '80VG / 20PG'     => '80VG/20PG',
        'regenbogen'     => 'iridescent',
        '28 GA'     => '28GA',
        '26 GA'     => '26GA',
        '24 GA'     => '24GA',
        '22 GA'     => '22GA',
        '26 GA*3+36 GA'     => '26GA*3+36GA',
        '28 GA*3+36 GA'     => '28GA*3+36GA',
        '30 GA*3+36 GA'     => '30GA*3+38GA',
        '24 GA*2+32 GA'     => '24GA*2+32GA',
        '28 GA*2+32 GA'     => '28GA*2+32GA',
        '26 GA+32 GA'     => '26GA+32GA',
        '28 GA*2+30 GA'     => '28GA*2+30GA'
    ],

    'article_codes'         => [],
    'article_names'        => [
        'Vampire Vape Applelicious - E-Zigaretten Liquid'     => 'Vampire Vape - Applelicious - E-Zigaretten Liquid',
    ],

    'article_name_replacements'     => [
        'preg_replace' => [
            '~0ml\/ml~'                                                     => '0mg/ml',
            '~((1 Liter)|(\d+ ml)) (Basis)~'                                => '$4 - $1',
            '~1 Liter~'                                                     => '1.000 ml',
            '~E-Zigaretten (Liquid)~'                                       => '- $1',
            '~(Liquid) für E-Zigaretten~'                                   => '$1',
            '~Aroma (- Liquid)~'                                            => '$1',
            '~(\d\d*)m~'                                                    => '$1 m',
            '~-(\d+) ml~'                                                   => '- $1 ml',
            '~(\d) ?mAH~'                                                   => '$1 mAh',
            '~ (\d+) mAh~'                                                  => ', $1 mAh',
            '~(\d{2})VG */ *(\d{2})PG~'                                     => '- VG/PG: $1:$2,',
            '~(\d{2})PG */ *(\d{2})VG~'                                     => '- VG/PG: $2:$1,',
            '~(\d)(\d{3}) mAh~'                                             => '$1.$2 mAh',
            '~([^,\-]) (\d) m$~'                                            => '$1 - $2 m',
            '~([^,\-]) (\d,\d+) Ohm~'                                       => '$1, $2 Ohm',
            '~, (\d,\d+ Ohm) Heads?~'                                       => ' Heads, $1',
            '~(\d)ml~'                                                      => '$1 ml',
            '~(\d) ?mg~'                                                    => '$1 mg/ml',
            '~ml\/ml~'                                                      => 'ml',
            '~ (\d+) Watt~'                                                 => ', $1 Watt',
            '~ml - (\d)~'                                                   => 'ml, $1',
            '~([^,\-]) (\d(,\d+)?) ?ml~'                                    => '$1, $2 ml',
            '~(\d+)ML(.*Leerflasche)~'                                      =>'$2, $1 ml',
            '~ml +(\d)~'                                                     => 'ml, $1',
            '~([^,\-]) (\d+) ml$~'                                          => '$1 - $2 ml',
            '~([^,\-]) (\d+) mg\/ml$~'                                      => '$1 - $2 mg/ml',
            '~(\d+ mg/ml),? (\d+ ml)~'                                      => '$2, $1',
            '~([^,\-]) (\d+ ml, \d+ mg/ml)~'                                => '$1 - $2',
            '~(Treib.*100 ml$)~'                                            => '$1, 0 mg/ml',
            '~Rebelz (- Aroma)(.*)(- \d+ ml)~'                              => 'Rebelz - $2 $1 $3',
            '~Vape( -)?( Aroma)(.*)(- \d+ ml)~'                             => 'Vape - $3 -$2 $4',
            '~(Vampire Vape) ([^\-])~'                                      => '$1 - $2',
            '~(VLADS VG) Liquid (-.*)~'                                     => '$1 $2 - Liquid',
            '~(Bull) (- Aroma)(.*)(- \d+ ml)~'                              => '$1 - $3 $2 $4',
            '~(SC -) (Aroma) (.*)~'                                         => '$1 $3 - $2',
            '~- Twisted (Aroma) - (.*)(- \d+ ml)~'                          => '- $2 - $1 $3',
            '~(I VG - )(Aroma) (.*)(- \d+ ml)~'                             => '$1 $3 - $2 $4',
            '~(Bozz Liquids -) (Aroma)(.*)(- \d+ ml)~'                      => '$1 $3 - $2 $4',
            '~(Flavorist -) (Aroma)(.*)(- \d+ ml)~'                         => '$1 $3 - $2 $4',
            '~(VapeTastic -) (Aroma) - (.*)(- \d+ ml)~'                     => '$1 $3 - $2 $4',
            '~(Twisted -) (Cryostasis|Road Trip) (Aroma)(.*)(- \d+ ml)~'    => '$1 $2 $4 - $3 $5',
            '~((SC)|(InnoCigs))(.*)((- )?(Liquid)|(Aroma))$~'               => '$1$4$6$5 - 10 ml',
            '~(SC) (- Vape Base)(.*-)(.*)~'                                 => '$1 $3 $2 - $4',
            '~^(Erste Sahne) ([^\-])~'                                      => '$1 - $2',
            '~(John Smith.*) (- \d+ ml)~'                                   => '$1 - Aroma $2',
            '~Heads?~'                                                      => 'Verdampferkopf',
            '~((Vape)|(SC)) - (\d+ ml) (Shot) - (.*), (.*)~'                => '$1 - $5 - $6, $4, $7',
            '~(Solt) (\d+ ml) (.*) - (.*), (.*)~'                           => '$1 - $3 - $4, $2, $5',
            '~ - ?$~'                                                       => '',
            '~\s+~'                                                         => ' ',
            '~(-\s)+~'                                                      => '- ',
        ],
        'str_replace' => [
            'SINUOUS'                               => 'Sinuous',
            'GNOME'                                 => 'Gnome',
            'Verdampferkopf Verdampferkopf'         => 'Verdampferkopf',
            'Sherbert'                              => 'Sherbet',
            ' Core Dual '                           => ' ',
            ' Core '                                => ' ',
            'Afternoon Vanille-Käsekuchen'          => 'Afternoon',
            'Always Cola'                           => 'Always',
            'Angels in Heaven Tabak'                => 'Angels in Heaven',
            'Blue Spot Blaubeeren'                  => 'Blue Spot',
            'Brown Nutty Nougat'                    => 'Brown Nutty',
            'Caribbean Kokos-Schokoladen'           => 'Carribean',
            'Celestial Dragon Tabak'                => 'Celestial Dragon',
            'Cold Vacci Heidelbeere-Fresh'          => 'Cold Vacci',
            'Commander Joe Tabak'                   => 'Commander Joe',
            'Devils Darling Tabak'                  => 'Devils Darling',
            'First Man Apfel'                       => 'First Man',
            'First Money Orangenlimonade'           => 'First Money',
            'Green Angry Limetten'                  => 'Green Angry',
            'Hairy Fluffy Pfirsich'                 => 'Hairy Fluffy',
            'Inside Red Wassermelonen'              => 'Inside Red',
            'La Renaissance Tabak-Schokoladen'      => 'La Renaissance',
            'Little Soft Himbeer'                   => 'Little Soft',
            'Master Wood Waldmeister'               => 'Master Wood',
            'Milli Vanille'                         => 'Milli',
            'Monkey Around Bananen-Amarenakirsche'  => 'Monkey Around',
            'Pretty Sweetheart Sahne-Erdbeer'       => 'Pretty Sweetheart',
            'Red Cyclone Rote Früchte'              => 'Red Cyclone',
            'Red Violet Amarenakirsche'             => 'Red Violet',
            'Rounded Yellow Honigmelonen'           => 'Rounded Yello',
            'Spiky Maracuja'                        => 'Spiky',
            'Star Spangled Tabak'                   => 'Star Spangled',
            'Sweetheart Erdbeer'                    => 'Sweatheart',
            'The Empire Tabak Nuss'                 => 'The Empire',
            'The Rebels Tabak Vanille'              => 'The Rebels',
            'White Glacier Fresh'                   => 'White Glacier',
            'Wild West Tabakaroma'                  => 'Wild West',
            'Virginias Best Tabak'                  => 'Virginia\'s Best',
            'Strong Taste Tabak'                    => 'Strong Taste',
            'RY4 Tabak'                             => 'RY4',
            'Pure Tabakaroma'                       => 'Pure',
            'Americas Finest Tabak'                 => 'America\'s Finest',
            'E-Zigaretten Nikotinsalz Liquid'       => 'Nikotinsalz-Liquid',
            'Heads Heads'                           => 'Heads',
            'AsMODus'                               => 'asMODus',
            'mit,'                                  => 'mit',
            'Pro P'                                 => 'pro P',
            'pro Pack)'                             => 'pro Packung)',
            'St. pro'                               => 'Stück pro',
            '5er Pack'                              => '5 Stück pro Packung',
            '10er Packung'                          => '(10 Stück pro Packung)',
            '(Dual Coil), 1,5 Ohm'                  => '- Dual Coil, 1,5 Ohm',
            'Vape Base'                             => 'Shake & Vape',
            'Vape Verdampferkopf'                   => 'Vape Head',
            'mAh 40A'                               => 'mAh, 40 A',
            'P80 Watt'                              => 'P80, 80 Watt',
            '+ Adapter'                             => ', mit Adapter',
            'Limited Edition - 30 ml'               => 'Limited Edition - Aroma - 30 ml',
            '- -'                                   => '-',
            ' ,'                                    => ',',
            ', -'                                   => '-',
        ],
    ],
    'group_names'     => [
        'STAERKE'           => 'Nikotinstärke',
        'WIDERSTAND'        => 'Widerstand',
        'PACKUNG'           => 'Packungsgröße',
        'FARBE'             => 'Farbe',
        'DURCHMESSER'       => 'Durchmesser',
        'GLAS'              => 'Glas',
    ],
    'option_names'          => [
        'minz-grün'     => 'minzgrün',
    ],
    'variant_codes'         => [],
    'manufacturers'         => [
        'Smok'     => [
            'supplier'      => 'Smoktech',
            'brand'         => 'Smok'
        ],
        'Renova'     => [
            'supplier'      => 'Vaporesso',
            'brand'         => 'Renova',
        ],
        'Dexter`s Juice Lab'     => [
            'brand'     => 'Dexter\'s Juice Lab',
            'supplier'     => 'Dexter\'s Juice Lab',
        ]
    ],
    'categories'     => [
        'source' => [
            'preg_match' => [
                '~Liquids \> Easy 3 Caps~' => 'Liquids > Easy 3 Caps',
                '~E-Zigaretten~'    => 'E-Zigaretten',
                '~TWIST~.*- \d~'    => 'Shake & Vape',
                '~^Alt > Joye~'     => 'Zubehör',
                '~Clearomizer~'    => 'Verdampfer',
                '~^Box Mods~'       => 'Akkuträger',
                '~((Aspire)|(InnoCigs)|(Steamax) )?Zubehör~'  => 'Zubehör',
                '~Ladegerät~'       => 'Ladegeräte',
                '~Shake & Vape~'    => 'Shake & Vape',
                '~VLADS VG~'        => 'Liquids',
                '~Basen und Shots~' => 'Basen & Shots',
                '~Vaporizer~'       => 'Vaporizer'

            ],
        ],
        'name' => [
            'preg_match' => [
                '~TWIST~' => 'Shake & Vape',
                '~COTN~'  => 'Zubehör',
                '~Werkzeug-Set~' => 'Zubehör',
                '~Verdampferkopf~' => ' Zubehör',
                '~(Basis)|(Shot)~' => 'Basen & Shots',
                '~Aroma~' => 'Aromen',
                '~Liquid~' => 'Liquid',
                '~0 mg/ml~' => 'Shake & Vape',
                '~Flavor~' => 'Aromen',
                '~Vampire Vape.*Limited Edition~' => 'Aromen',
                '~iWu Abdeck~' => 'Zubehör',
                '~Ladegerät~' => 'Ladegeräte',
            ]
        ]
    ],

    'innocigs_brands' => [ 'SC', 'Steamax', 'InnoCigs'],

    'articles'     => 'This key is reserverd for PropertyMapperFactory',
];