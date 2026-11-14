<?php
// The examples describe the enqueued-asset views in templates/meta-box-loaded-assets/.
if (! isset($data)) {
    exit;
}

$groupingPreviews = array(
    'by-location' => array(
        'label' => __('Location', 'wp-asset-clean-up'),
        'purpose' => __('Find which plugin or theme supplies an asset, then review related files together.', 'wp-asset-clean-up'),
        'detail' => __('Plugins come first, followed by themes, uploads, WordPress core and external assets. Plugin and theme files have their own subsections. The uploads section appears only when it contains assets; core visibility follows your settings.', 'wp-asset-clean-up'),
        'groups' => array(
            array(__('Plugins', 'wp-asset-clean-up'), 'contact-form.css · contact-form.js'),
            array(__('Themes', 'wp-asset-clean-up'), 'theme-style.css · theme-navigation.js'),
            array(__('Uploads', 'wp-asset-clean-up'), 'generated-styles.css'),
            array(__('WordPress core', 'wp-asset-clean-up'), 'wp-block-library.css'),
            array(__('External', 'wp-asset-clean-up'), 'https://cdn.example.com/widget.js')
        )
    ),
    'by-position' => array(
        'label' => __('Tag position', 'wp-asset-clean-up'),
        'purpose' => __('Review what is placed in the document head and what is placed in the body.', 'wp-asset-clean-up'),
        'detail' => __('The HEAD group comes before BODY. A changed position is used when one is configured. This view helps you inspect placement without changing where an asset loads.', 'wp-asset-clean-up'),
        'groups' => array(array('<head>', 'site-style.css · head-script.js'), array('<body>', 'gallery.js · navigation.js'))
    ),
    'by-preload' => array(
        'label' => __('Preload status', 'wp-asset-clean-up'),
        'purpose' => __('Check assets marked for preload together before reviewing files with standard loading.', 'wp-asset-clean-up'),
        'detail' => __('Preloaded assets come first, then assets without a preload setting. A preload setting only has an effect when the asset is actually loaded on the page.', 'wp-asset-clean-up'),
        'groups' => array(array(__('Preloaded', 'wp-asset-clean-up'), 'hero-style.css · important-script.js'), array(__('Not preloaded', 'wp-asset-clean-up'), 'footer.css · gallery.js'))
    ),
    'by-parents' => array(
        'label' => __('Dependencies', 'wp-asset-clean-up'),
        'purpose' => __('See dependency relationships before unloading a file that other assets need.', 'wp-asset-clean-up'),
        'detail' => __('Parents are listed first, then children, then independent assets. A parent is required by other assets; a child depends on another asset. An asset that is both a parent and a child appears with the parents.', 'wp-asset-clean-up'),
        'groups' => array(array(__('Parents — needed by other assets', 'wp-asset-clean-up'), 'jquery-core → gallery'), array(__('Children — depend on other assets', 'wp-asset-clean-up'), 'gallery → requires jquery-core'), array(__('Independent — no dependency links', 'wp-asset-clean-up'), 'standalone-style.css'))
    ),
    'by-loaded-unloaded' => array(
        'label' => __('Loaded / unloaded', 'wp-asset-clean-up'),
        'purpose' => __('Scan which assets remain loaded and which are unloaded on the page, especially when checking front-end changes.', 'wp-asset-clean-up'),
        'detail' => __('Loaded assets come first, then unloaded assets. Load exceptions are taken into account: an asset unloaded site-wide but allowed on this page belongs in Loaded. Save changes and refresh the asset list to review the updated groups.', 'wp-asset-clean-up'),
        'groups' => array(array(__('Loaded on this page', 'wp-asset-clean-up'), 'navigation.js · site-style.css'), array(__('Unloaded on this page', 'wp-asset-clean-up'), 'contact-form.js · unused-slider.css'))
    ),
    'by-size' => array(
        'label' => __('File size', 'wp-asset-clean-up'),
        'purpose' => __('Start your review with the largest local files to find potential optimization opportunities faster.', 'wp-asset-clean-up'),
        'detail' => __('Files with a detected size are ordered from largest to smallest, mixing CSS and JavaScript. External files and files whose size cannot be detected follow in a separate group. These are detected file sizes, not a measurement of compressed network transfer.', 'wp-asset-clean-up'),
        'groups' => array(array(__('Known size — largest first', 'wp-asset-clean-up'), 'slider.js — 240 KB|theme.css — 85 KB|navigation.js — 12 KB'), array(__('External / size unavailable', 'wp-asset-clean-up'), 'https://cdn.example.com/widget.js'))
    ),
    'by-rules' => array(
        'label' => __('Rules / no rules', 'wp-asset-clean-up'),
        'purpose' => __('Review assets you have configured first, then scan those without rules.', 'wp-asset-clean-up'),
        'detail' => __('Assets with at least one rule come first, followed by assets with no rules. This includes load exceptions as well as unload, preload, async/defer and position changes. Having a rule does not necessarily mean the asset is unloaded on this page.', 'wp-asset-clean-up'),
        'groups' => array(array(__('At least one rule', 'wp-asset-clean-up'), __('contact-form.js — unload rule|site-style.css — preload|gallery.js — load exception', 'wp-asset-clean-up')), array(__('No rules', 'wp-asset-clean-up'), 'navigation.js · untouched-style.css'))
    ),
    'two-lists' => array(
        'label' => __('CSS, then JavaScript', 'wp-asset-clean-up'),
        'purpose' => __('Focus on stylesheets first, then review scripts in a separate list.', 'wp-asset-clean-up'),
        'detail' => __('All enqueued CSS is grouped together before all enqueued JavaScript. Each list is sorted by handle, making it easier to locate an asset when you know its name and type.', 'wp-asset-clean-up'),
        'groups' => array(array(__('CSS stylesheets', 'wp-asset-clean-up'), 'base-style · theme-style'), array(__('JavaScript', 'wp-asset-clean-up'), 'analytics · navigation'))
    ),
    'all' => array(
        'label' => __('One list', 'wp-asset-clean-up'),
        'purpose' => __('Look through one continuous list without switching between groups.', 'wp-asset-clean-up'),
        'detail' => __('Enqueued CSS and JavaScript are mixed in one list, sorted by handle. Use this when you prefer a simple overview or want to find a known handle regardless of asset type.', 'wp-asset-clean-up'),
        'groups' => array(array(__('All enqueued assets — by handle', 'wp-asset-clean-up'), 'analytics (JS)|base-style (CSS)|navigation (JS)|theme-style (CSS)'))
    )
);
?>
<dialog id="wpacu-grouping-preview" aria-labelledby="wpacu-grouping-preview-title" aria-describedby="wpacu-grouping-preview-intro">
    <header class="wpacu-grouping-preview__header">
        <div><span class="wpacu-grouping-preview__eyebrow"><?php esc_html_e('CSS/JS list appearance', 'wp-asset-clean-up'); ?></span><h2 id="wpacu-grouping-preview-title"><?php esc_html_e('Explore asset grouping', 'wp-asset-clean-up'); ?></h2></div>
        <button type="button" class="wpacu-grouping-preview__close" aria-label="<?php esc_attr_e('Close preview', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
    </header>
    <p id="wpacu-grouping-preview-intro"><?php esc_html_e('See how each layout organizes the manager. Browsing these examples does not change your selection or saved rules.', 'wp-asset-clean-up'); ?></p>
    <div class="wpacu-grouping-preview__body">
        <nav aria-label="<?php esc_attr_e('Grouping previews', 'wp-asset-clean-up'); ?>">
            <?php foreach ($groupingPreviews as $groupingKey => $groupingPreview) { ?>
                <button type="button" data-grouping-nav="<?php echo esc_attr($groupingKey); ?>"><?php echo esc_html($groupingPreview['label']); ?></button>
            <?php } ?>
        </nav>
        <div class="wpacu-grouping-preview__content">
            <?php foreach ($groupingPreviews as $groupingKey => $groupingPreview) { ?>
                <section data-grouping-panel="<?php echo esc_attr($groupingKey); ?>" hidden>
                    <h3 tabindex="-1"><?php echo esc_html($groupingPreview['label']); ?></h3>
                    <p class="wpacu-grouping-preview__purpose"><?php echo esc_html($groupingPreview['purpose']); ?></p>
                    <figure>
                        <figcaption><?php esc_html_e('Illustrative order · example assets', 'wp-asset-clean-up'); ?></figcaption>
                        <ol>
                            <?php foreach ($groupingPreview['groups'] as $groupingGroup) { ?>
                                <li><strong><?php echo esc_html($groupingGroup[0]); ?></strong><div><?php foreach (explode('|', $groupingGroup[1]) as $groupingExample) { ?><code><?php echo esc_html($groupingExample); ?></code><?php } ?></div></li>
                            <?php } ?>
                        </ol>
                    </figure>
                    <p class="wpacu-grouping-preview__detail"><?php echo esc_html($groupingPreview['detail']); ?></p>
                </section>
            <?php } ?>
        </div>
    </div>
    <footer class="wpacu-grouping-preview__footer">
        <button type="button" data-grouping-step="-1"><?php esc_html_e('← Previous', 'wp-asset-clean-up'); ?></button>
        <span role="status" aria-live="polite" class="wpacu-grouping-preview__position"></span>
        <button type="button" data-grouping-step="1"><?php esc_html_e('Next →', 'wp-asset-clean-up'); ?></button>
    </footer>
