<?php
declare(strict_types=1);
/**
 * @var array<string, mixed>|null $currentUser
 * @var string $csrfToken
 */
?>
<section class="page-container" style="max-width: 860px; margin: 40px auto; padding: 0 16px;">
  <div class="glass-card" style="padding: 40px 36px; border-radius: var(--radius-xl);">
    <div style="display: inline-block; padding: 4px 14px; border-radius: 9999px; background: rgba(99, 102, 241, 0.12); color: var(--primary-600, #4f46e5); font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px;">
      Privacy & Trust
    </div>
    <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-heading); margin-bottom: 8px; letter-spacing: -0.02em;">
      Privacy Policy
    </h1>
    <p style="color: var(--text-muted); font-size: 0.92rem; margin-bottom: 24px;">
      Last updated: October 2026 · Notice: Transparent disclosure of actual data processing practices on Infinity Blog.
    </p>

    <div class="article-body-content" style="line-height: 1.75; color: var(--text-body); font-size: 0.98rem;">
      <h2 style="font-size: 1.35rem; margin-top: 24px; margin-bottom: 12px; color: var(--text-heading);">1. Information We Collect and Process</h2>
      <p style="margin-bottom: 16px;">
        Infinity Blog processes information necessary to provide reading, publishing, community interaction, and security services:
      </p>
      <ul style="margin-left: 20px; margin-bottom: 16px;">
        <li><strong>Account credentials:</strong> Your chosen display name, username, email address, role, and securely hashed passwords (using <code>PASSWORD_DEFAULT</code>).</li>
        <li><strong>Optional author profile details:</strong> Bio, location, website URL, interests, and profile avatar or banner images.</li>
        <li><strong>Publishing content:</strong> Articles, drafts, versions, category selections, cover artwork, and media files.</li>
        <li><strong>Personal library and reading progress:</strong> Saved bookmarks, reading progress percentages, passage notes, custom collections, and muted topics (accessible only to your authenticated account).</li>
        <li><strong>Community interactions:</strong> Threaded article comments, reader scores (1–5 stars), followed authors, direct text messages, and chat messages.</li>
        <li><strong>Technical metadata:</strong> Session identifiers, CSRF verification tokens, and non-sensitive audit logs for security, rate limiting, and spam defense.</li>
      </ul>

      <h2 style="font-size: 1.35rem; margin-top: 24px; margin-bottom: 12px; color: var(--text-heading);">2. Cookies, Sessions & Local Storage</h2>
      <p style="margin-bottom: 16px;">
        We believe in transparent, minimal data storage:
      </p>
      <ul style="margin-left: 20px; margin-bottom: 16px;">
        <li><strong>Essential Session Cookie:</strong> <code>INFINITYBLOGSESSID</code> is strictly required for secure user authentication, role enforcement, and CSRF protection. It expires when your session terminates or upon logout.</li>
        <li><strong>Consent Preferences:</strong> <code>infinity_blog_cookie_consent</code> records whether you have accepted or declined optional analytics cookies.</li>
        <li><strong>Browser Local Storage:</strong> Used locally on your device to store autosaved drafts in the Writing Studio (to prevent accidental work loss) and to store reading comfort preferences (such as text size and distraction-free focus mode).</li>
      </ul>

      <h2 style="font-size: 1.35rem; margin-top: 24px; margin-bottom: 12px; color: var(--text-heading);">3. Cloud Storage & Third-Party Service Providers</h2>
      <p style="margin-bottom: 16px;">
        We work only with vetted service providers necessary to operate the platform:
      </p>
      <ul style="margin-left: 20px; margin-bottom: 16px;">
        <li><strong>Supabase Storage:</strong> Public profile avatars and public article cover images are stored securely in dedicated public buckets. Uploaded article videos are maintained in private buckets with short-lived signed playback URLs.</li>
        <li><strong>AI Summaries & Speech (OpenAI):</strong> When an author requests an AI summary or article speech assistance, only the public article title and article text are processed. We never send passwords, personal notes, or private messages to AI providers.</li>
        <li><strong>Google Analytics (Optional):</strong> If configured by site administrators and explicitly accepted via our cookie consent banner, privacy-conscious Google Analytics 4 measures general aggregated page views without transmitting sensitive personal text.</li>
      </ul>

      <h2 style="font-size: 1.35rem; margin-top: 24px; margin-bottom: 12px; color: var(--text-heading);">4. Profile Privacy & Visibility Controls</h2>
      <p style="margin-bottom: 16px;">
        You maintain control over your public footprint. When your profile visibility is set to <strong>Private</strong>, your profile and author page are excluded from public discovery feeds, search engines, and XML sitemaps. Your registered email address is never displayed publicly regardless of your visibility setting.
      </p>

      <h2 style="font-size: 1.35rem; margin-top: 24px; margin-bottom: 12px; color: var(--text-heading);">5. Data Retention & Your Rights</h2>
      <p style="margin-bottom: 16px;">
        You have the right to:
      </p>
      <ul style="margin-left: 20px; margin-bottom: 16px;">
        <li>Access, edit, or update your profile information at any time via <a href="/profile/edit" style="color: var(--primary-600, #4f46e5); font-weight: 600;">Edit Profile</a>.</li>
        <li>Modify, unpublish, or permanently delete your published articles and drafts via <a href="/posts/manage" style="color: var(--primary-600, #4f46e5); font-weight: 600;">Manage Articles</a>.</li>
        <li>Download formatted copies of your reading material via PDF and EPUB exports.</li>
        <li>Request complete account closure and removal of associated personal records.</li>
      </ul>

      <h2 style="font-size: 1.35rem; margin-top: 24px; margin-bottom: 12px; color: var(--text-heading);">6. Contacting Us About Privacy</h2>
      <p style="margin-bottom: 24px;">
        If you have questions, inquiries, or requests regarding this Privacy Policy or your personal information, please submit an inquiry through our <a href="/contact" style="color: var(--primary-600, #4f46e5); font-weight: 600;">Contact Form</a> or reach our privacy coordinator at <code style="background: rgba(0,0,0,0.06); padding: 2px 6px; border-radius: 4px;">privacy@infinityblog.local</code>.
      </p>
    </div>

    <div style="border-top: 1px solid rgba(226, 232, 240, 0.6); padding-top: 24px; margin-top: 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
      <a href="/terms" style="color: var(--primary-600, #4f46e5); font-weight: 600; text-decoration: none;">View Terms and Conditions →</a>
      <a href="/" class="btn btn-secondary btn-pill btn-sm">Return Home</a>
    </div>
  </div>
</section>
