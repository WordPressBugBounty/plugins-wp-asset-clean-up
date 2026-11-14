<?php
namespace WpAssetCleanUp\Admin;

use WpAssetCleanUp\Menu;

/** Shared Alternative Assets selectors; independent of Pro Plugins Manager. */
class AlternativeAssetsChoices
{
    const SINGULAR_POSTS_AJAX_RESULTS_LIMIT = 20;
    const TAXONOMY_TERMS_AJAX_RESULTS_LIMIT = 20;
    const AUTHOR_ARCHIVES_AJAX_RESULTS_LIMIT = 20;

    public static function getSelectedSingularPostOptions($postIds)
    {
        if (empty($postIds)) {
            return array();
        }

        $postTypes = self::getPublicPostTypeObjectsForSingularRules();
        $publicPostTypes = array_keys($postTypes);

        if (empty($publicPostTypes)) {
            return array();
        }

        $posts = get_posts(array(
            'post__in'               => $postIds,
            'post_type'              => $publicPostTypes,
            'post_status'            => self::getSingularPostStatusesForRules(),
            'posts_per_page'         => count($postIds),
            'orderby'                => 'post__in',
            'suppress_filters'        => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));
        $options = array();

        foreach ($posts as $post) {
            if ( ! isset($postTypes[$post->post_type])) {
                continue;
            }

            $options[(int)$post->ID] = self::prepareSingularPostOptionData(
                $post,
                $postTypes[$post->post_type],
                true
            );
        }

        return $options;
    }

    public static function getSelectedAuthorArchiveOptions($userIds)
    {
        static $cachedOptions = array();

        $userIds = array_values(array_unique(array_filter(array_map('absint', (array)$userIds))));

        if (empty($userIds)) {
            return array();
        }

        $missingUserIds = array_values(array_diff($userIds, array_keys($cachedOptions)));

        if ( ! empty($missingUserIds)) {
            $users = get_users(array(
                'include' => $missingUserIds,
                'fields'  => array('ID', 'display_name', 'user_login')
            ));
            $foundUserIds = array();

            foreach ($users as $user) {
                $userId = (int)$user->ID;
                $foundUserIds[$userId] = true;
                $cachedOptions[$userId] = self::prepareAuthorArchiveOptionData($user);
            }

            foreach ($missingUserIds as $userId) {
                if ( ! isset($foundUserIds[$userId])) {
                    $cachedOptions[$userId] = self::getMissingAuthorArchiveOptionData($userId);
                }
            }
        }

        $selectedOptions = array();

        foreach ($userIds as $userId) {
            if (isset($cachedOptions[$userId])) {
                $selectedOptions[$userId] = $cachedOptions[$userId];
            }
        }

        return $selectedOptions;
    }

    public static function getSelectedTaxonomyTermOptions($termTaxonomyIds)
    {
        static $cachedOptions = array();

        if (empty($termTaxonomyIds)) {
            return array();
        }

        $termTaxonomyIds = array_values(array_unique(array_map('absint', $termTaxonomyIds)));
        $missingTermTaxonomyIds = array_values(array_diff($termTaxonomyIds, array_keys($cachedOptions)));

        if ( ! empty($missingTermTaxonomyIds)) {
            global $wpdb;

            $placeholders = implode(', ', array_fill(0, count($missingTermTaxonomyIds), '%d'));
            $sql = "
                SELECT tt.term_taxonomy_id, tt.taxonomy, tt.parent, tt.count, t.term_id, t.name
                FROM {$wpdb->term_taxonomy} AS tt
                INNER JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
                WHERE tt.term_taxonomy_id IN ({$placeholders})
            ";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $missingTermTaxonomyIds), ARRAY_A);
            $taxonomies = self::getPublicTaxonomyObjectsForTermRules();

            foreach ($rows as $row) {
                $taxonomy = $row['taxonomy'];
                $taxonomyLabel = isset($taxonomies[$taxonomy]->labels->name)
                    ? $taxonomies[$taxonomy]->labels->name
                    : $taxonomy;
                $termPathLabel = self::formatTaxonomyTermPathLabel($row);

                $cachedOptions[(int)$row['term_taxonomy_id']] = array(
                    'option_label'  => sprintf(
                        '%s: %s',
                        $taxonomyLabel,
                        self::formatAjaxTaxonomyTermLabel($row)
                    ),
                    'display_label' => sprintf(
                        '%s: %s',
                        $taxonomyLabel,
                        $termPathLabel
                    )
                );
            }
        }

        $selectedOptions = array();

        foreach ($termTaxonomyIds as $termTaxonomyId) {
            if (isset($cachedOptions[$termTaxonomyId])) {
                $selectedOptions[$termTaxonomyId] = $cachedOptions[$termTaxonomyId];
            }
        }

        return $selectedOptions;
    }