</dialog>
<script>
(function () {
    'use strict';
    var dialog = document.getElementById('wpacu-grouping-preview');
    var select = document.getElementById('wpacu_assets_list_layout');
    var trigger = document.querySelector('.wpacu-grouping-preview-open');
    if (!dialog || !select || !trigger || typeof dialog.showModal !== 'function') {
        if (trigger) { trigger.hidden = true; }
        return;
    }
    // Keep the native modal outside the settings form and any hidden tab containers.
    document.body.appendChild(dialog);
    var nav = Array.prototype.slice.call(dialog.querySelectorAll('[data-grouping-nav]'));
    var panels = Array.prototype.slice.call(dialog.querySelectorAll('[data-grouping-panel]'));
    var previous = dialog.querySelector('[data-grouping-step="-1"]');
    var next = dialog.querySelector('[data-grouping-step="1"]');
    var current = 0;
    var bodyOverflow;

    function show(key, moveFocus) {
        current = nav.map(function (button) { return button.getAttribute('data-grouping-nav'); }).indexOf(key);
        if (current < 0) { current = 0; }
        nav.forEach(function (button, index) {
            if (index === current) { button.setAttribute('aria-current', 'true'); }
            else { button.removeAttribute('aria-current'); }
        });
        panels.forEach(function (panel, index) { panel.hidden = index !== current; });
        previous.disabled = current === 0;
        next.disabled = current === nav.length - 1;
        dialog.querySelector('.wpacu-grouping-preview__position').textContent = (current + 1) + ' / ' + nav.length + ' · ' + nav[current].textContent;
        if (moveFocus) { panels[current].querySelector('h3').focus(); }
    }

    trigger.addEventListener('click', function () {
        show(select.value === 'default' ? 'two-lists' : select.value, false);
        bodyOverflow = document.body.style.overflow;
        dialog.showModal();
        document.body.style.overflow = 'hidden';
        dialog.querySelector('.wpacu-grouping-preview__close').focus();
    });
    nav.forEach(function (button) {
        button.addEventListener('click', function () { show(button.getAttribute('data-grouping-nav'), true); });
    });
    [previous, next].forEach(function (button) {
        button.addEventListener('click', function () {
            var target = current + Number(button.getAttribute('data-grouping-step'));
            if (nav[target]) { show(nav[target].getAttribute('data-grouping-nav'), true); }
        });
    });
    dialog.querySelector('.wpacu-grouping-preview__close').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab') { return; }
        var buttons = dialog.querySelectorAll('button:not([disabled])');
        var first = buttons[0];
        var last = buttons[buttons.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    dialog.addEventListener('click', function (event) {
        var rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) { dialog.close(); }
    });
    dialog.addEventListener('close', function () {
        document.body.style.overflow = bodyOverflow;
        trigger.focus();
    });
}());
</script>
