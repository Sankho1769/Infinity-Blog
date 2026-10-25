<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use InfinityBlog\Config\AppConfig;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Ebooks\EbookRepository;
use InfinityBlog\Ebooks\EbookFileService;
use InfinityBlog\Ebooks\EpubParser;
use InfinityBlog\Ebooks\EbookValidationException;

echo "=== STARTING EBOOK LIBRARY INTEGRATION & UNIT TESTS ===\n\n";

$config = AppConfig::fromEnvironment();
$pdo = ConnectionFactory::create($config->database);
$repo = new EbookRepository($pdo);
$files = new EbookFileService();

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message, string $err = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $message\n";
        $passed++;
    } else {
        echo "  [FAIL] $message" . ($err !== '' ? ": $err" : '') . "\n";
        $failed++;
    }
}

// -------------------------------------------------------------
// Test 1: Public Catalogue & Featured Books
// -------------------------------------------------------------
echo "[Test Suite 1] Public Catalogue Listing & Filtering\n";
$publicList = $repo->listPublic([], 1, 10);
assertTest(!empty($publicList['items']), "Public catalogue returns seeded books");
assertTest($publicList['total'] >= 2, "Public catalogue count matches seeded count (" . $publicList['total'] . ")");

// Format filter
$pdfList = $repo->listPublic(['format' => 'pdf'], 1, 10);
assertTest(!empty($pdfList['items']) && $pdfList['items'][0]['format'] === 'pdf', "Filter by format='pdf' returns only PDF books");

$epubList = $repo->listPublic(['format' => 'epub'], 1, 10);
assertTest(!empty($epubList['items']) && $epubList['items'][0]['format'] === 'epub', "Filter by format='epub' returns only EPUB books");

// Search filter
$searchResult = $repo->listPublic(['search' => 'Architect'], 1, 10);
assertTest(!empty($searchResult['items']) && str_contains($searchResult['items'][0]['title'], 'Architect'), "Search by keyword 'Architect' returns matching book");

// -------------------------------------------------------------
// Test 2: Privacy Enforcement (No Email Leaks)
// -------------------------------------------------------------
echo "\n[Test Suite 2] Privacy & Identity Verification\n";
foreach ($publicList['items'] as $item) {
    assertTest(!isset($item['email']), "Public eBook item does not have an 'email' field");
    assertTest(!isset($item['uploader']['email']), "Public uploader payload does not have an 'email' field");
    assertTest(isset($item['uploader']['handle']) && str_starts_with($item['uploader']['handle'], '@'), "Uploader handle correctly formatted with @ prefix: " . ($item['uploader']['handle'] ?? ''));
}

// -------------------------------------------------------------
// Test 3: Book Retrieval & Genuine Engagement Counters
// -------------------------------------------------------------
echo "\n[Test Suite 3] Book Retrieval & Engagement Counters\n";
$book1 = $publicList['items'][0];
$initialReads = (int) $book1['read_count'];
$initialDownloads = (int) $book1['download_count'];

$repo->incrementReadCount((int) $book1['id']);
$refreshed = $repo->findById((int) $book1['id']);
assertTest((int) $refreshed['read_count'] === $initialReads + 1, "incrementReadCount correctly increments genuine read count");

$repo->incrementDownloadCount((int) $book1['id']);
$refreshed2 = $repo->findById((int) $book1['id']);
assertTest((int) $refreshed2['download_count'] === $initialDownloads + 1, "incrementDownloadCount correctly increments download count");

// -------------------------------------------------------------
// Test 4: File Validation & Magic Byte Security
// -------------------------------------------------------------
echo "\n[Test Suite 4] File Security & Magic Byte Enforcements\n";
$validPdfBytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\nstartxref\n9\n%%EOF";
$tmpValidPdf = tempnam(sys_get_temp_dir(), 'valid_pdf_');
file_put_contents($tmpValidPdf, $validPdfBytes);

try {
    $res = $files->processUploadedBook([
        'tmp_name' => $tmpValidPdf,
        'name' => 'document.pdf',
        'size' => strlen($validPdfBytes),
        'error' => UPLOAD_ERR_OK,
    ], 1);
    assertTest($res['format'] === 'pdf' && $res['mime_type'] === 'application/pdf', "Valid PDF magic bytes (%PDF-) accepted");
    $files->deleteFile($res['file_path']);
} catch (\Throwable $e) {
    assertTest(false, "Valid PDF magic bytes accepted", $e->getMessage());
}
@unlink($tmpValidPdf);

// Malicious File Test: PHP script disguised as PDF
$fakePdfBytes = "<?php echo 'system exploit'; ?>";
$tmpFakePdf = tempnam(sys_get_temp_dir(), 'fake_pdf_');
file_put_contents($tmpFakePdf, $fakePdfBytes);

