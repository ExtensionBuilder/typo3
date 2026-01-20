<?php

return [
    'ctrl' => [
        'title' => 'theme_camino.backend_fields:tx_themecamino_list_item',
        'label' => 'header',
        'label_alt' => 'text,link_label',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'hideTable' => true,
        'sortby' => 'sorting_foreign',
        'delete' => 'deleted',
        'versioningWS' => true,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource' => 'l10n_source',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'typeicon_classes' => ['default' => 'theme-camino-record-listitem'],
        'security' => [
            'ignoreWebMountRestriction' => true,
            'ignoreRootLevelRestriction' => true,
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
        // Field for foreign_match_fields
        'fieldname' => [
            'config' => [
                // Could be type passthrough, but then database field would not be created automatically.
                'type' => 'input',
            ],
        ],

        // Custom fields
        'category' => [
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.category',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 50,
            ],
        ],
        'date' => [
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.date',
            'config' => [
                'type' => 'datetime',
                'format' => 'date',
                'nullable' => true,
            ],
        ],
        'header' => [
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.header',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
            ],
        ],
        'images' => [
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.images',
            'config' => [
                'type' => 'file',
                'allowed' => ['common-image-types'],
                'appearance' => [
                    'createNewRelationLinkTitle' => 'theme_camino.backend_fields:tx_themecamino_list_item.images.addFileReference',
                    'showPossibleLocalizationRecords' => true,
                ],
            ],
        ],
        'link' => [
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.link',
            'config' => [
                'type' => 'link',
                'size' => 50,
                'appearance' => [
                    'browserTitle' => 'theme_camino.backend_fields:tx_themecamino_list_item.link',
                ],
            ],
        ],
        'link_config' => [
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.link_config',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'theme_camino.backend_fields:tt_content.tx_themecamino_link_config.I.0',
                        'value' => '0',
                    ],
                    [
                        'label' => 'theme_camino.backend_fields:tt_content.tx_themecamino_link_config.I.secondary',
                        'value' => 'secondary',
                    ],
                    [
                        'label' => 'theme_camino.backend_fields:tt_content.tx_themecamino_link_config.I.soft',
                        'value' => 'soft',
                    ],
                    [
                        'label' => 'theme_camino.backend_fields:tt_content.tx_themecamino_link_config.I.text',
                        'value' => 'text',
                    ],
                    [
                        'label' => 'theme_camino.backend_fields:tt_content.tx_themecamino_link_config.I.inverted',
                        'value' => 'inverted',
                        'group' => 'inverted',
                    ],
                    [
                        'label' => 'theme_camino.backend_fields:tt_content.tx_themecamino_link_config.I.inverted-secondary',
                        'value' => 'inverted-secondary',
                        'group' => 'inverted',
                    ],
                    [
                        'label' => 'theme_camino.backend_fields:tt_content.tx_themecamino_link_config.I.inverted-soft',
                        'value' => 'inverted-soft',
                        'group' => 'inverted',
                    ],
                    [
                        'label' => 'theme_camino.backend_fields:tt_content.tx_themecamino_link_config.I.inverted-text',
                        'value' => 'inverted-text',
                        'group' => 'inverted',
                    ],
                ],

            ],
        ],
        'link_icon' => [
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.link_icon',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [],
            ],
        ],
        'link_label' => [
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.link_label',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
            ],
        ],
        'text' => [
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.text',
            'config' => [
                'type' => 'text',
                'cols' => 50,
                'rows' => 10,
                'softref' => 'typolink_tag,email[subst],url',
            ],
        ],
    ],
    'palettes' => [
        'linklabel' => [
            'showitem' => 'link, link_label',
        ],
        'linklabelconfig' => [
            'showitem' => 'link, link_label, --linebreak--, link_config',
        ],
        'linklabelicon' => [
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.palettes.linklabelicon',
            'showitem' => 'link, link_label, --linebreak--, link_icon',
        ],
        'linklabeliconconfig' => [
            'label' => 'theme_camino.backend_fields:tx_themecamino_list_item.palettes.linklabeliconconfig',
            'showitem' => 'link, link_label, --linebreak--, link_icon, link_config',
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => '',
        ],
    ],
];