    public static function ajaxSearchSingularPosts()
    {
        check_ajax_referer(WPACU_PLUGIN_ID . '_alternative_assets_search_posts', 'wpacu_security');

        if ( ! Menu::userCanManageAlternativeAssets() ) {
            wp_send_json_error(array('message' => __('You are not allowed to perform this action.', 'wp-asset-clean-up')), 403);
        }

        global $wpdb;
        $keyword = isset($_GET['keyword']) ? sanitize_text_field(wp_unslash($_GET['keyword'])) : '';
        $page = isset($_GET['page_number']) ? max(1, absint($_GET['page_number'])) : 1;

        if ((function_exists('mb_strlen') ? mb_strlen($keyword) : strlen($keyword)) < 2) {
            wp_send_json(array('results' => array(), 'pagination' => array('more' => false)));
        }

        $postTypes = self::getPublicPostTypeObjectsForSingularRules();
        $postTypeKeys = array_keys($postTypes);

        if (empty($postTypeKeys)) {
            wp_send_json(array('results' => array(), 'pagination' => array('more' => false)));
        }

        $limit = max(1, (int)apply_filters(
            'wpacu_alternative_assets_singular_posts_ajax_results_limit',
            self::SINGULAR_POSTS_AJAX_RESULTS_LIMIT
        ));
        $offset = ($page - 1) * $limit;
        $typePlaceholders = implode(', ', array_fill(0, count($postTypeKeys), '%s'));
        $like = '%' . $wpdb->esc_like($keyword) . '%';
        $whereSearch = '(post_title LIKE %s OR post_name LIKE %s)';
        $args = array_merge($postTypeKeys, array($like, $like));

        if (ctype_digit($keyword)) {
            $whereSearch = '(ID = %d OR post_title LIKE %s OR post_name LIKE %s)';
            $args = array_merge($postTypeKeys, array((int)$keyword, $like, $like));
        }

        $args[] = $limit + 1;
        $args[] = $offset;
        $sql = "SELECT ID, post_title, post_type, post_status FROM {$wpdb->posts}
                WHERE post_type IN ({$typePlaceholders})
                  AND post_status IN ('publish', 'private', 'inherit')
                  AND {$whereSearch}
                ORDER BY post_type ASC, post_title ASC, ID DESC
                LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
        $hasMore = count($rows) > $limit;
        if ($hasMore) { array_pop($rows); }

        $groups = array();
        foreach ($rows as $row) {
            $postType = $row['post_type'];
            $groupLabel = isset($postTypes[$postType]->labels->name) ? $postTypes[$postType]->labels->name : $postType;
            if ( ! isset($groups[$postType])) {
                $groups[$postType] = array('text' => $groupLabel, 'children' => array());
            }
            $title = trim((string)$row['post_title']);
            $title = $title !== '' ? $title : __('(no title)', 'wp-asset-clean-up');
            $label = sprintf('%s — ID: %d', $title, (int)$row['ID']);
            $groups[$postType]['children'][] = array(
                'id' => (string)$row['ID'],
                'text' => $label,
                'selection_text' => sprintf('%s — %s', $title, $groupLabel),
            );
        }

        wp_send_json(array('results' => array_values($groups), 'pagination' => array('more' => $hasMore)));
    }

