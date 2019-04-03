<?php

use MxcDropshipInnocigs\Mapping\Import\ArticleTypeMapper;

return [
    'types' => [
        ArticleTypeMapper::TYPE_UNKNOWN            => 'UNKNOWN',
        ArticleTypeMapper::TYPE_E_CIGARETTE        => 'E_CIGARETTE',
        ArticleTypeMapper::TYPE_BOX_MOD            => 'BOX_MOD',
        ArticleTypeMapper::TYPE_E_PIPE             => 'E_PIPE',
        ArticleTypeMapper::TYPE_CLEAROMIZER        => 'CLEAROMIZER',
        ArticleTypeMapper::TYPE_CLEAROMIZER_RTA    => 'CLEAROMIZER_RTA',
        ArticleTypeMapper::TYPE_CLEAROMIZER_RDTA   => 'CLEAROMIZER_RDTA',
        ArticleTypeMapper::TYPE_CLEAROMIZER_RDSA   => 'CLEAROMIZER_RDSA',
        ArticleTypeMapper::TYPE_LIQUID             => 'LIQUID',
        ArticleTypeMapper::TYPE_AROMA              => 'AROMA',
        ArticleTypeMapper::TYPE_SHAKE_VAPE         => 'SHAKE_VAPE',
        ArticleTypeMapper::TYPE_HEAD               => 'HEAD',
        ArticleTypeMapper::TYPE_TANK               => 'TANK',
        ArticleTypeMapper::TYPE_SEAL               => 'SEAL',
        ArticleTypeMapper::TYPE_DRIP_TIP           => 'DRIP_TIP',
        ArticleTypeMapper::TYPE_POD                => 'POD',
        ArticleTypeMapper::TYPE_CARTRIDGE          => 'CARTRIDGE',
        ArticleTypeMapper::TYPE_CELL               => 'CELL',
        ArticleTypeMapper::TYPE_CELL_BOX           => 'CELL_BOX',
        ArticleTypeMapper::TYPE_BASE               => 'BASE',
        ArticleTypeMapper::TYPE_CHARGER            => 'CHARGER',
        ArticleTypeMapper::TYPE_BAG                => 'BAG',
        ArticleTypeMapper::TYPE_TOOL               => 'TOOL',
        ArticleTypeMapper::TYPE_WADDING            => 'WADDING', // Watte
        ArticleTypeMapper::TYPE_WIRE               => 'WIRE',
        ArticleTypeMapper::TYPE_BOTTLE             => 'BOTTLE',
        ArticleTypeMapper::TYPE_SQUONKER_BOTTLE    => 'SQUONKER_BOTTLE',
        ArticleTypeMapper::TYPE_VAPORIZER          => 'VAPORIZER',
        ArticleTypeMapper::TYPE_SHOT               => 'SHOT',
        ArticleTypeMapper::TYPE_CABLE              => 'CABLE',
        ArticleTypeMapper::TYPE_BOX_MOD_CELL       => 'BOX_MOD_CELL',
        ArticleTypeMapper::TYPE_COIL               => 'COIL',
        ArticleTypeMapper::TYPE_RDA_BASE           => 'RDA_BASE',
        ArticleTypeMapper::TYPE_MAGNET             => 'MAGNET',
        ArticleTypeMapper::TYPE_MAGNET_ADAPTOR     => 'MAGNET_ADAPTER',
        ArticleTypeMapper::TYPE_ACCESSORY          => 'ACCESSORY',
        ArticleTypeMapper::TYPE_BATTERY_CAP        => 'BATTERY_CAP',
        ArticleTypeMapper::TYPE_EXTENSION_KIT      => 'EXTENSION_KIT',
        ArticleTypeMapper::TYPE_CONVERSION_KIT     => 'CONVERSION_KIT',
        ArticleTypeMapper::TYPE_E_HOOKAH           => 'E_HOOKAH',
        ArticleTypeMapper::TYPE_SQUONKER_BOX       => 'SQUONKER_BOX',
        ArticleTypeMapper::TYPE_EMPTY_BOTTLE       => 'EMPTY_BOTTLE',
        ArticleTypeMapper::TYPE_EASY3_CAP          => 'EASY3_CAP',
        ArticleTypeMapper::TYPE_DECK               => 'DECK',
        ArticleTypeMapper::TYPE_HEATING_PLATE      => 'HEATING_PLATE',
        ArticleTypeMapper::TYPE_TOOL_HEATING_PLATE => 'TOOL_HEATING_PLATE',
        ArticleTypeMapper::TYPE_DRIP_TIP_CAP       => 'DRIP_TIP_CAP',
        ArticleTypeMapper::TYPE_TANK_PROTECTION    => 'TANK_PROTECTION',
        ArticleTypeMapper::TYPE_STORAGE            => 'STORAGE',
        ArticleTypeMapper::TYPE_BATTERY_SLEEVE     => 'BATTERY_SLEEVE',
    ],
    
    'name_type_mapping' => [
        '~RTA.*Clearomizer~'  => ArticleTypeMapper::TYPE_CLEAROMIZER_RTA,
        '~RDSA.*Clearomizer~' => ArticleTypeMapper::TYPE_CLEAROMIZER_RDSA,
        '~RDTA.*Clearomizer~' => ArticleTypeMapper::TYPE_CLEAROMIZER_RDTA,
        '~Clearomizer~'       => ArticleTypeMapper::TYPE_CLEAROMIZER,
        '~Cartridge~'         => ArticleTypeMapper::TYPE_CARTRIDGE,
        '~Pod~'               => ArticleTypeMapper::TYPE_POD,
        '~E-Pfeife~'          => ArticleTypeMapper::TYPE_E_PIPE,
        '~E-Hookah~'          => ArticleTypeMapper::TYPE_E_HOOKAH,
        '~Vaporizer~'         => ArticleTypeMapper::TYPE_VAPORIZER,
        '~Aroma ~'            => ArticleTypeMapper::TYPE_AROMA,
        '~Guillotine.*Base~'  => ArticleTypeMapper::TYPE_RDA_BASE,
        '~Base~'              => ArticleTypeMapper::TYPE_BASE,
        '~Shot~'              => ArticleTypeMapper::TYPE_SHOT,
        '~Akkuträger~'        => ArticleTypeMapper::TYPE_BOX_MOD_CELL,
        '~Akku~'              => ArticleTypeMapper::TYPE_BOX_MOD,
        '~Squonker Box~'      => ArticleTypeMapper::TYPE_SQUONKER_BOX,
        '~Squonker Flasche~'  => ArticleTypeMapper::TYPE_SQUONKER_BOTTLE,
        '~Liquid Flasche~'    => ArticleTypeMapper::TYPE_SQUONKER_BOTTLE,
        '~Leerflasche~'       => ArticleTypeMapper::TYPE_EMPTY_BOTTLE,
        '~Shake & Vape~'      => ArticleTypeMapper::TYPE_SHAKE_VAPE,
        '~Liquid~'            => ArticleTypeMapper::TYPE_LIQUID,
        '~Easy 3.*Cap~'       => ArticleTypeMapper::TYPE_EASY3_CAP,
        '~Head~'              => ArticleTypeMapper::TYPE_HEAD,
        '~[Tt]asche~'         => ArticleTypeMapper::TYPE_BAG,
        '~Lederschale~'       => ArticleTypeMapper::TYPE_BAG,
        '~E-Zigarette~'       => ArticleTypeMapper::TYPE_E_CIGARETTE,
        '~Deck~'              => ArticleTypeMapper::TYPE_DECK,
        '~Watte~'             => ArticleTypeMapper::TYPE_WADDING,
        '~Wickeldraht~'       => ArticleTypeMapper::TYPE_WIRE,
        '~Coil~'              => ArticleTypeMapper::TYPE_COIL,
        '~Ladegerät~'         => ArticleTypeMapper::TYPE_CHARGER,
        '~DigiCharger~'       => ArticleTypeMapper::TYPE_CHARGER,
        '~[Ss]tecker~'        => ArticleTypeMapper::TYPE_CHARGER,
        '~[Kk]abel~'          => ArticleTypeMapper::TYPE_CABLE,
        '~Werkzeug~'          => ArticleTypeMapper::TYPE_TOOL,
        '~[Pp]inzette~'       => ArticleTypeMapper::TYPE_TOOL,
        '~NCFilm~'            => ArticleTypeMapper::TYPE_HEATING_PLATE,
        '~Heizplatte~'        => ArticleTypeMapper::TYPE_TOOL_HEATING_PLATE,
        '~Drip Cap~'          => ArticleTypeMapper::TYPE_DRIP_TIP_CAP,
        '~Mundstück~'         => ArticleTypeMapper::TYPE_DRIP_TIP,
        '~Drip Tip~'          => ArticleTypeMapper::TYPE_DRIP_TIP,
        '~Glastank~'          => ArticleTypeMapper::TYPE_TANK,
        '~Ultem Tank~'        => ArticleTypeMapper::TYPE_TANK,
        '~Top-Kappe~'         => ArticleTypeMapper::TYPE_TANK,
        '~Hollowed Out Tank~' => ArticleTypeMapper::TYPE_TANK_PROTECTION,
        '~Tankschutz~'        => ArticleTypeMapper::TYPE_TANK_PROTECTION,
        '~Umbausatz~'         => ArticleTypeMapper::TYPE_CONVERSION_KIT,
        '~Erweiterungssatz~'  => ArticleTypeMapper::TYPE_EXTENSION_KIT,
        '~[Dd]ichtung~'       => ArticleTypeMapper::TYPE_SEAL,
        '~O-Ring~'            => ArticleTypeMapper::TYPE_SEAL,
        '~Vitrine~'           => ArticleTypeMapper::TYPE_STORAGE,
        '~Abdeckung~'         => ArticleTypeMapper::TYPE_ACCESSORY,
        '~Vape Band~'         => ArticleTypeMapper::TYPE_ACCESSORY,
        '~Magnet.*Adapter~'   => ArticleTypeMapper::TYPE_MAGNET_ADAPTOR,
        '~[Mm]agnet~'         => ArticleTypeMapper::TYPE_MAGNET,
        '~Batteriehülse~'     => ArticleTypeMapper::TYPE_BATTERY_SLEEVE,
        '~Batteriekappe~'     => ArticleTypeMapper::TYPE_BATTERY_CAP,
    ],
];