$caughtExploit = false;
try {
    $files->processUploadedBook([
        'tmp_name' => $tmpFakePdf,
        'name' => 'trojan.pdf',
        'size' => strlen($fakePdfBytes),
        'error' => UPLOAD_ERR_OK,
    ], 1);
} catch (EbookValidationException $e) {
    $caughtExploit = true;
}
@unlink($tmpFakePdf);
assertTest($caughtExploit, "Malicious PHP script disguised as PDF correctly rejected by magic byte inspection");

// -------------------------------------------------------------
// Test 5: In-Browser EPUB Parser & Chapter Extraction
// -------------------------------------------------------------
echo "\n[Test Suite 5] EPUB Parser, Manifest & Chapter Sanitization\n";
// Find the seeded EPUB book
$epubBook = null;
foreach ($publicList['items'] as $item) {
    if ($item['format'] === 'epub') {
        $epubBook = $item;
        break;
    }
}

if ($epubBook !== null) {
    $diskPath = $files->resolveDiskPath($epubBook['file_path']);
    assertTest($diskPath !== null && file_exists($diskPath), "EPUB storage disk path resolves accurately: $diskPath");

    $isEpubValid = EpubParser::isValidEpub($diskPath);
    assertTest($isEpubValid, "EpubParser confirms valid EPUB structure and container.xml");

    $manifest = EpubParser::getManifest($diskPath);
    assertTest(!empty($manifest['spine']) || !empty($manifest['total_chapters']), "EpubParser extracts chapters/spine from OPF package");

    $chapter0 = EpubParser::getChapterHtml($diskPath, 0, (int) $epubBook['id']);
    $htmlContent = $chapter0['html'] ?? $chapter0['content'] ?? '';
    assertTest(!empty($htmlContent), "EpubParser extracts sanitized chapter HTML");
    assertTest(!str_contains($htmlContent, '<script'), "Chapter HTML contains no malicious executable script tags");
} else {
    echo "  [SKIP] No EPUB book found to test parser\n";
}

// -------------------------------------------------------------
// Test 6: Reading Progress Tracking
// -------------------------------------------------------------
echo "\n[Test Suite 6] Reading Progress Persistence\n";
$userId = (int) $book1['user_id'];
$ebookId = (int) $book1['id'];

$savedProgress = $repo->saveReadingProgress($userId, $ebookId, 42, null, 75.5);
assertTest(!empty($savedProgress) && (int) $savedProgress['last_page'] === 42, "Reading progress saved (last_page = 42)");
assertTest(round((float) $savedProgress['percentage']) == 76, "Reading progress saved (percentage = 76%)");

$fetchedProgress = $repo->getReadingProgress($userId, $ebookId);
assertTest(!empty($fetchedProgress) && (int) $fetchedProgress['last_page'] === 42, "Reading progress retrieved accurately");

$continueReading = $repo->getContinueReading($userId, 5);
assertTest(!empty($continueReading), "getContinueReading returns active in-progress books for user");

// -------------------------------------------------------------
// Test 7: Personal Library Bookmarking
// -------------------------------------------------------------
echo "\n[Test Suite 7] Personal Library Integration (Save / Unsave)\n";
$repo->unsaveBook($userId, $ebookId); // start fresh
assertTest(!$repo->isBookSaved($userId, $ebookId), "Book initially not saved in personal library");

$repo->saveBook($userId, $ebookId);
assertTest($repo->isBookSaved($userId, $ebookId), "Book saved to personal library via saveBook()");

$savedBooks = $repo->getSavedBooks($userId);
assertTest(!empty($savedBooks) && (int) $savedBooks[0]['id'] === $ebookId, "getSavedBooks() includes the bookmarked eBook");

$repo->unsaveBook($userId, $ebookId);
assertTest(!$repo->isBookSaved($userId, $ebookId), "Book removed from library via unsaveBook()");

// -------------------------------------------------------------
// Test 8: Moderation & Reporting System
// -------------------------------------------------------------
echo "\n[Test Suite 8] eBook Reporting & Moderation Status Flow\n";
// Clean up previous test reports
$pdo->prepare("DELETE FROM ebook_reports WHERE ebook_id = ?")->execute([$ebookId]);

// Pick a different user as reporter
$otherUserStmt = $pdo->prepare("SELECT id FROM users WHERE id != ? LIMIT 1");
$otherUserStmt->execute([$userId]);
$reporterId = (int) ($otherUserStmt->fetchColumn() ?: ($userId + 9999));