    public static function ajaxSearchAuthorArchives()
    {
        check_ajax_referer(WPACU_PLUGIN_ID . '_alternative_assets_search_authors', 'wpacu_security');

        if ( ! Menu::userCanManageAlternativeAssets()) {
            wp_send_json_error(array('message' => __('You are not allowed to perform this action.', 'wp-asset-clean-up')), 403);
        }

        $keyword = isset($_GET['keyword']) ? sanitize_text_field(wp_unslash($_GET['keyword'])) : '';
        $page = isset($_GET['page_number']) ? max(1, absint($_GET['page_number'])) : 1;

        if ((function_exists('mb_strlen') ? mb_strlen($keyword) : strlen($keyword)) < 1) {
            wp_send_json(array('results' => array(), 'pagination' => array('more' => false)));
        }

        $limit = max(1, (int)apply_filters(
            'wpacu_alternative_assets_author_archives_ajax_results_limit',
            self::AUTHOR_ARCHIVES_AJAX_RESULTS_LIMIT
        ));
        $offset = ($page - 1) * $limit;
        $userQuery = new \WP_User_Query(array(
            'number'         => $limit + 1,
            'offset'         => $offset,
            'orderby'        => 'display_name',
            'order'          => 'ASC',
            'fields'         => array('ID', 'display_name', 'user_login'),
            'count_total'    => false,
            'search'         => '*' . $keyword . '*',
            'search_columns' => array('user_login', 'user_nicename', 'user_email', 'display_name')
        ));
        $users = $userQuery->get_results();
        $users = is_array($users) ? $users : array();
        $hasMore = count($users) > $limit;

        if ($hasMore) {
            array_pop($users);
        }

        // WP_User_Query does not search the numeric ID column through `search_columns`.
        // Add an exact ID match on the first page while keeping the normal keyword results.
        if ($page === 1 && ctype_digit($keyword)) {
            $exactUserId = absint($keyword);
            $exactUser = $exactUserId > 0 ? get_user_by('id', $exactUserId) : false;
            $isCurrentSiteUser = true;

            if ($exactUser && is_multisite() && function_exists('is_user_member_of_blog')) {
                $isCurrentSiteUser = is_user_member_of_blog($exactUserId);
            }

            if ($exactUser && $isCurrentSiteUser) {
                $alreadyIncluded = false;

                foreach ($users as $user) {
                    if ((int)$user->ID === $exactUserId) {
                        $alreadyIncluded = true;
                        break;
                    }
                }

                if ( ! $alreadyIncluded) {
                    // Keep the complete first batch after prepending the exact ID match.
                    // Trimming it here would skip one normal result because page two still
                    // uses the original query offset.
                    array_unshift($users, $exactUser);
                }
            }
        }

        $results = array();

        foreach ($users as $user) {
            $optionData = self::prepareAuthorArchiveOptionData($user);
            $results[] = array(
                'id'             => (string)$user->ID,
                'text'           => $optionData['option_label'],
                'selection_text' => $optionData['display_label']
            );
        }

        wp_send_json(array(
            'results'    => $results,
            'pagination' => array('more' => $hasMore)
        ));
    }

