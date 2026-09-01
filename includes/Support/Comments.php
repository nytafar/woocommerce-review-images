<?php
/**
 * Comment resolution.
 *
 * One place that answers "is this thing a product review", so the avatar
 * filters, the block and the checks all ask the same question the same way.
 *
 * @package KaupangReviewImages
 */

declare(strict_types=1);

namespace Kaupang\ReviewImages\Support;

defined('ABSPATH') || exit;

final class Comments
{
    /**
     * Resolve a get_avatar()-style identifier to a product review.
     *
     * Only comment objects resolve. A numeric $idOrEmail in get_avatar() is a
     * USER id, never a comment id -- reading it as one was a latent bug that
     * the old is_product() gate happened to keep out of reach.
     *
     * @param mixed $idOrEmail
     */
    public static function productReview($idOrEmail): ?\WP_Comment
    {
        if (!is_object($idOrEmail) || !isset($idOrEmail->comment_ID)) {
            return null;
        }

        $comment = $idOrEmail instanceof \WP_Comment
            ? $idOrEmail
            : get_comment((int) $idOrEmail->comment_ID);

        return $comment ? self::asProductReview($comment) : null;
    }

    /**
     * The gate itself: a review comment sitting on a product.
     *
     * Deliberately strict about comment_type. Staging carries 143 rows typed
     * 'review' against 2 typed 'comment' on products; the two are ordinary
     * comments, not legacy reviews.
     */
    public static function asProductReview(\WP_Comment $comment): ?\WP_Comment
    {
        if ($comment->comment_type !== 'review') {
            return null;
        }

        if (get_post_type((int) $comment->comment_post_ID) !== 'product') {
            return null;
        }

        return $comment;
    }
}