$report = $repo->createReport($ebookId, $reporterId, 'copyright', 'Testing moderation reporting pipeline');
assertTest(!empty($report['id']) && $report['status'] === 'success', "eBook report created with status='pending'");

$reportsList = $repo->listReports('pending', 1, 10);
assertTest(!empty($reportsList['items']), "listReports('pending') includes newly submitted report");

// Moderate report: Hide book
$updated = $repo->updateReportStatus((int) $report['id'], 'reviewed', $userId, 'hide');
assertTest($updated, "updateReportStatus with action='hide' executed successfully");

// Verify book is hidden from public guests
$hiddenBookGuest = $repo->findPublished($ebookId, null, false);
assertTest($hiddenBookGuest === null, "findPublished returns null for guest viewer when book is hidden");

// Admin can still inspect hidden book
$hiddenBookAdmin = $repo->findPublished($ebookId, null, true);
assertTest($hiddenBookAdmin !== null && $hiddenBookAdmin['status'] === 'hidden', "findPublished returns hidden book for administrator");

// Restore book
$repo->updateReportStatus((int) $report['id'], 'reviewed', $userId, 'restore');
$restoredBook = $repo->findPublished($ebookId, null, false);
assertTest($restoredBook !== null && $restoredBook['status'] === 'published', "Book restored to published status and viewable by public");

// -------------------------------------------------------------
// Test 9: Anonymous Guest Submissions & Approval Workflow
// -------------------------------------------------------------
echo "\n[Test Suite 9] Anonymous Guest Submissions & Approval Workflow\n";
// Clean up any old test books with this test title
$pdo->prepare("DELETE FROM ebooks WHERE title = 'Anonymous Test eBook'")->execute();

$anonId = $repo->create(null, [
    'title' => 'Anonymous Test eBook',
    'author_name' => 'Anonymous Contributor',
    'description' => 'A test guide uploaded anonymously by a guest contributor without signing in.',
    'category_id' => 1,
    'file_path' => 'ebooks/files/test_anon.pdf',
    'file_format' => 'pdf',
    'file_size_bytes' => 10240,
    'allow_download' => 1,
    'submitter_name' => 'Alice Guest',
    'submitter_email' => 'alice.guest@example.org',
]);

$anonSubmission = $repo->findById($anonId);
assertTest($anonId > 0, "Anonymous eBook created successfully in database (ID: $anonId)");
assertTest(($anonSubmission['status'] ?? '') === 'pending', "Anonymous submission default status is 'pending'");

// Verify public guests CANNOT view pending submission
$guestView = $repo->findPublished($anonId, null, false);
assertTest($guestView === null, "findPublished returns NULL for unauthenticated visitor on pending submission");

// Verify admin CAN view pending submission
$adminView = $repo->findPublished($anonId, null, true);
assertTest($adminView !== null && $adminView['id'] === $anonId, "findPublished returns pending submission for administrator");

// Verify submitter_email is strictly excluded from public row formatting
assertTest(!isset($guestView['submitter_email']), "submitter_email is strictly excluded from public book format");
assertTest(!isset($adminView['submitter_email']), "submitter_email is excluded from public-facing findPublished response");

// Admin approves submission
$adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$adminId = (int) ($adminStmt->fetchColumn() ?: 1);

$approved = $repo->approveSubmission($anonId, $adminId);
assertTest($approved, "approveSubmission transitions submission status to 'published'");

// Verify public guests CAN now view approved submission
$guestViewApproved = $repo->findPublished($anonId, null, false);
assertTest($guestViewApproved !== null && $guestViewApproved['status'] === 'published', "findPublished returns approved book to public visitors");
assertTest($guestViewApproved['uploader_name'] === 'Alice Guest', "Anonymous uploader display name matches submitted name 'Alice Guest'");
assertTest($guestViewApproved['uploader_username'] === 'community', "Anonymous uploader handle mapped to '@community'");

// -------------------------------------------------------------
// Test 10: Anonymous Submissions Rejection & Moderation Reason Flow
// -------------------------------------------------------------
echo "\n[Test Suite 10] Submissions Rejection & Moderation Reason Flow\n";
$anon2Id = $repo->create(null, [
    'title' => 'Spam Test eBook',
    'author_name' => 'Spammer',
    'description' => 'This is an inappropriate submission that violates policy.',
    'category_id' => 1,
    'file_path' => 'ebooks/files/test_spam.pdf',
    'file_format' => 'pdf',
    'file_size_bytes' => 512,
    'allow_download' => 1,
    'submitter_name' => 'Malicious Submitter',
    'submitter_email' => 'spam@example.org',
]);

$rejected = $repo->rejectSubmission($anon2Id, $adminId, 'Spam content and copyright violation');
assertTest($rejected, "rejectSubmission transitions submission status to 'rejected'");