    public static function ajaxSearchTaxonomyTerms()
    {
        check_ajax_referer(WPACU_PLUGIN_ID . '_alternative_assets_search_terms', 'wpacu_security');

        if ( ! Menu::userCanManageAlternativeAssets() ) {
            wp_send_json_error(array('message' => __('You are not allowed to perform this action.', 'wp-asset-clean-up')), 403);
        }

        global $wpdb;

        $keyword = isset($_GET['keyword']) ? sanitize_text_field(wp_unslash($_GET['keyword'])) : '';
        $page = isset($_GET['page_number']) ? max(1, absint($_GET['page_number'])) : 1;
        $minimumInputLength = 1;

        if (function_exists('mb_strlen')) {
            if (mb_strlen($keyword) < $minimumInputLength) {
                wp_send_json(array('results' => array(), 'pagination' => array('more' => false)));
            }
        } elseif (strlen($keyword) < $minimumInputLength) {
            wp_send_json(array('results' => array(), 'pagination' => array('more' => false)));
        }

        $taxonomies = self::getPublicTaxonomyObjectsForTermRules();
        $taxonomyKeys = array_keys($taxonomies);

        if (empty($taxonomyKeys)) {
            wp_send_json(array('results' => array(), 'pagination' => array('more' => false)));
        }

        $limit = max(1, (int)apply_filters(
            'wpacu_alternative_assets_taxonomy_terms_ajax_results_limit',
            self::TAXONOMY_TERMS_AJAX_RESULTS_LIMIT
        ));
        $offset = ($page - 1) * $limit;
        $taxonomyPlaceholders = implode(', ', array_fill(0, count($taxonomyKeys), '%s'));
        $likeKeyword = '%' . $wpdb->esc_like($keyword) . '%';

        $whereSearch = 't.name LIKE %s';
        $queryArgs = array_merge($taxonomyKeys, array($likeKeyword));

        if (ctype_digit($keyword)) {
            $whereSearch = '(t.name LIKE %s OR t.term_id = %d OR tt.term_taxonomy_id = %d)';
            $queryArgs[] = (int)$keyword;
            $queryArgs[] = (int)$keyword;
        }

        $queryArgs[] = $limit + 1;
        $queryArgs[] = $offset;

        $sql = "
            SELECT tt.term_taxonomy_id, tt.taxonomy, tt.parent, tt.count, t.term_id, t.name
            FROM {$wpdb->term_taxonomy} AS tt
            INNER JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ({$taxonomyPlaceholders})
              AND {$whereSearch}
            ORDER BY tt.taxonomy ASC, t.name ASC
            LIMIT %d OFFSET %d
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $queryArgs), ARRAY_A);
        $hasMore = count($rows) > $limit;

        if ($hasMore) {
            array_pop($rows);
        }

        $groupedResults = array();

        foreach ($rows as $row) {
            $taxonomy = $row['taxonomy'];
            $taxonomyLabel = isset($taxonomies[$taxonomy]->labels->name)
                ? $taxonomies[$taxonomy]->labels->name
                : $taxonomy;

            if ( ! isset($groupedResults[$taxonomy])) {
                $groupedResults[$taxonomy] = array(
                    'text'     => $taxonomyLabel,
                    'children' => array()
                );
            }

            $termLabel = self::formatAjaxTaxonomyTermLabel($row);

            $groupedResults[$taxonomy]['children'][] = array(
                'id'             => (string)$row['term_taxonomy_id'],
                'text'           => $termLabel,
                'selection_text' => sprintf(
                    '%s: %s',
                    $taxonomyLabel,
                    self::formatTaxonomyTermPathLabel($row)
                )
            );
        }

        self::sortTaxonomyGroups($groupedResults);

        wp_send_json(array(
            'results'    => array_values($groupedResults),
            'pagination' => array('more' => $hasMore)
        ));
    }

    private static function getPublicPostTypeObjectsForSingularRules()
    {
        static $postTypes = null;

        if ($postTypes === null) {
            $postTypes = get_post_types(array('public' => true), 'objects');
        }

        return is_array($postTypes) ? $postTypes : array();
    }

    private static function getSingularPostStatusesForRules()
    {
        return array('publish', 'private', 'inherit');
    }

    private static function prepareSingularPostOptionData($post, $postTypeObject, $includePostTypeInOptionLabel = false)
    {
        $title = get_the_title($post);
        $title = $title !== '' ? $title : __('(no title)', 'wp-asset-clean-up');
        $postTypeLabel = self::getSingularPostTypeLabel($postTypeObject);
        $optionLabel = $includePostTypeInOptionLabel
            ? sprintf('%s — %s — ID: %d', $title, $postTypeLabel, $post->ID)
            : sprintf('%s — ID: %d', $title, $post->ID);

        return array(
            'option_label'  => $optionLabel,
            'display_label' => sprintf('%s — %s', $title, $postTypeLabel)
        );
    }

    private static function prepareAuthorArchiveOptionData($user)
    {
        $userId = isset($user->ID) ? (int)$user->ID : 0;
        $displayName = isset($user->display_name) ? trim((string)$user->display_name) : '';

        if ($displayName === '' && isset($user->user_login)) {
            $displayName = trim((string)$user->user_login);
        }

        if ($displayName === '') {
            $displayName = __('(no display name)', 'wp-asset-clean-up');
        }

        $label = sprintf('%s — ID: %d', $displayName, $userId);

        return array(
            'option_label'  => $label,
            'display_label' => $label
        );
    }

    private static function getMissingAuthorArchiveOptionData($userId)
    {
        $label = sprintf(
            __('Unknown or deleted author — ID: %d', 'wp-asset-clean-up'),
            (int)$userId
        );

        return array(
            'option_label'  => $label,
            'display_label' => $label
        );
    }

    private static function getPublicTaxonomyObjectsForTermRules()
    {
        static $taxonomies = null;

        if ($taxonomies === null) {
            $taxonomies = get_taxonomies(array('public' => true, 'show_ui' => true), 'objects');
        }

        return is_array($taxonomies) ? $taxonomies : array();
    }

    private static function formatTaxonomyTermPathLabel($row)
    {
        $termPath = array();
        $taxonomy = (string)$row['taxonomy'];
        $termId = (int)$row['term_id'];

        if ((int)$row['parent'] > 0 && taxonomy_exists($taxonomy)) {
            $ancestorIds = array_reverse(get_ancestors($termId, $taxonomy, 'taxonomy'));

            foreach ($ancestorIds as $ancestorId) {
                $ancestor = get_term($ancestorId, $taxonomy);

                if ($ancestor && ! is_wp_error($ancestor)) {
                    $termPath[] = $ancestor->name;
                }
            }
        }

        $termPath[] = (string)$row['name'];

        return implode(' › ', $termPath);
    }

    private static function formatAjaxTaxonomyTermLabel($row)
    {
        $termId = (int)$row['term_id'];
        $postsCount = (int)$row['count'];
        $postsCountText = sprintf(
            _n('%s post', '%s posts', $postsCount, 'wp-asset-clean-up'),
            number_format_i18n($postsCount)
        );

        return sprintf(
            '%s (%s) — ID: %d',
            self::formatTaxonomyTermPathLabel($row),
            $postsCountText,
            $termId
        );
    }

    private static function sortTaxonomyGroups(&$groups)
    {
        uksort($groups, static function ($taxonomyA, $taxonomyB) use ($groups) {
            $priority = array(
                'category' => 0,
                'post_tag' => 1
            );

            $priorityA = isset($priority[$taxonomyA]) ? $priority[$taxonomyA] : 10;
            $priorityB = isset($priority[$taxonomyB]) ? $priority[$taxonomyB] : 10;

            if ($priorityA !== $priorityB) {
                // PHP 5.6 compatible (the spaceship operator `<=>` requires PHP 7.0+)
                return ($priorityA < $priorityB) ? -1 : 1;
            }

            $labelA = isset($groups[$taxonomyA]['label']) ? $groups[$taxonomyA]['label'] : $groups[$taxonomyA]['text'];
            $labelB = isset($groups[$taxonomyB]['label']) ? $groups[$taxonomyB]['label'] : $groups[$taxonomyB]['text'];

            return strnatcasecmp($labelA, $labelB);
        });
    }

    private static function getSingularPostTypeLabel($postTypeObject, $singular = true)
    {
        if ($singular && ! empty($postTypeObject->labels->singular_name)) {
            return $postTypeObject->labels->singular_name;
        }

        if ( ! $singular && ! empty($postTypeObject->labels->name)) {
            return $postTypeObject->labels->name;
        }

        if ( ! empty($postTypeObject->label)) {
            return $postTypeObject->label;
        }

        return ! empty($postTypeObject->name) ? $postTypeObject->name : '';
    }
}
