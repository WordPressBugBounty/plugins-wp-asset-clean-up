<?php
/*
 * No direct access to this file
 */

use WpAssetCleanUp\Admin\Overview;
use WpAssetCleanUp\Admin\PluginsManagerAdmin;

if ( ! isset($data) ) {
	exit;
}
?>
<hr style="margin: 15px 0;"/>

<div id="wpacu-plugins-load-manager-wrap">
	<?php
	foreach ($data['plugins_with_rules'] as $locationKey => $pluginsWithRules) {
		if ( ! empty($pluginsWithRules) ) {
			?>
			<h3 id="<?php echo $locationKey === 'plugins' ? 'wpacu-overview-section-plugins-front' : 'wpacu-overview-section-plugins-admin'; ?>" class="wpacu-overview-section-title"><span class="dashicons dashicons-admin-plugins"></span> <?php _e('Plugins Manager rules', 'wp-asset-clean-up'); ?>
				<?php
				if ($locationKey === 'plugins') {
					$pageTypeText = 'frontend';
					echo ' (in frontend view)';
				} else {
                    // $locationKey === 'plugins_dash'
					$pageTypeText = 'admin';
					echo ' (within the dashboard, where the user is always logged-in)';
				}

				if (isset($data['plugins_with_rules'][$locationKey]) && count($data['plugins_with_rules'][$locationKey]) > 0) {
					echo ' &#10230; Total: '.count($data['plugins_with_rules'][$locationKey]);
				}
				?>
				<a class="wpacu-overview-back-to-navigation" href="#wpacu-overview-start" aria-label="<?php esc_attr_e('Back to Overview navigation', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></a>
			</h3>

			<table class="wp-list-table wpacu-list-table widefat plugins striped" style="width: 100%;">
				<?php
				foreach ($pluginsWithRules as $pluginValues) {
					$pluginTitle       = $pluginValues['title'];
					$pluginPath        = $pluginValues['path'];
					$pluginRules       = $pluginValues['rules'];
					$isPluginInstalled = ! isset($pluginValues['is_installed']) || $pluginValues['is_installed'];

					if (! is_array($pluginRules['status'])) {
						$pluginRules['status'] = array($pluginRules['status']); // from v1.1.8.3
					}

                    $inactiveRuleKeys = ! empty($pluginRules['_wpacu_overview_inactive_rule_keys'])
                        && is_array($pluginRules['_wpacu_overview_inactive_rule_keys'])
                        ? $pluginRules['_wpacu_overview_inactive_rule_keys']
                        : array();

					list($pluginDir) = explode('/', $pluginPath);

					$isPluginActive = $isPluginInstalled && in_array($pluginPath, $data['plugins_active'], true);
					?>
					<tr>
						<td data-wpacu-item-data="1" class="wpacu_plugin_details">
							<div class="wpacu_plugin_icon" style="float: left;">
								<?php if (isset($data['plugins_icons'][$pluginDir])) { ?>
									<img width="40" height="40" alt="" src="<?php echo esc_attr($data['plugins_icons'][$pluginDir]); ?>" />
								<?php } else { ?>
									<div><span class="dashicons dashicons-admin-plugins"></span></div>
								<?php } ?>
							</div>

							<div style="float: left; margin-left: 8px; width: 80%;">
								<div>
									<span class="wpacu_plugin_title"><?php echo esc_html($pluginTitle); ?></span>
									<?php if ( ! $isPluginInstalled ) { ?>
										<span style="display: inline-block; margin-left: 5px; padding: 1px 5px; border: 1px solid #b32d2e; border-radius: 3px; color: #b32d2e; font-size: 10px; font-weight: 600; line-height: 1.5; text-transform: uppercase;"><?php esc_html_e('Not installed', 'wp-asset-clean-up'); ?></span>
									<?php } ?>
									<?php
									if (in_array($pluginPath, $data['plugins_active_network'], true)) {
										echo '&nbsp;<span title="Network Activated" class="dashicons dashicons-admin-multisite wpacu-tooltip"></span>';
									}
									?>
								</div>
								<div><span class="wpacu_plugin_path"><small><?php echo esc_html($pluginPath); ?></small></span></div>

								<?php
								if ( ! $isPluginActive ) {
                                    $ruleLocation     = ($locationKey === 'plugins') ? 'front' : 'dash';

                                    $clearForText = ($ruleLocation === 'front') ? 'front-end view (guests)' : 'dashboard view (logged-in)';

                                    if ($isPluginInstalled) {
                                        $clearForFullTextEditMode = sprintf(
                                            __('Clear all the %s view rules for this inactive plugin', 'wp-asset-clean-up'),
                                            esc_html($clearForText)
                                        );

                                        $clearForFullTextViewMode = sprintf(
                                            __('You can switch to %s"Edit Mode"%s to clear all the %s view rules for this inactive plugin', 'wp-asset-clean-up'),
                                            '<a href="'.admin_url('admin.php?page=wpassetcleanup_overview&wpacu_edit_mode=1').'">',
                                            '</a>',
                                            esc_html($clearForText)
                                        );

                                        $pluginStateNote = __('The plugin is inactive, thus any of its rules are also inactive & irrelevant.', 'wp-asset-clean-up');
                                    } else {
                                        $clearForFullTextEditMode = sprintf(
                                            __('Clear all the %s view rules for this plugin that is not installed', 'wp-asset-clean-up'),
                                            esc_html($clearForText)
                                        );

                                        $clearForFullTextViewMode = sprintf(
                                            __('You can switch to %s"Edit Mode"%s to clear all the %s view rules for this plugin that is not installed', 'wp-asset-clean-up'),
                                            '<a href="'.admin_url('admin.php?page=wpassetcleanup_overview&wpacu_edit_mode=1').'">',
                                            '</a>',
                                            esc_html($clearForText)
                                        );

                                        $pluginStateNote = __('The plugin is not installed on this website. Its saved rules are preserved, but they cannot take effect unless the plugin is installed and activated using the same path.', 'wp-asset-clean-up');
                                    }
                                    ?>
                                    <div class="wpacu-clear-rule-area">
                                        <small>
                                            <strong><?php esc_html_e('Note:', 'wp-asset-clean-up'); ?></strong>
                                            <span style="color: darkred;"><?php echo esc_html($pluginStateNote); ?></span>
                                        </small>
                                        <div style="margin-top: 6px;">
                                            <?php
                                            $infoData = array(
                                                'plugin'   => $pluginPath,
                                                'location' => $ruleLocation,
                                            );

                                            if (Overview::isEditMode()) {
                                                echo Overview::renderRuleOutput(
                                                        $clearForFullTextEditMode,
                                                        $infoData,
                                                        'inactive_rules',
                                                        1
                                                );
                                            } else {
                                                echo '<small><em style="color: #6d6d6d;">'.$clearForFullTextViewMode.'</em></small>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php
                                }
								?>
							</div>

							<div class="wpacu_clearfix"></div>
						</td>
						<td data-wpacu-item-unload-load-rules="true" class="wpacu_plugin_rules" style="padding-left: 10px;">
							<?php
							$rulesList = array();

							$taxListValueToText = array(
								'category_all'          => __('"Category"', 'wp-asset-clean-up'),
								'post_tag_all'          => __('"Tag"', 'wp-asset-clean-up'),
								'product_cat_all'       => __('"WooCommerce Product Category"', 'wp-asset-clean-up'),
								'product_tag_all'       => __('"WooCommerce Product Tag"', 'wp-asset-clean-up'),
								'download_category_all' => __('"Easy Digital Downloads Download Category"', 'wp-asset-clean-up'),
								'download_tag_all'      => __('"Easy Digital Downloads Download Tag"', 'wp-asset-clean-up')
							);

                            $archiveListValueToText = PluginsManagerAdmin::generateArchivePageTypesList();

							global $wp_roles;
							$allUsersRoles = $wp_roles->roles;

                            $infoData = array(
                                'plugin'   => $pluginPath,
                                'location' => ($pageTypeText === 'frontend' ? 'front' : 'dash'),
                            );

                            $allPluginUnloadRuleKeys = array(
                               'unload_home_page',
                               'unload_site_wide',
                               'unload_via_post',
                               'unload_via_post_type',
                               'unload_via_post_tax_term',
                               'unload_via_tax_term',
                               'unload_via_tax',
                               'unload_via_archive',
                               'unload_via_author_archive',
                               'unload_via_regex',
                               'unload_logged_in',
                               'unload_logged_in_via_role'
                           );

                            $ruleStatus = array_fill_keys($pluginRules['status'], true);

                            $unloadHomePage          = isset($ruleStatus['unload_home_page']);
                            $unloadSiteWide          = isset($ruleStatus['unload_site_wide']);

                            $unloadedOnSelectedPosts  = isset($ruleStatus['unload_via_post']) && ! empty($pluginRules['unload_via_post']['values']);
                            $unloadedViaPostType     = isset($ruleStatus['unload_via_post_type']) && ! empty($pluginRules['unload_via_post_type']['values']);
                            $unloadedViaPostTaxTerm  = isset($ruleStatus['unload_via_post_tax_term']) && ! empty($pluginRules['unload_via_post_tax_term']['values']);
                            $unloadedViaTaxTerm      = isset($ruleStatus['unload_via_tax_term']) && ! empty($pluginRules['unload_via_tax_term']['values']);
                            $unloadedViaTax          = isset($ruleStatus['unload_via_tax']) && ! empty($pluginRules['unload_via_tax']['values']);
                            $unloadedViaArchive      = isset($ruleStatus['unload_via_archive']) && ! empty($pluginRules['unload_via_archive']['values']);
                            $unloadedViaAuthorArchive = isset($ruleStatus['unload_via_author_archive']) && ! empty($pluginRules['unload_via_author_archive']['values']);

                            $unloadedViaRegEx        = isset($ruleStatus['unload_via_regex']) && isset($pluginRules['unload_via_regex']['value']) && $pluginRules['unload_via_regex']['value'];

                            $unloadedIfLoggedIn      = isset($ruleStatus['unload_logged_in']);
                            $unloadedLoggedInViaRole = isset($ruleStatus['unload_logged_in_via_role']) && ! empty($pluginRules['unload_logged_in_via_role']['values']);

                            $isViewMode      = Overview::isViewMode();
                            $valuesSeparator = $isViewMode ? ', ' : ' ';
                            $pageTypeTextEsc = esc_html($pageTypeText);

                            $formatRuleValues = function ($values, $ruleKey, $infoData, $valuesSeparator, $labelsList, $useStrong) {
                                $formattedValuesArray = array_map(function ($value) use ($ruleKey, $infoData, $labelsList, $useStrong) {
                                    $label = isset($labelsList[$value]) ? $labelsList[$value] : $value;
                                    $label = esc_html($label);

                                    if ($useStrong) {
                                        $label = '<strong>' . $label . '</strong>';
                                    }

                                    return Overview::renderNoWrapRuleOutput(
                                        $label,
                                        $infoData,
                                        $ruleKey,
                                        $value
                                    );
                                }, $values);

                                return implode($valuesSeparator, $formattedValuesArray);
                            };


                            $formatTaxonomyTermValues = function ($values, $ruleKey, $infoData, $valuesSeparator) {
                                $termTaxonomyIds = array_values(array_unique(array_filter(array_map('absint', (array)$values))));
                                $termDetails     = Overview::getTaxonomyTermDetailsByTermTaxonomyIds($termTaxonomyIds);
                                $formattedValues = array();

                                foreach ($termTaxonomyIds as $termTaxonomyId) {
                                    if ( ! empty($termDetails[$termTaxonomyId])) {
                                        $termData     = $termDetails[$termTaxonomyId];
                                        $termId       = (int)$termData['term_id'];
                                        $termEditLink = get_edit_term_link($termId, $termData['taxonomy']);
                                        $termSlug     = trim(rawurldecode((string)$termData['term_slug']));
                                        $tooltipText  = sprintf(
                                            __('Term Title: %1$s, Term Taxonomy: %2$s, Slug: %3$s', 'wp-asset-clean-up'),
                                            $termData['term_name'],
                                            $termData['taxonomy_label'],
                                            $termSlug !== '' ? $termSlug : __('(not set)', 'wp-asset-clean-up')
                                        );

                                        if (is_wp_error($termEditLink) || ! is_string($termEditLink) || $termEditLink === '') {
                                            $termIdOutput = '<span'
                                                . ' title="' . esc_attr($tooltipText) . '"'
                                                . ' class="wpacu-tooltip">'
                                                    . esc_html($termId)
                                                . '</span>';
                                        } else {
                                            $termIdOutput = '<a'
                                                . ' title="' . esc_attr($tooltipText) . '"'
                                                . ' class="wpacu-tooltip"'
                                                . ' target="_blank"'
                                                . ' href="' . esc_url($termEditLink) . '">'
                                                    . esc_html($termId)
                                                . '</a>';
                                        }
                                    } else {
                                        $termIdOutput = '<s'
                                            . ' class="wpacu-tooltip"'
                                            . ' title="' . esc_attr(sprintf(
                                                __('N/A (taxonomy term deleted); stored term_taxonomy_id: %d', 'wp-asset-clean-up'),
                                                $termTaxonomyId
                                            )) . '"'
                                            . ' style="color: #cc0000;">'
                                                . esc_html(sprintf(__('TT ID: %d', 'wp-asset-clean-up'), $termTaxonomyId))
                                            . '</s>';
                                    }

                                    $formattedValues[] = Overview::renderNoWrapRuleOutput(
                                        $termIdOutput,
                                        $infoData,
                                        $ruleKey,
                                        $termTaxonomyId
                                    );
                                }

                                return implode($valuesSeparator, $formattedValues);
                            };

                            $formatAuthorArchiveValues = function ($values, $ruleKey, $infoData, $valuesSeparator) {
                                $userIds = array_values(array_unique(array_filter(array_map('absint', (array)$values))));
                                $formattedValues = array();

                                foreach ($userIds as $userId) {
                                    $userData = get_userdata($userId);

                                    if ($userData) {
                                        $displayName = trim((string)$userData->display_name);

                                        if ($displayName === '') {
                                            $displayName = (string)$userData->user_login;
                                        }

                                        $label = $displayName . ' — ID: ' . $userId;
                                        $authorUrl = get_author_posts_url($userId);
                                        $labelOutput = '<a'
                                            . ' title="' . esc_attr($label) . '"'
                                            . ' class="wpacu-tooltip"'
                                            . ' target="_blank"'
                                            . ' href="' . esc_url($authorUrl) . '">'
                                                . esc_html($label)
                                            . '</a>';
                                    } else {
                                        $labelOutput = '<s'
                                            . ' class="wpacu-tooltip"'
                                            . ' title="' . esc_attr__('N/A (author deleted)', 'wp-asset-clean-up') . '"'
                                            . ' style="color: #cc0000;">'
                                                . esc_html(sprintf(
                                                    __('Unknown or deleted author — ID: %d', 'wp-asset-clean-up'),
                                                    $userId
                                                ))
                                            . '</s>';
                                    }

                                    $formattedValues[] = Overview::renderNoWrapRuleOutput(
                                        $labelOutput,
                                        $infoData,
                                        $ruleKey,
                                        $userId
                                    );
                                }

                                return implode($valuesSeparator, $formattedValues);
                            };

                           if ( $unloadSiteWide ) {
                                $ruleKey = 'unload_site_wide';

                                $ruleData = array(
                                    'status' => $ruleKey
                                );

                                $wrappedOutput = Overview::renderRuleOutput(
                                    '<span style="color: #cc0000;">Unloaded in all ' . esc_html($pageTypeText) . ' pages</span>',
                                    $infoData,
                                    $ruleData['status'],
                                    1
                                );

                                $ruleData['text'] = $wrappedOutput;

                                $rulesList[] = $ruleData;
                            } else {
                                if ( $unloadHomePage ) {
                                    $ruleKey = 'unload_home_page';

                                    $ruleData = array(
                                        'status' => $ruleKey
                                    );

                                    $wrappedOutput = Overview::renderNoWrapRuleOutput(
                                        '<span style="color: #cc0000;">Unloaded in the homepage</span>',
                                        $infoData,
                                        $ruleData['status'],
                                        1
                                    );

                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($wrappedOutput, $ruleData['status']);

                                    $rulesList[] = $ruleData;
                                }

                                if ($unloadedOnSelectedPosts) {
                                    $ruleKey = 'unload_via_post';

                                    $ruleData = array(
                                        'status' => $ruleKey,
                                        'values' => array_values(array_unique(array_map('absint', $pluginRules[$ruleKey]['values'])))
                                    );

                                    $formattedPostIds = array();

                                    foreach ($ruleData['values'] as $postId) {
                                        $postData = get_post($postId);

                                        if (isset($postData->post_title, $postData->post_type)) {
                                            $postIdOutput = '<a'
                                                . ' title="' . esc_attr(Overview::getPostTooltipText($postData)) . '"'
                                                . ' class="wpacu-tooltip"'
                                                . ' target="_blank"'
                                                . ' href="' . esc_url(admin_url('post.php?post=' . $postId . '&action=edit')) . '">'
                                                    . esc_html($postId)
                                                . '</a>';
                                        } else {
                                            $postIdOutput = '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">'
                                                . esc_html($postId)
                                                . '</s>';
                                        }

                                        $formattedPostIds[] = Overview::renderNoWrapRuleOutput(
                                            $postIdOutput,
                                            $infoData,
                                            $ruleKey,
                                            $postId
                                        );
                                    }

                                    $valuesOutput = implode($valuesSeparator, $formattedPostIds);

                                    $ruleData['text'] = '<span style="color: #cc0000;">Unloaded in all these frontend pages singular pages: ' . $valuesOutput . '</span>';
                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                    $rulesList[] = $ruleData;
                                }

                                if ( $unloadedViaPostType ) {
                                    $ruleKey = 'unload_via_post_type';

                                    $ruleData = array(
                                        'status' => $ruleKey,
                                        'values' => $pluginRules[$ruleKey]['values']
                                    );

                                    $valuesOutput = $formatRuleValues($ruleData['values'], $ruleKey, $infoData, $valuesSeparator, array(), true);

                                    $ruleData['text'] = '<span style="color: #cc0000;">Unloaded in all ' . $pageTypeTextEsc . ' pages belonging to the following post types: ' . $valuesOutput . '</span>';
                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                    $rulesList[] = $ruleData;
                                }

                                if ($unloadedViaPostTaxTerm) {
                                    $ruleKey = 'unload_via_post_tax_term';

                                    $ruleData = array(
                                        'status' => $ruleKey,
                                        'values' => $pluginRules[$ruleKey]['values']
                                    );

                                    $valuesOutput = $formatTaxonomyTermValues(
                                        $ruleData['values'],
                                        $ruleKey,
                                        $infoData,
                                        $valuesSeparator
                                    );

                                    $ruleData['text'] = '<span style="color: #cc0000;">Unloaded in all front-end singular pages associated with these taxonomy terms: ' . $valuesOutput . '</span>';
                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                    $rulesList[] = $ruleData;
                                }

                                if ($unloadedViaTaxTerm) {
                                    $ruleKey = 'unload_via_tax_term';

                                    $ruleData = array(
                                        'status' => $ruleKey,
                                        'values' => $pluginRules[$ruleKey]['values']
                                    );

                                    $valuesOutput = $formatTaxonomyTermValues(
                                        $ruleData['values'],
                                        $ruleKey,
                                        $infoData,
                                        $valuesSeparator
                                    );

                                    $ruleData['text'] = '<span style="color: #cc0000;">Unloaded in all these front-end taxonomy term pages: ' . $valuesOutput . '</span>';
                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                    $rulesList[] = $ruleData;
                                }

                                if ( $unloadedViaTax ) {
                                    $ruleKey = 'unload_via_tax';

                                    $ruleData = array(
                                        'status' => $ruleKey,
                                        'values' => $pluginRules[$ruleKey]['values']
                                    );

                                    $valuesOutput = $formatRuleValues($ruleData['values'], $ruleKey, $infoData, $valuesSeparator, $taxListValueToText, true);

                                    $ruleData['text'] = '<span style="color: #cc0000;">Unloaded in all ' . $pageTypeTextEsc . ' pages</span> of these <strong>taxonomy</strong> page types: ' . $valuesOutput;
                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                    $rulesList[] = $ruleData;
                                }

                                if ( $unloadedViaArchive ) {
                                    $ruleKey = 'unload_via_archive';

                                    $ruleData = array(
                                        'status' => $ruleKey,
                                        'values' => $pluginRules[$ruleKey]['values']
                                    );

                                    $valuesOutput = $formatRuleValues($ruleData['values'], $ruleKey, $infoData, $valuesSeparator, $archiveListValueToText, true);

                                    $ruleData['text'] = '<span style="color: #cc0000;">Unloaded in all ' . $pageTypeTextEsc . ' pages</span> of these archive and listing page types: ' . $valuesOutput;
                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                    $rulesList[] = $ruleData;
                                }

                                if ($unloadedViaAuthorArchive) {
                                    $ruleKey = 'unload_via_author_archive';

                                    $ruleData = array(
                                        'status' => $ruleKey,
                                        'values' => $pluginRules[$ruleKey]['values']
                                    );

                                    $valuesOutput = $formatAuthorArchiveValues(
                                        $ruleData['values'],
                                        $ruleKey,
                                        $infoData,
                                        $valuesSeparator
                                    );

                                    $ruleData['text'] = '<span style="color: #cc0000;">Unloaded on specific author archive pages: ' . $valuesOutput . '</span>';
                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                    $rulesList[] = $ruleData;
                                }

                                if ( $unloadedViaRegEx ) {
                                    $ruleKey = 'unload_via_regex';

                                    $hasMultipleLines = false;

                                    $regexValue = isset($pluginRules[$ruleKey]['value']) ? trim($pluginRules[$ruleKey]['value']) : '';

                                    if (strpos($regexValue, "\n") !== false || strpos($regexValue, "\r") !== false) {
                                        $hasMultipleLines = true;
                                    }

                                    $ruleData = array(
                                        'status' => $ruleKey,
                                        'values' => $regexValue
                                    );

                                    $appendAfterText = '';

                                    if (Overview::isViewMode()) {
                                        if ($hasMultipleLines) {
                                            $appendAfterText .= '<br />';
                                        }

                                        $appendAfterText .= '<code style="color: #cc0000;">' . nl2br(esc_html($ruleData['values'])) . '</code>';
                                    }

                                    $output = '<span style="color: #cc0000;">Unloaded in all ' . $pageTypeTextEsc . ' pages if the request URI matches any of these rules:</span> ' . $appendAfterText;

                                    $ruleData['text'] = Overview::renderNoWrapRuleOutput(
                                        $output,
                                        $infoData,
                                        $ruleKey,
                                        $ruleData['values']
                                    );

                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                    $rulesList[] = $ruleData;
                                }

                                if ( $unloadedIfLoggedIn ) {
                                    $ruleKey = 'unload_logged_in';

                                    $ruleData = array(
                                        'status' => $ruleKey
                                    );

                                    $wrappedOutput = Overview::renderNoWrapRuleOutput(
                                        '<span style="color: #cc0000;">Unloaded if the user is logged-in</span>',
                                        $infoData,
                                        $ruleData['status'],
                                        1
                                    );

                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($wrappedOutput, $ruleData['status']);

                                    $rulesList[] = $ruleData;
                                }

                                if ( $unloadedLoggedInViaRole ) {
                                    $ruleKey = 'unload_logged_in_via_role';

                                    $ruleData = array(
                                        'status' => $ruleKey,
                                        'values' => $pluginRules[$ruleKey]['values']
                                    );

                                    $formattedValuesArray = array_map(function ($value) use ($allUsersRoles, $infoData, $ruleKey) {
                                        if (isset($allUsersRoles[$value])) {
                                            $return = '<strong>' . translate_user_role($allUsersRoles[$value]['name']) . '</strong> (' . esc_html($value) . ')';
                                        } else {
                                            $return = esc_html($value);
                                        }

                                        return Overview::renderNoWrapRuleOutput($return, $infoData, $ruleKey, $value);
                                    }, $ruleData['values']);

                                    $valuesOutput = implode($valuesSeparator, $formattedValuesArray);

                                    $ruleData['text'] = '<span style="color: #cc0000;">Unloaded if the user is logged-in and has the following role(s): ' . $valuesOutput . '</span>';
                                    $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                    $rulesList[] = $ruleData;
                                }
							}

                           $noUnloadRuleSet = empty($rulesList);

                            $allLoadRuleKeys = array(
                                'load_home_page',
                                'load_via_post_type',
                                'load_via_post',
                                'load_via_post_tax_term',
                                'load_via_tax_term',
                                'load_via_tax',
                                'load_via_archive',
                                'load_via_author_archive',
                                'load_via_regex',
                                'load_logged_in',
                                'load_logged_in_via_role'
                            );

                            $ruleStatus = array_fill_keys($pluginRules['status'], true);

                            $loadedHomePage        = isset($ruleStatus['load_home_page']);
                            $loadedViaPostType     = isset($ruleStatus['load_via_post_type']) && ! empty($pluginRules['load_via_post_type']['values']);
                            $loadedOnSelectedPosts = isset($ruleStatus['load_via_post']) && ! empty($pluginRules['load_via_post']['values']);
                            $loadedViaPostTaxTerm  = isset($ruleStatus['load_via_post_tax_term']) && ! empty($pluginRules['load_via_post_tax_term']['values']);
                            $loadedViaTaxTerm      = isset($ruleStatus['load_via_tax_term']) && ! empty($pluginRules['load_via_tax_term']['values']);
                            $loadedViaTax          = isset($ruleStatus['load_via_tax']) && ! empty($pluginRules['load_via_tax']['values']);
                            $loadedViaArchive      = isset($ruleStatus['load_via_archive']) && ! empty($pluginRules['load_via_archive']['values']);
                            $loadedViaAuthorArchive = isset($ruleStatus['load_via_author_archive']) && ! empty($pluginRules['load_via_author_archive']['values']);

                            $loadedViaRegex        = isset($ruleStatus['load_via_regex'])
                                                || (isset($pluginRules['load_via_regex']['enable']) && $pluginRules['load_via_regex']['enable']); // legacy

                            $loadedLoggedIn        = isset($ruleStatus['load_logged_in'])
                                                || (isset($pluginRules['load_logged_in']['enable']) && $pluginRules['load_logged_in']['enable']); // legacy

                            $loadedLoggedInViaRole = isset($ruleStatus['load_logged_in_via_role']) && ! empty($pluginRules['load_logged_in_via_role']['values']);

							if ( $loadedHomePage ) {
                                $ruleKey = 'load_home_page';

                                $ruleData = array(
                                    'status' => $ruleKey
                                );

                                $text             = '<span style="color: green;">Loaded (as an exception) on the homepage</span>';
                                $ruleData['text'] = Overview::renderNoWrapRuleOutput($text, $infoData, $ruleData['status'], 1);
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleData['status']);

                                $rulesList[] = $ruleData;
							}

							if ( $loadedViaPostType ) {
                                $ruleKey = 'load_via_post_type';

                                $ruleData = array(
                                    'status' => $ruleKey,
                                    'values' => $pluginRules[$ruleKey]['values']
                                );

                                $valuesOutput = $formatRuleValues(
                                    $ruleData['values'],
                                    $ruleKey,
                                    $infoData,
                                    $valuesSeparator,
                                    array(),
                                    false
                                );

                                $ruleData['text'] = '<span style="color: green;">Loaded (as an exception) in all ' . $pageTypeTextEsc . ' pages of these post types: ' . $valuesOutput . '</span>';
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                $rulesList[] = $ruleData;
							}

                            if ($loadedOnSelectedPosts) {
                                $ruleKey = 'load_via_post';

                                $ruleData = array(
                                    'status' => $ruleKey,
                                    'values' => array_values(array_unique(array_map('absint', $pluginRules[$ruleKey]['values'])))
                                );

                                $formattedPostIds = array();

                                foreach ($ruleData['values'] as $postId) {
                                    $postData = get_post($postId);

                                    if (isset($postData->post_title, $postData->post_type)) {
                                        $postIdOutput = '<a'
                                            . ' title="' . esc_attr(Overview::getPostTooltipText($postData)) . '"'
                                            . ' class="wpacu-tooltip"'
                                            . ' target="_blank"'
                                            . ' href="' . esc_url(admin_url('post.php?post=' . $postId . '&action=edit')) . '">'
                                                . esc_html($postId)
                                            . '</a>';
                                    } else {
                                        $postIdOutput = '<s class="wpacu-tooltip" title="N/A (post deleted)" style="color: #cc0000;">'
                                            . esc_html($postId)
                                            . '</s>';
                                    }

                                    $formattedPostIds[] = Overview::renderNoWrapRuleOutput(
                                        $postIdOutput,
                                        $infoData,
                                        $ruleKey,
                                        $postId
                                    );
                                }

                                $valuesOutput = implode($valuesSeparator, $formattedPostIds);

                                $ruleData['text'] = '<span style="color: green;">Loaded (as an exception) on these singular pages: ' . $valuesOutput . '</span>';
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                $rulesList[] = $ruleData;
                            }

                            if ($loadedViaPostTaxTerm) {
                                $ruleKey = 'load_via_post_tax_term';

                                $ruleData = array(
                                    'status' => $ruleKey,
                                    'values' => $pluginRules[$ruleKey]['values']
                                );

                                $valuesOutput = $formatTaxonomyTermValues(
                                    $ruleData['values'],
                                    $ruleKey,
                                    $infoData,
                                    $valuesSeparator
                                );

                                $ruleData['text'] = '<span style="color: green;">Loaded (as an exception) in all front-end singular pages associated with these taxonomy terms: ' . $valuesOutput . '</span>';
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                $rulesList[] = $ruleData;
                            }

                            if ($loadedViaTaxTerm) {
                                $ruleKey = 'load_via_tax_term';

                                $ruleData = array(
                                    'status' => $ruleKey,
                                    'values' => $pluginRules[$ruleKey]['values']
                                );

                                $valuesOutput = $formatTaxonomyTermValues(
                                    $ruleData['values'],
                                    $ruleKey,
                                    $infoData,
                                    $valuesSeparator
                                );

                                $ruleData['text'] = '<span style="color: green;">Loaded as an exception on these taxonomy term pages: ' . $valuesOutput . '</span>';
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                $rulesList[] = $ruleData;
                            }

                            if ( $loadedViaTax ) {
                                $ruleKey = 'load_via_tax';

                                $ruleData = array(
                                    'status' => $ruleKey,
                                    'values' => $pluginRules[$ruleKey]['values']
                                );

                                $valuesOutput = $formatRuleValues(
                                    $ruleData['values'],
                                    $ruleKey,
                                    $infoData,
                                    $valuesSeparator,
                                    $taxListValueToText,
                                    true
                                );

                                $ruleData['text'] = '<span style="color: green;">Loaded (as an exception) in all ' . $pageTypeTextEsc . ' pages of these <strong>taxonomy</strong> page types:</span> ' . $valuesOutput;
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                $rulesList[] = $ruleData;
                            }

                            if ( $loadedViaArchive ) {
                                $ruleKey = 'load_via_archive';

                                $ruleData = array(
                                    'status' => $ruleKey,
                                    'values' => $pluginRules[$ruleKey]['values']
                                );

                                $valuesOutput = $formatRuleValues(
                                    $ruleData['values'],
                                    $ruleKey,
                                    $infoData,
                                    $valuesSeparator,
                                    $archiveListValueToText,
                                    true
                                );

                                $ruleData['text'] = '<span style="color: green;">Loaded (as an exception) in all ' . $pageTypeTextEsc . ' pages of these archive and listing page types:</span> ' . $valuesOutput;
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                $rulesList[] = $ruleData;
                            }

                            if ($loadedViaAuthorArchive) {
                                $ruleKey = 'load_via_author_archive';

                                $ruleData = array(
                                    'status' => $ruleKey,
                                    'values' => $pluginRules[$ruleKey]['values']
                                );

                                $valuesOutput = $formatAuthorArchiveValues(
                                    $ruleData['values'],
                                    $ruleKey,
                                    $infoData,
                                    $valuesSeparator
                                );

                                $ruleData['text'] = '<span style="color: green;">Loaded (as an exception) on specific author archive pages: ' . $valuesOutput . '</span>';
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                $rulesList[] = $ruleData;
                            }

                            $ruleKey = 'load_via_regex';

                            if ( (isset($pluginRules[$ruleKey]['enable']) || in_array($ruleKey, $pluginRules['status'])) && isset($pluginRules[$ruleKey]['value']) ) {
                                $ruleData = array(
                                    'status' => $ruleKey,
                                    'values' => $pluginRules[$ruleKey]['value']
                                );

                                $hasMultipleLines = false;

                                $regexValue = isset($pluginRules[$ruleKey]['value']) ? trim($pluginRules[$ruleKey]['value']) : '';

                                if (strpos($regexValue, "\n") !== false || strpos($regexValue, "\r") !== false) {
                                    $hasMultipleLines = true;
                                }

                                $appendAfterText = '';

                                if (Overview::isViewMode()) {
                                    if ($hasMultipleLines) {
                                        $appendAfterText .= '<br />';
                                    }

                                    $appendAfterText .= '<code style="color: green;">' . nl2br(esc_html($regexValue)) . '</code>';
                                }

                                $output = '<span style="color: green;">Loaded (as an exception) for all ' . $pageTypeTextEsc . ' URIs (from the URL) matching these rules:</span> ' . $appendAfterText;

                                $ruleData['text'] = Overview::renderNoWrapRuleOutput($output, $infoData, $ruleKey, $regexValue);
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                $rulesList[] = $ruleData;
                            }

                            $ruleKey = 'load_logged_in';

                            if (isset($pluginRules[$ruleKey]['enable']) || in_array($ruleKey, $pluginRules['status'])) {
                                $ruleData = array(
                                    'status' => $ruleKey
                                );

                                $text = '<span style="color: green;">Loaded (as an exception) in all ' . esc_html($pageTypeText) . ' pages if the user is logged in</span>';

                                $ruleData['text'] = Overview::renderNoWrapRuleOutput($text, $infoData, $ruleData['status'], 1);
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleData['status']);

                                $rulesList[] = $ruleData;
                            }

                            if ($loadedLoggedInViaRole) {
                                $ruleKey = 'load_logged_in_via_role';

                                $ruleData = array(
                                    'status' => $ruleKey,
                                    'values' => $pluginRules[$ruleKey]['values']
                                );

                                $formattedValuesArray = array_map(function ($value) use ($allUsersRoles, $infoData, $ruleKey) {
                                    $label = isset($allUsersRoles[$value])
                                        ? '<strong>' . translate_user_role($allUsersRoles[$value]['name']) . '</strong> (' . esc_html($value) . ')'
                                        : esc_html($value);

                                    return Overview::renderNoWrapRuleOutput($label, $infoData, $ruleKey, $value);
                                }, $ruleData['values']);

                                $valuesOutput = implode($valuesSeparator, $formattedValuesArray);

                                $ruleData['text'] = '<span style="color: green;">Loaded (as an exception) if the user is logged-in and has the following role(s): ' . $valuesOutput . '</span>';
                                $ruleData['text'] = Overview::wrapRuleViewChangeOutput($ruleData['text'], $ruleKey);

                                $rulesList[] = $ruleData;
                            }

                            if ( ! empty($rulesList) ) {
								foreach ($rulesList as $ruleData) {
                                    $ruleKey = isset($ruleData['status']) && is_string($ruleData['status'])
                                        ? $ruleData['status']
                                        : '';

                                    if (in_array($ruleKey, $inactiveRuleKeys, true)) {
                                        $inactiveExplanation = __('A value is saved for this rule, but its checkbox is not enabled. The rule is inactive; you can re-enable it in Plugins Manager or remove it here in Edit Mode.', 'wp-asset-clean-up');
                                        $inactiveHelpIcon = '<span class="dashicons dashicons-editor-help wpacu-overview-plugin-rule-inactive-help" tabindex="0" data-tooltip="'.esc_attr($inactiveExplanation).'" aria-label="'.esc_attr($inactiveExplanation).'"></span>';
                                        $inactiveRuleText = preg_replace('/<ul class="/', '<ul class="wpacu-overview-plugin-rule-inactive ', $ruleData['text'], 1);
                                        $inactiveRuleText = preg_replace('/<\/li>\s*<\/ul>\s*$/', $inactiveHelpIcon.'</li></ul>', $inactiveRuleText, 1);
                                        echo $inactiveRuleText . "\n";
                                    } else {
                                        echo $ruleData['text']."\n";
                                    }
								}

                                // There are just load exceptions left
                                if ($noUnloadRuleSet) {
                                    if (count($rulesList) > 1) {
                                        echo '<small><strong>'.esc_html__('Orphaned load exceptions:', 'wp-asset-clean-up').'</strong> <em>'.esc_html__('There are no unload rules left for this plugin, so the exceptions above are inactive and can be safely removed in Edit Mode.', 'wp-asset-clean-up').'</em></small>';
                                    } else {
                                        echo '<small><strong>'.esc_html__('Orphaned load exception:', 'wp-asset-clean-up').'</strong> <em>'.esc_html__('There is no unload rule left for this plugin, so the exception above is inactive and can be safely removed in Edit Mode.', 'wp-asset-clean-up').'</em></small>';
                                    }
                                }
							}
							?>
							<div class="wpacu_clearfix"></div>
						</td>
					</tr>
				<?php } ?>
			</table>
			<?php
		} else {
			?>
			<p><?php _e('There are no rules added to any of the active plugins.', 'wp-asset-clean-up'); ?></p>
			<?php
		}
	}
	?>
</div>
<hr style="margin: 15px 0;"/>
