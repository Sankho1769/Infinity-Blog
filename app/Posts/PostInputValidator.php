<?php
declare(strict_types=1);

namespace InfinityBlog\Posts;

final class PostInputValidator
{
    /**
     * @param array<string, mixed> $input
     * @return array{array{title?: string, content?: string, category_id?: ?int, remixed_from_post_id?: ?int}, array<string, string>}
     */
    public static function validate(array $input, bool $creating): array
    {
        $fields = [];
        $errors = [];
        foreach (array_keys($input) as $key) {
            if (!in_array($key, ['title', 'content', 'category_id', 'status', 'remixed_from_post_id'], true)) {
                $errors[$key === 'author_id' ? 'author_id' : '_request'] = $key === 'author_id'
                    ? 'The author is assigned by the authenticated session.'
                    : 'Unsupported post field.';
            }
        }
        if ($creating && array_key_exists('status', $input) && $input['status'] !== 'draft') {
            $errors['status'] = 'Create posts as drafts; publish them through the publish operation.';
        } elseif (!$creating && array_key_exists('status', $input)) {
            $errors['status'] = 'Use the publish or unpublish operation to change status.';
        }
        if ($creating && !array_key_exists('title', $input)) {
            $errors['title'] = 'A post title is required.';
        }
        if ($creating && !array_key_exists('content', $input)) {
            $errors['content'] = 'Article content is required.';
        }
        if (array_key_exists('title', $input)) {
            $title = $input['title'];
            if (!is_string($title) || preg_match('//u', $title) !== 1 || preg_match('/\p{Cc}/u', $title) === 1) {
                $errors['title'] = 'Enter a valid post title.';
            } else {
                $title = trim($title);
                $length = preg_match_all('/./us', $title);
                if ($title === '' || $length === false || $length > 255) {
                    $errors['title'] = 'Titles must contain 1 to 255 characters.';
                } else {
                    $fields['title'] = $title;
                }
            }
        }
        if (array_key_exists('content', $input)) {
            $content = $input['content'];
            if (!is_string($content) || preg_match('//u', $content) !== 1
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $content) === 1) {
                $errors['content'] = 'Enter valid UTF-8 article content.';
            } elseif (trim($content) === '') {
                $errors['content'] = 'Article content cannot be empty.';
            } elseif (strlen($content) > 524288) {
                $errors['content'] = 'Article content may not exceed 512 KiB.';
            } else {
                $fields['content'] = $content;
            }
        }
        if (array_key_exists('category_id', $input)) {
            $category = $input['category_id'];
            if ($category !== null && (!is_int($category) || $category < 1)) {
                $errors['category_id'] = 'Use a positive integer category ID or null.';
            } else {
                $fields['category_id'] = $category;
            }
        } elseif ($creating) {
            $fields['category_id'] = null;
        }
        if (array_key_exists('remixed_from_post_id', $input)) {
            $remixedFrom = $input['remixed_from_post_id'];
            if ($remixedFrom !== null && (!is_int($remixedFrom) || $remixedFrom < 1)) {
                $errors['remixed_from_post_id'] = 'Use a positive integer post ID or null for remixed_from_post_id.';
            } else {
                $fields['remixed_from_post_id'] = $remixedFrom;
            }
        } elseif ($creating) {
            $fields['remixed_from_post_id'] = null;
        }
        if (!$creating && $fields === [] && $errors === []) {
            $errors['_request'] = 'Provide a title, content, or category_id to update.';
        }

        return [$fields, $errors];
    }
}
