<?php
declare(strict_types=1);

namespace InfinityBlog\Http;

use InfinityBlog\Auth\AuthGuard;
use InfinityBlog\Auth\SessionManager;
use InfinityBlog\Auth\UserAccount;
use InfinityBlog\Categories\CategoryConflictException;
use InfinityBlog\Categories\CategoryIconHelper;
use InfinityBlog\Categories\CategoryNotFoundException;
use InfinityBlog\Categories\CategoryRepository;
use InfinityBlog\Storage\MediaService;

final class CategoryController
{
    private CategoryRepository $categories;
    private AuthGuard $guard;
    private SessionManager $sessions;
    private ?MediaService $media;

    public function __construct(
        CategoryRepository $categories,
        AuthGuard $guard,
        SessionManager $sessions,
        ?MediaService $media = null
    ) {
        $this->categories = $categories;
        $this->guard = $guard;
        $this->sessions = $sessions;
        $this->media = $media;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(Request $request): array
    {
        if ($request->path === '/categories') {
            if ($request->method !== 'GET') {
                return $this->methodNotAllowed();
            }

            $categories = $this->categories->listActive();
            return [
                'status' => 200,
                'body' => ['data' => ['categories' => $categories], 'meta' => ['total' => count($categories)]],
            ];
        }

        if ($request->path === '/admin/categories') {
            if ($request->method === 'GET') {
                $access = $this->requireAdmin($request, false);
                if (is_array($access)) {
                    return $access;
                }
                $categories = $this->categories->listAdmin();
                return [
                    'status' => 200,
                    'body' => ['data' => ['categories' => $categories], 'meta' => ['total' => count($categories)]],
                ];
            }
            if ($request->method === 'POST') {
                return $this->create($request);
            }

            return $this->methodNotAllowed();
        }

        if (preg_match('#^/admin/categories/([0-9]+)$#D', $request->path, $matches) === 1) {
            if ($request->method === 'PATCH' || $request->method === 'PUT') {
                return $this->update($request, $matches[1]);
            }
            if ($request->method === 'DELETE') {
                return $this->delete($request, $matches[1]);
            }

            return $this->methodNotAllowed();
        }

        if ($request->path === '/admin/categories/upload-logo') {
            if ($request->method !== 'POST') {
                return $this->methodNotAllowed();
            }
            return $this->uploadLogo($request);
        }

        if (preg_match('#^/admin/categories/([0-9]+)/(activate|deactivate)$#D', $request->path, $matches) === 1) {
            if ($request->method !== 'POST') {
                return $this->methodNotAllowed();
            }
            return $this->setActive($request, $matches[1], $matches[2] === 'activate');
        }

        return $this->error(404, 'NOT_FOUND', 'The requested resource was not found.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function create(Request $request): array
    {
        $access = $this->requireAdmin($request, true);
        if (is_array($access)) {
            return $access;
        }

        [$fields, $errors] = $this->validatedFields($request->jsonObject(), true);
        if ($errors !== []) {
            return $this->validationError($errors);
        }

        $iconName = $fields['icon_name'] ?? null;
        $customLogoPath = $fields['custom_logo_path'] ?? null;

        try {
            $category = $this->categories->create(
                $fields['name'],
                $fields['description'] ?? null,
                $access->id,
                $iconName,
                $customLogoPath
            );
            return ['status' => 201, 'body' => ['data' => ['category' => $category]]];
        } catch (CategoryConflictException $exception) {
            return $this->conflictError($exception);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function update(Request $request, string $idText): array
    {
        $access = $this->requireAdmin($request, true);
        if (is_array($access)) {
            return $access;
        }
        $categoryId = self::parseId($idText);
        if ($categoryId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The category ID is invalid.', ['id' => 'Use a positive integer category ID.']);
        }

        [$fields, $errors] = $this->validatedFields($request->jsonObject(), false);
        if ($errors !== []) {
            return $this->validationError($errors);
        }
        $current = $this->categories->findAdminCategory($categoryId);
        if ($current === null) {
            return $this->error(404, 'NOT_FOUND', 'The requested category was not found.');
        }
        $name = $fields['name'] ?? $current['name'];
        $description = array_key_exists('description', $fields) ? $fields['description'] : $current['description'];
        $iconName = array_key_exists('icon_name', $fields) ? $fields['icon_name'] : $current['icon_name'];
        $customLogoPath = array_key_exists('custom_logo_path', $fields) ? $fields['custom_logo_path'] : $current['custom_logo_path'];
        $removeCustomLogo = !empty($fields['remove_custom_logo']);

        try {
            $category = $this->categories->update(
                $categoryId,
                $name,
                $description,
                $access->id,
                $iconName,
                $customLogoPath,
                $removeCustomLogo
            );
            return ['status' => 200, 'body' => ['data' => ['category' => $category]]];
        } catch (CategoryNotFoundException) {
            return $this->error(404, 'NOT_FOUND', 'The requested category was not found.');
        } catch (CategoryConflictException $exception) {
            return $this->conflictError($exception);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function setActive(Request $request, string $idText, bool $active): array
    {
        $access = $this->requireAdmin($request, true);
        if (is_array($access)) {
            return $access;
        }
        $categoryId = self::parseId($idText);
        if ($categoryId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The category ID is invalid.', ['id' => 'Use a positive integer category ID.']);
        }

        try {
            $category = $this->categories->setActive($categoryId, $active, $access->id);
            return ['status' => 200, 'body' => ['data' => ['category' => $category]]];
        } catch (CategoryNotFoundException) {
            return $this->error(404, 'NOT_FOUND', 'The requested category was not found.');
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function delete(Request $request, string $idText): array
    {
        $access = $this->requireAdmin($request, true);
        if (is_array($access)) {
            return $access;
        }
        $categoryId = self::parseId($idText);
        if ($categoryId === null) {
            return $this->error(422, 'VALIDATION_ERROR', 'The category ID is invalid.', ['id' => 'Use a positive integer category ID.']);
        }

        try {
            $this->categories->delete($categoryId, $access->id);
            return ['status' => 200, 'body' => ['data' => ['message' => 'Category deleted.']]];
        } catch (CategoryNotFoundException) {
            return $this->error(404, 'NOT_FOUND', 'The requested category was not found.');
        } catch (CategoryConflictException $exception) {
            return $this->conflictError($exception);
        }
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function uploadLogo(Request $request): array
    {
        $access = $this->requireAdmin($request, true);
        if (is_array($access)) {
            return $access;
        }

        if (empty($_FILES['logo']) || !is_array($_FILES['logo'])) {
            return $this->error(422, 'VALIDATION_ERROR', 'No logo file was uploaded.', ['logo' => 'Please provide an image file.']);
        }

        $file = $_FILES['logo'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->error(422, 'UPLOAD_ERROR', 'Failed to upload file to server.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath) || !is_readable($tmpPath)) {
            return $this->error(422, 'INVALID_FILE', 'The uploaded file could not be read.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $size = (int) filesize($tmpPath);
        }
        if ($size > 1024 * 1024) { // 1 MB
            return $this->error(422, 'FILE_TOO_LARGE', 'The logo file size must not exceed 1 MB.', ['logo' => 'Maximum allowed size is 1 MB.']);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $tmpPath) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!is_string($mime) || !in_array($mime, $allowedMimes, true)) {
            return $this->error(422, 'INVALID_MIME_TYPE', 'Only JPEG, PNG, and WebP images are allowed.', ['logo' => 'Invalid image format.']);
        }

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            return $this->error(422, 'CORRUPT_IMAGE', 'The uploaded file is not a valid image.');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'png',
        };

        // Try storage via MediaService (Supabase) if available
        if ($this->media !== null) {
            try {
                $storedPath = $this->media->uploadCategoryLogo($file, $access->id);
                $url = CategoryIconHelper::resolveLogoUrl($storedPath);
                return [
                    'status' => 200,
                    'body' => [
                        'data' => [
                            'path' => $storedPath,
                            'url' => $url,
                        ],
                    ],
                ];
            } catch (\Throwable) {
                // If Supabase upload fails, gracefully fall through to safe local fallback
            }
        }

        // Safe local fallback storage in /public/uploads/category-logos/
        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/category-logos';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $randomHex = bin2hex(random_bytes(12));
        $timestamp = gmdate('Ymd_His');
        $filename = "cat_logo_{$timestamp}_{$randomHex}.{$ext}";
        $destPath = $uploadDir . '/' . $filename;

        if (!@move_uploaded_file($tmpPath, $destPath)) {
            if (!@copy($tmpPath, $destPath)) {
                return $this->error(500, 'STORAGE_FAILED', 'Could not save the uploaded logo file.');
            }
        }

        $publicUrl = '/uploads/category-logos/' . $filename;
        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'path' => $publicUrl,
                    'url' => $publicUrl,
                ],
            ],
        ];
    }

    /** @return UserAccount|array{status: int, body: array<string, mixed>} */
    private function requireAdmin(Request $request, bool $stateChanging): UserAccount|array
    {
        $account = $this->guard->currentAccount();
        if ($account === null) {
            return $this->error(401, 'UNAUTHENTICATED', 'Authentication is required.');
        }
        if ($account->role !== 'admin') {
            return $this->error(403, 'FORBIDDEN', 'Administrator access is required.');
        }

        $token = $request->header('X-CSRF-Token');
        if (!is_string($token) || $token === '') {
            $token = $_POST['csrf_token'] ?? null;
        }

        if ($stateChanging && (!is_string($token) || !$this->sessions->validateCsrfToken($token))) {
            return $this->error(403, 'CSRF_INVALID', 'The request could not be verified.');
        }

        return $account;
    }

    /** @param array<string, mixed> $input
     *  @return array{array<string, mixed>, array<string, string>}
     */
    private function validatedFields(array $input, bool $creating): array
    {
        $fields = [];
        $errors = [];
        foreach (array_keys($input) as $key) {
            if (!in_array($key, ['name', 'description', 'icon_name', 'custom_logo_path', 'remove_custom_logo', 'csrf_token'], true)) {
                $errors[$key === 'slug' ? 'slug' : '_request'] = $key === 'slug'
                    ? 'The slug is generated by the server.'
                    : 'Only name, description, icon_name, and custom_logo_path are supported.';
            }
        }

        if ($creating && !array_key_exists('name', $input)) {
            $errors['name'] = 'A category name is required.';
        } elseif (array_key_exists('name', $input)) {
            if (!is_string($input['name']) || preg_match('//u', $input['name']) !== 1
                || preg_match('/\p{Cc}/u', $input['name']) === 1) {
                $errors['name'] = 'Enter a valid category name.';
            } else {
                $name = preg_replace('/\s+/u', ' ', trim($input['name']));
                $length = is_string($name) ? self::characterLength($name) : null;
                if (!is_string($name) || $name === '' || $length === null || $length > 120) {
                    $errors['name'] = 'Category names must contain 1 to 120 characters.';
                } else {
                    $fields['name'] = $name;
                }
            }
        }

        if (array_key_exists('description', $input)) {
            $description = $input['description'];
            if ($description !== null && (!is_string($description) || preg_match('//u', $description) !== 1
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $description) === 1)) {
                $errors['description'] = 'Enter a valid category description or null.';
            } elseif (is_string($description)) {
                $description = trim($description);
                $length = self::characterLength($description);
                if ($length === null || $length > 500) {
                    $errors['description'] = 'Descriptions may contain at most 500 characters.';
                } else {
                    $fields['description'] = $description === '' ? null : $description;
                }
            } else {
                $fields['description'] = null;
            }
        }

        if (array_key_exists('icon_name', $input)) {
            $icon = $input['icon_name'];
            if ($icon !== null && (!is_string($icon) || !CategoryIconHelper::isValidIcon($icon))) {
                $errors['icon_name'] = 'The selected icon is invalid or not in the allowlist.';
            } else {
                $fields['icon_name'] = is_string($icon) && trim($icon) !== '' ? trim($icon) : null;
            }
        }

        if (array_key_exists('custom_logo_path', $input)) {
            $path = $input['custom_logo_path'];
            if ($path !== null && (!is_string($path) || strlen(trim($path)) > 255)) {
                $errors['custom_logo_path'] = 'The custom logo path must be at most 255 characters.';
            } else {
                $fields['custom_logo_path'] = is_string($path) && trim($path) !== '' ? trim($path) : null;
            }
        }

        if (array_key_exists('remove_custom_logo', $input)) {
            $fields['remove_custom_logo'] = (bool) $input['remove_custom_logo'];
        }

        if (!$creating && $fields === [] && $errors === []) {
            $errors['_request'] = 'Provide fields to update.';
        }

        return [$fields, $errors];
    }

    private static function characterLength(string $value): ?int
    {
        $length = preg_match_all('/./us', $value);

        return $length === false ? null : $length;
    }

    private static function parseId(string $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    /** @param array<string, string> $errors
     *  @return array{status: int, body: array<string, mixed>}
     */
    private function validationError(array $errors): array
    {
        return $this->error(422, 'VALIDATION_ERROR', 'One or more fields need attention.', $errors);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function conflictError(CategoryConflictException $exception): array
    {
        if ($exception->reason === CategoryConflictException::NAME_EXISTS) {
            return $this->error(409, 'CATEGORY_NAME_EXISTS', 'A category with that name already exists.');
        }
        if ($exception->reason === CategoryConflictException::IN_USE) {
            return $this->error(409, 'CATEGORY_IN_USE', 'A category assigned to posts cannot be deleted.');
        }

        return $this->error(409, 'CATEGORY_SLUG_UNAVAILABLE', 'A unique category slug could not be generated.');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function methodNotAllowed(): array
    {
        return $this->error(405, 'METHOD_NOT_ALLOWED', 'The request method is not supported.');
    }

    /** @param array<string, string> $fields
     *  @return array{status: int, body: array<string, mixed>}
     */
    private function error(int $status, string $code, string $message, array $fields = []): array
    {
        return ['status' => $status, 'body' => ApiErrorResponder::build($code, $message, $fields)];
    }
}