$rejectedBookAdmin = $repo->findById($anon2Id);
assertTest(($rejectedBookAdmin['status'] ?? '') === 'rejected', "eBook status updated to 'rejected'");
assertTest(str_contains((string) ($rejectedBookAdmin['rejection_reason'] ?? ''), 'Spam content'), "rejection_reason stored in database");

$rejectedGuestView = $repo->findPublished($anon2Id, null, false);
assertTest($rejectedGuestView === null, "findPublished strictly blocks 'rejected' eBooks from public visitors");

// Clean up test submissions
$pdo->prepare("DELETE FROM ebooks WHERE id IN (?, ?)")->execute([$anonId, $anon2Id]);

// -------------------------------------------------------------
// Test 11: EPUB Zip Slip & Path Traversal Attack Defense
// -------------------------------------------------------------
echo "\n[Test Suite 11] EPUB Zip Slip & Path Traversal Attack Defense\n";
$tmpZipSlip = tempnam(sys_get_temp_dir(), 'zipslip_') . '.epub';
$zip = new ZipArchive();
if ($zip->open($tmpZipSlip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->addFromString('../../../evil_traversal.txt', 'Malicious traversal payload');
    $zip->close();

    $zipSlipValid = EpubParser::isValidEpub($tmpZipSlip);
    assertTest(!$zipSlipValid, "EpubParser rejects EPUB containing Zip Slip traversal path (../)");
} else {
    echo "  [SKIP] Could not create test zip slip archive\n";
}
@unlink($tmpZipSlip);

// Dangerous executable inside EPUB
$tmpExeZip = tempnam(sys_get_temp_dir(), 'bad_exe_') . '.epub';
if ($zip->open($tmpExeZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->addFromString('OEBPS/backdoor.php', '<?php phpinfo(); ?>');
    $zip->close();

    $exeZipValid = EpubParser::isValidEpub($tmpExeZip);
    assertTest(!$exeZipValid, "EpubParser rejects EPUB containing dangerous executable extension (.php)");
} else {
    echo "  [SKIP] Could not create test exe zip archive\n";
}
@unlink($tmpExeZip);

// -------------------------------------------------------------
// Test 12: Cover Image Dimensions Validation Defense
// -------------------------------------------------------------
echo "\n[Test Suite 12] Cover Image Dimensions Validation Defense\n";
// Create a 20x20 PNG using base64 bytes (no GD dependency)
$tinyPngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAAD0lEQVR42mNk+M+ABYgNAA49ARV92y/iAAAAAElFTkSuQmCC');
$tmpTinyPath = tempnam(sys_get_temp_dir(), 'tiny_cover_') . '.png';
file_put_contents($tmpTinyPath, $tinyPngBytes);

$caughtSmallCover = false;
try {
    $files->processUploadedCover([
        'tmp_name' => $tmpTinyPath,
        'name' => 'tiny.jpg',
        'size' => filesize($tmpTinyPath),
        'error' => UPLOAD_ERR_OK,
    ]);
} catch (EbookValidationException $e) {
    $caughtSmallCover = true;
    $errors = $e->getErrors();
    assertTest(isset($errors['cover_image']) && str_contains($errors['cover_image'], '50x50'), "Cover validation error message specifies minimum 50x50 pixels: " . ($errors['cover_image'] ?? ''));
}
@unlink($tmpTinyPath);
assertTest($caughtSmallCover, "processUploadedCover rejects undersized cover image (<50x50px)");

// -------------------------------------------------------------
// Test 13: IP Rate Limiting Verification for Guest Uploads
// -------------------------------------------------------------
echo "\n[Test Suite 13] IP Rate Limiting Verification for Guest Uploads\n";
$testIp = '203.0.113.' . mt_rand(1, 254);
$limit = 3;
$win = 300;

// First 3 calls should pass
$pass1 = EbookFileService::checkUploadRateLimit($pdo, $testIp, $limit, $win);
$pass2 = EbookFileService::checkUploadRateLimit($pdo, $testIp, $limit, $win);
$pass3 = EbookFileService::checkUploadRateLimit($pdo, $testIp, $limit, $win);
assertTest($pass1 && $pass2 && $pass3, "checkUploadRateLimit permits submissions within limit (3/3)");

// 4th call should be blocked
$pass4 = EbookFileService::checkUploadRateLimit($pdo, $testIp, $limit, $win);
assertTest(!$pass4, "checkUploadRateLimit blocks submission exceeding rate limit (4th attempt)");


// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n=======================================================\n";
echo "TEST RESULTS: $passed PASSED, $failed FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
