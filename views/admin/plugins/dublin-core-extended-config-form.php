<fieldset id="fieldset-dublin-core-extended-form">
    <div class="field">
        <div class="two columns alpha">
            <?php echo $view->formLabel('dublin_core_extended_refines',
                __('Refines Items Search')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $view->formCheckbox('dublin_core_extended_refines', true,
                array('checked' => (boolean) get_option('dublin_core_extended_refines'))); ?>
            <p class="explanation">
                <?php echo __('If selected, an advanced search on a element of the Dublin Core will be enlarged to its refinements, if any.'); ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $view->formLabel('dublin_core_extended_oaipmh_unrefined_dc',
                __('Unrefined DC for OAI-PMH')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $view->formCheckbox('dublin_core_extended_oaipmh_unrefined_dc', true,
                array('checked' => (boolean) get_option('dublin_core_extended_oaipmh_unrefined_dc'))); ?>
            <p class="explanation">
                <?php echo __('If checked, refined elements will be merged into the 15 default elements, so they will be harvestable.'); ?>
                <?php echo __('Detailled qualified Dublin Core elements will be available via the "qdc" metadata format too.'); ?>
                <?php if (!plugin_is_active('OaiPmhRepository')): ?>
            </p>
            <p class="explanation">
                <?php echo __('This option applies only when the plugin %s is enabled.', '<a href="http://omeka.org/add-ons/plugins/oai-pmh-repository">OAI-PMH Repository</a>'); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php if (plugin_is_active('OaiPmhRepository')): ?>
            <div class="inputs five columns omega">
            <label for="dublin_core_exclude_collections"><?php echo 'Do not include file links for items from these collections: '; ?></label>
            <?php echo get_view()->formText('dublin_core_exclude_collections', get_option('dublin_core_exclude_collections'));?>
            <p class="explanation"><?php echo __('Comma-separated list of collection ids'); ?></p>
            </div> 
        <?php endif; ?>
    </div>
</fieldset>
