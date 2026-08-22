<?php
if (! isset($data)) {
    exit;
}

use \WpAssetCleanUp\MiscArray;

$settingKey    = $data['current_setting_key']; // e.g. resource_loading
$settingSubKey = 'lazy_load';

$resourceLoadingImageLazyLoad = $data[$settingKey.'_image_'.$settingSubKey.'_rules'];

$isLazyLoadEnabled     = MiscArray::getValue($resourceLoadingImageLazyLoad, '_enabled', 0);

$skipViaCssClasses     = trim(MiscArray::getValue($resourceLoadingImageLazyLoad, 'skip_via_css_classes', ''));
$skipViaSourceKeywords = trim(MiscArray::getValue($resourceLoadingImageLazyLoad, 'skip_via_source_keywords', ''));

?>
<style>
    .wpacu-rules-wrap,
    .wpacu-rules-wrap * {
        box-sizing: border-box;
    }

    .wpacu-rules-wrap code {
        white-space: normal;
        word-break: break-word;
    }

    .wpacu-rules-wrap label {
        line-height: 1.5;
    }

    .wpacu-rules-wrap textarea.large-text,
    .wpacu-rules-wrap input[type="text"],
    .wpacu-rules-wrap input[type="number"] {
        max-width: 100%;
    }

    .wpacu-collapsible-title span:last-child {
        min-width: 0;
    }

    .wpacu-rules-wrap td > p.description {
        margin-top: 4px !important;
    }

    .wpacu-collapsible-options-wrap {
        margin-top: 4px;
    }

    .wpacu-collapsible-option {
        margin-bottom: 12px;
        border: 1px solid #dcdcde;
        border-radius: 4px;
        background: #fff;
        overflow: hidden;
    }

    .wpacu-collapsible-option.wpacu-contracted .wpacu-collapsible-area {
        height: 0;
        border: 0;

        max-height: 0;
        opacity: 0;
        visibility: hidden;

        padding-top: 0;
        padding-bottom: 0;
    }

    .wpacu-collapsible-option.wpacu-expanded .wpacu-collapsible-area {
        max-height: 10000px;
        opacity: 1;
        visibility: visible;
    }

    .wpacu-collapsible-option.wpacu-expanded .wpacu-collapsible-caret {
        transform: rotate(90deg);
    }

    .wpacu-collapsible-options-wrap .wpacu-collapsible-option:last-child {
        margin-bottom: 0;
    }

    .wpacu-collapsible-title {
        min-height: 38px;

        width: 100%;
        display: flex;
        align-items: center;
        gap: 3px;

        padding: 10px 14px;

        border: 0;
        background: #f6f7f7;

        cursor: pointer;

        font-size: 13px;
        font-weight: 600;

        text-align: left;

        transition: background-color 120ms ease, color 120ms ease;
    }

    .wpacu-collapsible-title:hover {
        background: #f0f0f1;
        color: #004567;
    }

    .wpacu-collapsible-title:focus {
        outline: none;
        box-shadow: none;
    }

    .wpacu-collapsible-caret {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        width: 16px;
        height: 16px;

        font-size: 14px;
        line-height: 1;

        font-weight: 700;

        flex-shrink: 0;

        transition: transform 120ms ease;
    }

    .wpacu-collapsible-caret:hover {
        color: #004567;
    }

    .wpacu-collapsible-area {
        padding: 12px 14px 14px;
        border-top: 1px solid #dcdcde;
        background: #fff;

        overflow: hidden;
        transition:
                max-height 180ms ease,
                opacity 160ms ease,
                padding-top 180ms ease,
                padding-bottom 180ms ease,
                border-top-color 180ms ease;
    }

    .wpacu-collapsible-area textarea {
        margin-top: 0;
    }

    .wpacu-collapsible-area .description {
        margin-top: 8px;
    }

    @media screen and (max-width: 782px) {
        .wpacu-rules-wrap .wpacu-form-table,
        .wpacu-rules-wrap .wpacu-form-table tbody,
        .wpacu-rules-wrap .wpacu-form-table tr,
        .wpacu-rules-wrap .wpacu-form-table th,
        .wpacu-rules-wrap .wpacu-form-table td {
            display: block;
            width: 100%;
        }

        .wpacu-rules-wrap .wpacu-form-table th {
            padding: 16px 0 6px;
            font-weight: 600;
        }

        .wpacu-rules-wrap .wpacu-form-table td {
            padding: 0 0 16px;
        }

        .wpacu-rules-wrap .wpacu-form-table tr {
            border-bottom: 1px solid #dcdcde;
            margin-bottom: 14px;
        }

        .wpacu-rules-wrap .wpacu-form-table tr:last-child {
            border-bottom: 0;
            margin-bottom: 0;
        }

        .wpacu-collapsible-title {
            padding: 11px 12px;
            font-size: 13px;
        }

        .wpacu-collapsible-area {
            padding: 12px;
        }

        .wpacu-collapsible-area textarea {
            width: 100%;
            min-height: 120px;
        }

        .wpacu-rules-wrap label {
            display: inline-block;
            max-width: 100%;
        }

        .wpacu-rules-wrap input.small-text[type="number"] {
            width: 70px;
            max-width: 100%;
        }
    }

    @media screen and (max-width: 480px) {
        .wpacu-collapsible-title {
            gap: 2px;
            padding: 10px;
        }

        .wpacu-collapsible-area {
            padding: 10px;
        }

        .wpacu-collapsible-option {
            margin-bottom: 10px;
        }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.wpacu-toggle-collapsible').forEach(function (button) {
            var option = button.closest('.wpacu-collapsible-option');

            if ( ! option ) {
                return;
            }

            button.addEventListener('click', function () {
                var isExpanded = option.classList.contains('wpacu-expanded');

                option.classList.toggle('wpacu-expanded', !isExpanded);
                option.classList.toggle('wpacu-contracted', isExpanded);
            });
        });
    });
