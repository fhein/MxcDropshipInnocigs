<?php

use MxcDropshipIntegrator\Mapping\Import\TypeMapper;

return [
    'types' => [
        TypeMapper::TYPE_UNKNOWN            => 'UNKNOWN',
        TypeMapper::TYPE_E_CIGARETTE        => 'E_CIGARETTE',
        TypeMapper::TYPE_BOX_MOD            => 'BOX_MOD',
        TypeMapper::TYPE_E_PIPE             => 'E_PIPE',
        TypeMapper::TYPE_CLEAROMIZER        => 'CLEAROMIZER',
        TypeMapper::TYPE_CLEAROMIZER_ADA    => 'CLEAROMIZER_ADA',
        TypeMapper::TYPE_CLEAROMIZER_RTA    => 'CLEAROMIZER_RTA',
        TypeMapper::TYPE_CLEAROMIZER_RDA    => 'CLEAROMIZER_RDA',
        TypeMapper::TYPE_CLEAROMIZER_RDTA   => 'CLEAROMIZER_RDTA',
        TypeMapper::TYPE_LIQUID             => 'LIQUID',
        TypeMapper::TYPE_LIQUID_BOX         => 'LIQUID_BOX',
        TypeMapper::TYPE_AROMA              => 'AROMA',
        TypeMapper::TYPE_SHAKE_VAPE         => 'SHAKE_VAPE',
        TypeMapper::TYPE_HEAD               => 'HEAD',
        TypeMapper::TYPE_TANK               => 'TANK',
        TypeMapper::TYPE_SEAL               => 'SEAL',
        TypeMapper::TYPE_DRIP_TIP           => 'DRIP_TIP',
        TypeMapper::TYPE_POD                => 'POD',
        TypeMapper::TYPE_CARTRIDGE          => 'CARTRIDGE',
        TypeMapper::TYPE_CELL               => 'CELL',
        TypeMapper::TYPE_CELL_BOX           => 'CELL_BOX',
        TypeMapper::TYPE_BASE               => 'BASE',
        TypeMapper::TYPE_CHARGER            => 'CHARGER',
        TypeMapper::TYPE_BAG                => 'BAG',
        TypeMapper::TYPE_TOOL               => 'TOOL',
        TypeMapper::TYPE_WADDING            => 'WADDING', // Watte
        TypeMapper::TYPE_WIRE               => 'WIRE',
        TypeMapper::TYPE_SQUONKER_BOTTLE    => 'SQUONKER_BOTTLE',
        TypeMapper::TYPE_VAPORIZER          => 'VAPORIZER',
        TypeMapper::TYPE_SHOT               => 'SHOT',
        TypeMapper::TYPE_CABLE              => 'CABLE',
        TypeMapper::TYPE_BOX_MOD_CELL       => 'BOX_MOD_CELL',
        TypeMapper::TYPE_COIL               => 'COIL',
        TypeMapper::TYPE_RDA_BASE           => 'RDA_BASE',
        TypeMapper::TYPE_MAGNET             => 'MAGNET',
        TypeMapper::TYPE_MAGNET_ADAPTOR     => 'MAGNET_ADAPTOR',
        TypeMapper::TYPE_ACCESSORY          => 'ACCESSORY',
        TypeMapper::TYPE_BATTERY_CAP        => 'BATTERY_CAP',
        TypeMapper::TYPE_EXTENSION_KIT      => 'EXTENSION_KIT',
        TypeMapper::TYPE_CONVERSION_KIT     => 'CONVERSION_KIT',
        TypeMapper::TYPE_E_HOOKAH           => 'E_HOOKAH',
        TypeMapper::TYPE_SQUONKER_BOX       => 'SQUONKER_BOX',
        TypeMapper::TYPE_EMPTY_BOTTLE       => 'EMPTY_BOTTLE',
        TypeMapper::TYPE_EASY3_CAP          => 'EASY3_CAP',
        TypeMapper::TYPE_DECK               => 'DECK',
        TypeMapper::TYPE_HEATING_PLATE      => 'HEATING_PLATE',
        TypeMapper::TYPE_TOOL_HEATING_PLATE => 'TOOL_HEATING_PLATE',
        TypeMapper::TYPE_DRIP_TIP_CAP       => 'DRIP_TIP_CAP',
        TypeMapper::TYPE_TANK_PROTECTION    => 'TANK_PROTECTION',
        TypeMapper::TYPE_STORAGE            => 'STORAGE',
        TypeMapper::TYPE_BATTERY_SLEEVE     => 'BATTERY_SLEEVE',
        TypeMapper::TYPE_CLEANING_SUPPLY    => 'CLEANING_SUPPLY',
        TypeMapper::TYPE_COVER              => 'COVER',
        TypeMapper::TYPE_DISPLAY            => 'DISPLAY',
        TypeMapper::TYPE_SPARE_PARTS        => 'SPARE_PARTS',
        TypeMapper::TYPE_POD_SYSTEM         => 'POD_SYSTEM',
        TypeMapper::TYPE_NICSALT_LIQUID     => 'NICSALT_LIQUID'
    ],

    'name_type_mapping' => [
        '~TGO Pod Mod~'             => TypeMapper::TYPE_POD_SYSTEM,
        '~Ersatzteil-Set~'          => TypeMapper::TYPE_SPARE_PARTS,
        '~Kamin~'                   => TypeMapper::TYPE_SPARE_PARTS,
        '~T-Shirt~'                 => TypeMapper::TYPE_ACCESSORY,
        '~Reinigungsbürste~'        => TypeMapper::TYPE_TOOL,
        '~Seitenschneider~'         => TypeMapper::TYPE_TOOL,
        '~Keramikstäbe~'            => TypeMapper::TYPE_TOOL,
        '~521 Tab Mini~'            => TypeMapper::TYPE_TOOL,
        '~[Pp]inzette~'             => TypeMapper::TYPE_TOOL,
        '~Werkzeug~'                => TypeMapper::TYPE_TOOL,
        '~Wickelhilfe~'             => TypeMapper::TYPE_TOOL,
        '~Flaschenöffner~'          => TypeMapper::TYPE_TOOL,
        '~Vsticking - SMI ADA~'     => TypeMapper::TYPE_CLEAROMIZER_ADA,
        '~Vsticking - SMA ADA~'     => TypeMapper::TYPE_CLEAROMIZER_ADA,
        '~V3 mini ADA~'             => TypeMapper::TYPE_CLEAROMIZER_ADA,
        '~SX-ADA~'                  => TypeMapper::TYPE_CLEAROMIZER_ADA,
        '~RTA.*Verdampfer~'         => TypeMapper::TYPE_CLEAROMIZER_RTA,
        '~RDSA.*Verdampfer~'        => TypeMapper::TYPE_CLEAROMIZER_RDA,
        '~RDTA.*Verdampfer~'        => TypeMapper::TYPE_CLEAROMIZER_RDTA,
        '~RDA.*Verdampfer~'         => TypeMapper::TYPE_CLEAROMIZER_RDA,
        '~SX-RSA.*Verdampfer~'      => TypeMapper::TYPE_CLEAROMIZER_RDA,
        '~Verdampferständer~'       => TypeMapper::TYPE_STORAGE,
        '~Verdampfer~'              => TypeMapper::TYPE_CLEAROMIZER,
        '~Cartridge~'               => TypeMapper::TYPE_CARTRIDGE,
        '~Nikotinsalz-Liquid~'      => TypeMapper::TYPE_NICSALT_LIQUID,
        '~QPod - E-Zigarette~'      => TypeMapper::TYPE_E_CIGARETTE,
        '~Pod - E-Zigarette~'       => TypeMapper::TYPE_E_CIGARETTE,
        '~Pod E-Zigarette~'         => TypeMapper::TYPE_E_CIGARETTE,
        '~Preva Pod - E-Zigarette~' => TypeMapper::TYPE_E_CIGARETTE,
        '~PodStick - E-Zigarette~'  => TypeMapper::TYPE_E_CIGARETTE,
        '~Podin.*E-Zigarette~'      => TypeMapper::TYPE_E_CIGARETTE,
        '~Pod HP Head~'             => TypeMapper::TYPE_HEAD,
        '~Druga Narada Pod Head~'   => TypeMapper::TYPE_HEAD,
        '~Podin Head~'              => TypeMapper::TYPE_HEAD,
        '~Pod~'                     => TypeMapper::TYPE_POD,
        '~Unipod~'                  => TypeMapper::TYPE_POD,
        '~E-Pfeife~'                => TypeMapper::TYPE_E_PIPE,
        '~E-Hookah~'                => TypeMapper::TYPE_E_HOOKAH,
        '~Vaporizer~'               => TypeMapper::TYPE_VAPORIZER,
        '~Aroma ~'                  => TypeMapper::TYPE_AROMA,
        '~Guillotine.*Base~'        => TypeMapper::TYPE_RDA_BASE,
        '~Base~'                    => TypeMapper::TYPE_BASE,
        '~Shot~'                    => TypeMapper::TYPE_SHOT,
        '~Akkuzelle~'               => TypeMapper::TYPE_CELL,
        '~Akkubox~'                 => TypeMapper::TYPE_CELL_BOX,
        '~Akkuträger~'              => TypeMapper::TYPE_BOX_MOD_CELL,
        '~Akku~'                    => TypeMapper::TYPE_BOX_MOD,
        '~Squonker Box~'            => TypeMapper::TYPE_SQUONKER_BOX,
        '~Squonker Flasche~'        => TypeMapper::TYPE_SQUONKER_BOTTLE,
        '~Liquid Flasche~'          => TypeMapper::TYPE_SQUONKER_BOTTLE,
        '~Liquidflasche~'           => TypeMapper::TYPE_SQUONKER_BOTTLE,
        '~Leerflasche~'             => TypeMapper::TYPE_EMPTY_BOTTLE,
        '~Shake & Vape~'            => TypeMapper::TYPE_SHAKE_VAPE,
        '~Probierbox - Liquid~'     => TypeMapper::TYPE_LIQUID_BOX,
        '~Liquid~'                  => TypeMapper::TYPE_LIQUID,
        '~Easy 3.*Cap~'             => TypeMapper::TYPE_EASY3_CAP,
        '~Fused Clapton Head~'      => TypeMapper::TYPE_COIL,
        '~Head~'                    => TypeMapper::TYPE_HEAD,
        '~Hellcoil~'                => TypeMapper::TYPE_HEAD,
        '~[Tt]asche~'               => TypeMapper::TYPE_BAG,
        '~Lederschale~'             => TypeMapper::TYPE_BAG,
        '~Lederhülle~'              => TypeMapper::TYPE_BAG,
        '~Steam Bag~'               => TypeMapper::TYPE_BAG,
        '~E-Zigarette~'             => TypeMapper::TYPE_E_CIGARETTE,
        '~Deck~'                    => TypeMapper::TYPE_DECK,
        '~Airflow Resistor~'        => TypeMapper::TYPE_DECK, // @todo: No deck
        '~Mesh Wire~'               => TypeMapper::TYPE_WIRE,
        '~Wickeldraht~'             => TypeMapper::TYPE_WIRE,
        '~Competition Wire~'        => TypeMapper::TYPE_WIRE,
        '~Watte~'                   => TypeMapper::TYPE_WADDING,
        '~Doctor Coil Watte~'       => TypeMapper::TYPE_WADDING,
        '~Doctor Coil Cotton~'      => TypeMapper::TYPE_WADDING,
        '~Feather Cotton Threads~'  => TypeMapper::TYPE_WADDING,
        '~Coilology.*pro Rolle~'    => TypeMapper::TYPE_WIRE,
        '~Glastank~'                => TypeMapper::TYPE_TANK,
        '~Edelstahldraht~'          => TypeMapper::TYPE_COIL,
        '~Coil~'                    => TypeMapper::TYPE_COIL,
        '~Ladegerät~'               => TypeMapper::TYPE_CHARGER,
        '~DigiCharger~'             => TypeMapper::TYPE_CHARGER,
        '~[Ss]tecker~'              => TypeMapper::TYPE_CHARGER,
        '~Wireless-Charger~'        => TypeMapper::TYPE_CHARGER,
        '~[Kk]abel~'                => TypeMapper::TYPE_CABLE,
        '~NCFilm~'                  => TypeMapper::TYPE_HEATING_PLATE,
        '~Heizplatte~'              => TypeMapper::TYPE_TOOL_HEATING_PLATE,
        '~Drip Cap~'                => TypeMapper::TYPE_DRIP_TIP_CAP,
        '~Mundstück~'               => TypeMapper::TYPE_DRIP_TIP,
        '~Drip Tip~'                => TypeMapper::TYPE_DRIP_TIP,
        '~Ersatztank~'              => TypeMapper::TYPE_TANK,
        '~Tank~'                    => TypeMapper::TYPE_TANK,
        '~Glaserweiterungssatz~'    => TypeMapper::TYPE_TANK,
        '~Glas-Kamin~'              => TypeMapper::TYPE_TANK,
        '~Ultem Tank~'              => TypeMapper::TYPE_TANK,
        '~Top-Kappe~'               => TypeMapper::TYPE_TANK,
        '~Hollowed Out Tank~'       => TypeMapper::TYPE_TANK_PROTECTION,
        '~Tankschutz~'              => TypeMapper::TYPE_TANK_PROTECTION,
        '~Umbausatz~'               => TypeMapper::TYPE_CONVERSION_KIT,
        '~Erweiterungssatz~'        => TypeMapper::TYPE_EXTENSION_KIT,
        '~Reinigungsspray~'         => TypeMapper::TYPE_CLEANING_SUPPLY,
        '~Fensterreiniger~'         => TypeMapper::TYPE_CLEANING_SUPPLY,
        '~[Dd]ichtung~'             => TypeMapper::TYPE_SEAL,
        '~O-Ring~'                  => TypeMapper::TYPE_SEAL,
        '~[Dd]isplay~'              => TypeMapper::TYPE_DISPLAY,
        '~Flavorist - Starterpack~' => TypeMapper::TYPE_DISPLAY,
        '~Abdeckung~'               => TypeMapper::TYPE_COVER,
        '~Vape Band~'               => TypeMapper::TYPE_ACCESSORY,
        '~Magnet.*Adapter~'         => TypeMapper::TYPE_MAGNET_ADAPTOR,
        '~[Mm]agnet~'               => TypeMapper::TYPE_MAGNET,
        '~Batteriehülse~'           => TypeMapper::TYPE_BATTERY_SLEEVE,
        '~Batteriekappe~'           => TypeMapper::TYPE_BATTERY_CAP,
        '~Zeus X2.*Micromesh~'      => TypeMapper::TYPE_COIL,
    ],
];
