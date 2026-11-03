<?php

defined('ABSPATH') || exit;

/**
 * Generate HTML extended tag cloud
 *
 * @param string $args
 *
 * @return string
 * @author WebFactory Ltd
 */
function st_get_tag_cloud($args = '')
{
    return SimpleTags_Client_TagCloud::extendedTagCloud($args);
}

/**
 * Display extended tag cloud
 *
 * @param string $args
 *
 * @return void
 * @author WebFactory Ltd
 */
function st_tag_cloud($args = '')
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo st_get_tag_cloud($args);
}

/**
 * Generate extended current tags post
 *
 * @param string $args
 *
 * @return string
 * @author WebFactory Ltd
 */
function st_get_the_tags($args = '')
{
    if (class_exists('SimpleTags_Client_PostTags')) {
        return SimpleTags_Client_PostTags::extendedPostTags($args);
    }

    return '';
}

/**
 * Display extended current tags post
 *
 * @param string $args
 *
 * @return void
 * @author WebFactory Ltd
 */
function st_the_tags($args = '')
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo st_get_the_tags($args);
}

/**
 * Generate related posts for a post in WP loop
 *
 * @param string $args
 *
 * @return string|array
 * @author WebFactory Ltd
 */
function st_get_related_posts($args = '')
{
    if (class_exists('SimpleTags_Client_RelatedPosts')) {
        return SimpleTags_Client_RelatedPosts::get_related_posts($args);
    }

    return '';
}

/**
 * Display related posts for a post in WP loop
 *
 * @param string $args
 *
 * @return void
 * @author WebFactory Ltd
 */
function st_related_posts($args = '')
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo st_get_related_posts($args);
}
