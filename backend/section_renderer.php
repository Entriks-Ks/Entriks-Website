<?php
// Dynamic section renderer - renders sections based on database structure
function renderDynamicSection($sectionType, $sectionId, $isEditorMode = false) {
    $isGerman = true; // Adjust based on page
    
    // Section templates
    $templates = [
        'banner' => function($id, $edit) use ($isGerman) {
            ob_start();
            ?>
            <div id="<?= $id ?>" class="page-section">
                <div class="banner-style-one" style="<?= renderStyle('banner_shape_bg', 'background-image: url(' . renderBlock('banner_shape_bg', 'assets/img/shape/1.png') . ');') ?>" <?php if ($edit) echo 'data-editable-bg="true" data-key="banner_shape_bg" data-group="Background"'; ?>>
                    <div class="editable-section-wrapper" style="<?= renderStyle('section_banner') ?>" <?php if ($edit) echo 'data-editable="section" data-key="section_banner" data-group="Banner > Content"'; ?>>
                        <div class="container">
                            <div class="row align-center">
                                <div class="col-lg-8 banner-one-item">
                                    <h4 style="<?= renderStyle('banner_subtitle') ?>" <?php if ($edit) echo 'data-editable="true" data-key="banner_subtitle"'; ?>>
                                        <?= renderBlock('banner_subtitle', 'Ihr Partner für Nearshoring & BPO') ?>
                                    </h4>
                                    <h2 style="<?= renderStyle('banner_title') ?>" <?php if ($edit) echo 'data-editable="true" data-key="banner_title"'; ?>>
                                        <?= renderBlock('banner_title', 'SCHNELLER <strong>WACHSEN.</strong><br>OPTIMIERTER <strong>ARBEITEN.</strong>') ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        },
        
        'brand' => function($id, $edit) {
            ob_start();
            ?>
            <div id="<?= $id ?>" class="page-section">
                <div class="brand-area relative overflow-hidden text-light">
                    <div class="editable-section-wrapper" style="<?= renderStyle('section_brand') ?>" <?php if ($edit) echo 'data-editable="section" data-key="section_brand"'; ?>>
                        <div class="brand-style-one">
                            <div class="container-fill">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="brand-items custom-gradient-banner"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        },
        
        // Add more section templates as needed
    ];
    
    if (isset($templates[$sectionType])) {
        return $templates[$sectionType]($sectionId, $isEditorMode);
    }
    
    return '';
}