</script>
<div id="wpacu-image-lazy-load-rules" class="wpacu-rules-wrap <?php if ( ! $isLazyLoadEnabled ) { ?>wpacu-disabled-area<?php } ?>">
    <div class="wpacu-resource-loading-tool-header">
        <div><span class="wpacu-resource-loading-tool-kicker"><?php esc_html_e('Automated delivery', 'wp-asset-clean-up'); ?></span><h3><?php esc_html_e('Lazy load eligible images automatically', 'wp-asset-clean-up'); ?></h3><p><?php esc_html_e('Delay below-the-fold image requests while keeping critical images excluded and immediately available.', 'wp-asset-clean-up'); ?></p></div>
        <a target="_blank" rel="noopener noreferrer" href="https://www.assetcleanup.com/docs/?p=2403"><span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e('Read documentation', 'wp-asset-clean-up'); ?></a>
    </div>

    <div class="wpacu-resource-loading-explanation">
        <?php
        ?>
        For <code>&lt;img&gt;</code> tags, <code>loading="lazy"</code> is added automatically to the eligible tags.
    </div>

    <div id="wpacu-resource-loading-images-lazy-load-rules-area">
        <table class="wpacu-form-table">
            <tr>
                <th scope="row">
                    Image Decoding
                </th>
                <td>
                    <?php
                    $inputKey = 'decoding_async';
                    $inputName = WPACU_PLUGIN_ID . '_settings['.$settingKey.'][images]['.$settingSubKey.']['.$inputKey.']';
                    ?>
                    <label style="cursor: pointer;">
                        <input type="checkbox"
                               name="<?php echo $inputName; ?>"
                               <?php checked(1, MiscArray::getValue($resourceLoadingImageLazyLoad, $inputKey, '')); ?>
                               value="1" />
                        Apply <code>decoding="async"</code> to lazy-loaded images when <strong>no</strong> <code>decoding</code> attribute is already present
                    </label>

                    <p class="description">
                        Helps the browser decode lazy-loaded images without blocking rendering.
                    </p>
                </td>
            </tr>

            <?php
            ?>

            <tr>
                <th scope="row">
                    Lazy Load Exclusions
                </th>
                <td>
                    <p class="description" style="margin: 0 0 10px;">
                        Exclude specific images or sections from automatic lazy loading based on the options below:
                    </p>

                    <div style="border: 1px solid #dcdcde; background: #f6f7f7; padding: 10px 14px; border-radius: 4px; margin: 6px 0 12px;">
                    <label style="cursor: pointer;">Skip lazy loading for the first
                        <input type="number"
                               min="0"
                               max="20"
                               step="1"
                               name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][exclude_first]"
                               value="<?php echo MiscArray::getValue($resourceLoadingImageLazyLoad, 'exclude_first', 0); ?>"
                               class="small-text" /> eligible image(s) found in the HTML source.
                        Use <code>0</code> to apply lazy loading to all eligible images.</label>
                    </div>

                    <div class="wpacu-collapsible-options-wrap">
                        <div class="wpacu-collapsible-option <?php echo $skipViaSourceKeywords ? 'wpacu-expanded' : 'wpacu-contracted'; ?>">
                            <button type="button"
                                    class="wpacu-collapsible-title wpacu-toggle-collapsible"
                                    data-wpacu-toggle-target="wpacu-lazy-load-exclude-skip-via-url-keywords">
                                <span class="wpacu-collapsible-caret">❯</span>
                                <span>URL Keywords</span>
                            </button>

                            <div id="wpacu-lazy-load-exclude-skip-via-url-keywords"
                                 class="wpacu-collapsible-area">

                                <textarea name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][skip_via_source_keywords]"
                                  data-wpacu-adapt-height="1"
                                  rows="5"
                                  class="large-text code"
                                  placeholder="Example:&#10;/logo&#10;/hero&#10;banner-"><?php echo esc_textarea($skipViaSourceKeywords); ?></textarea>

                                <p class="description">
                                    <?php
                                    ?>
                                    Exclude images (e.g with attributes <code>src</code>, <code>srcset</code>, or <code>data-src</code>).
                                    Add one keyword per line. Regular expressions are supported when wrapped in
                                    <code>#</code>, <code>~</code>, <code>!</code>, or <code>%</code> delimiters.
                                    Example: <code>#/uploads/.+\.(jpg|webp)$#i</code>
                                </p>
                            </div>
                        </div>

                        <div class="wpacu-collapsible-option <?php echo $skipViaCssClasses ? 'wpacu-expanded' : 'wpacu-contracted'; ?>">
                            <button type="button"
                                    class="wpacu-collapsible-title wpacu-toggle-collapsible"
                                    data-wpacu-toggle-target="wpacu-lazy-load-exclude-skip-via-classes">
                                <span class="wpacu-collapsible-caret">❯</span>
                                <span>CSS Classes</span>
                            </button>

                            <div id="wpacu-lazy-load-exclude-skip-via-classes"
                                 class="wpacu-collapsible-area">

                                <textarea name="<?php echo WPACU_PLUGIN_ID . '_settings'; ?>[<?php echo $settingKey; ?>][images][<?php echo $settingSubKey; ?>][skip_via_css_classes]"
                                  data-wpacu-adapt-height="1"
                                  rows="5"
                                  class="large-text code"
                                  placeholder="Example:&#10;custom-logo&#10;hero-image&#10;skip-lazy"><?php echo esc_textarea($skipViaCssClasses); ?></textarea>

                                <p class="description">
                                    Exclude images matching specific CSS classes. Add one class per line, without the dot
                                    (e.g. <code>custom-logo</code>), or use a CSS selector (the option below) for more specific matches
                                    (e.g. <code>.particular-area.custom-class</code>).
                                </p>
                            </div>
                        </div>

                        <!--
                        <?php
                        ?>
                        -->
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
